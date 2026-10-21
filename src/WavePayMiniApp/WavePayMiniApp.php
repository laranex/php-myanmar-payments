<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\WavePayMiniApp;

use Laranex\PhpMyanmarPayments\Enums\PaymentStatus;
use Laranex\PhpMyanmarPayments\Exceptions\ApiException;
use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\Exceptions\SignatureVerificationException;
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;
use Laranex\PhpMyanmarPayments\Http\Transport;
use Laranex\PhpMyanmarPayments\Results\MiniAppPayment;
use Laranex\PhpMyanmarPayments\Results\PaymentCallback;
use Laranex\PhpMyanmarPayments\Support\ArrayCache;
use Laranex\PhpMyanmarPayments\Support\Json;
use Psr\Http\Client\ClientInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Wave Pay Mini App (Wave Money JS SDK spec v1.8): the customer pays inside the WavePay app, on your web page loaded
 * in its webview. Your server covers three steps:
 *
 * 1. `accessToken()` for the page's `WaveJsSDK.authModule.loginWithAccessToken(token)`.
 * 2. `payment()` signs the object the page passes to `WaveJsSDK.paymentModule.makePayment(payload)`.
 * 3. `handleCallback()` verifies the result Wave posts to your callback URL.
 *
 * Wave has no status API, so the callback is the only result. Access tokens are kept in the cache you pass (see
 * `YomaMmqr` for why a shared cache matters); the cache key is shared with the other Laranex SDKs.
 *
 * It does not implement `PaymentGateway`: the callback carries neither the mini app id nor the callback URL, so
 * `handleCallback()` also takes the callback URL the payment was signed with.
 */
class WavePayMiniApp
{
    /**
     * Prefix of the access token's cache key, the same in every Laranex SDK so services in different languages
     * can share one cache. The key ends with the SHA-256 of `<base url>|<client id>`.
     */
    public const TOKEN_CACHE_PREFIX = 'myanmar-payments.wave-pay-mini-app.token.';

    private const STATUSES = [
        'successful' => PaymentStatus::Successful,
        'processing' => PaymentStatus::Pending,
        'failed' => PaymentStatus::Failed,
    ];

    /**
     * The callback fields Wave documents. A nested (object or list) value in any of them fails verification.
     */
    private const CALLBACK_FIELDS = ['merchantRefId', 'orderId', 'amount', 'msisdn', 'hashValue', 'transactionId', 'transactionStatus'];

    public readonly WavePayMiniAppConfig $config;

    public readonly WavePayMiniAppSigner $signer;

    private readonly Transport $transport;

    private readonly CacheInterface $cache;

    /**
     * @param  WavePayMiniAppConfig|array<string, mixed>  $config  A config object or a `WavePayMiniAppConfig::fromArray()` array.
     * @param  CacheInterface|null  $cache  Where the access token is kept. Pass a shared cache in production; the
     *                                      in-memory default only lasts for the current PHP process.
     *
     * @throws ConfigurationException When a credential is missing.
     */
    public function __construct(
        WavePayMiniAppConfig|array $config,
        ?ClientInterface $httpClient = null,
        ?CacheInterface $cache = null,
    ) {
        $this->config = $config instanceof WavePayMiniAppConfig ? $config : WavePayMiniAppConfig::fromArray($config);
        $this->transport = new Transport($httpClient, $this->config->timeoutSeconds);
        $this->signer = new WavePayMiniAppSigner($this->config->secretKey);
        $this->cache = $cache ?? new ArrayCache;
    }

    /**
     * A gateway configured from the `WAVE_PAY_MINI_APP_*` environment variables.
     *
     * @param  array<array-key, mixed>|null  $env  Variables to read; defaults to `getenv()` merged with `$_ENV`.
     *
     * @throws ConfigurationException When a credential is missing.
     */
    public static function fromEnv(?array $env = null, ?ClientInterface $httpClient = null, ?CacheInterface $cache = null): self
    {
        return new self(WavePayMiniAppConfig::fromEnv($env), $httpClient, $cache);
    }

    /**
     * An access token for the mini app page's `WaveJsSDK.authModule.loginWithAccessToken(token)`, from the cache or
     * a form post of the client credentials to `{baseUrl}/auth/v1/external-utility/token`. It is kept for its
     * `expires_in` minus a minute (at least a minute; an hour when Wave sends none). Never call the token endpoint
     * from the page: it needs the client secret.
     *
     * @throws ApiException When the request fails or the response has no `access_token`.
     */
    public function accessToken(): string
    {
        $token = $this->cache->get($this->tokenCacheKey());

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $response = $this->transport->postForm("{$this->config->baseUrl}/auth/v1/external-utility/token", [
            'client_id' => $this->config->clientId,
            'client_secret' => $this->config->clientSecret,
            'grant_type' => 'client_credentials',
        ]);

        $body = $response->json();
        $token = Json::scalarString($body['access_token'] ?? null);

        if (! $response->successful() || $token === '') {
            $errorCode = self::nonEmpty($body['error'] ?? null);
            $message = self::nonEmpty($body['error_description'] ?? null) ?? self::nonEmpty($body['message'] ?? null);

            throw new ApiException(
                'Wave Pay Mini App token request failed'.($errorCode !== null ? rtrim(": [{$errorCode}] {$message}") : " with HTTP {$response->status}."),
                gatewayCode: $errorCode,
                gatewayMessage: $message,
                httpStatus: $response->status,
                raw: $body,
            );
        }

        // The leading digits of `expires_in`, e.g. 300 for `300` or `"300.0"`; anything else means one hour.
        $expiresIn = preg_match('/^\s*([0-9]+)/', Json::scalarString($body['expires_in'] ?? null), $match) === 1 ? (int) $match[1] : 0;
        $this->cache->set($this->tokenCacheKey(), $token, max(60, ($expiresIn > 0 ? $expiresIn : 3600) - 60));

        return $token;
    }

    /**
     * Forget the cached access token, e.g. after rotating the client secret.
     */
    public function forgetToken(): void
    {
        $this->cache->delete($this->tokenCacheKey());
    }

    /**
     * Sign a payment for the page's `WaveJsSDK.paymentModule.makePayment(payload)`. No request is sent: pass
     * `toArray()` (or `toJson()`) to the page unchanged.
     */
    public function payment(WavePayMiniAppPaymentData $data): MiniAppPayment
    {
        $amount = $data->amount->wholePart();

        return new MiniAppPayment(
            orderId: $data->orderId,
            amount: $data->amount,
            miniAppId: $this->config->miniAppId,
            merchantReferenceId: $data->merchantReferenceId,
            callbackUrl: $data->callbackUrl,
            hashValue: $this->signer->hash($amount, $data->orderId, $this->config->miniAppId, $data->merchantReferenceId, $data->callbackUrl),
            items: array_map(fn (WavePayMiniAppItem $item): array => ['name' => $item->name, 'amount' => $item->amount], $data->items),
        );
    }

    /**
     * Verify Wave's callback. Only `successful` means the customer paid; `processing` is pending and `failed` failed
     * (matched trimmed and case-insensitively).
     *
     * The callback carries neither the mini app id nor the callback URL, so pass the callback URL the payment was
     * signed with: normally the URL this callback was posted to, without its query string. The mini app id comes
     * from the configuration. After the hash matches, `orderId` and `merchantRefId` must not be empty and `amount`
     * must be a whole number. `merchantRefId` is in `raw`. Wave defines no response body: acknowledge with an empty 200.
     *
     * @throws SignatureVerificationException
     */
    public function handleCallback(CallbackRequest $request, string $callbackUrl): PaymentCallback
    {
        $payload = $request->parsedBody();

        foreach (self::CALLBACK_FIELDS as $field) {
            if (Json::isNested($payload[$field] ?? null)) {
                throw new SignatureVerificationException('Wave Pay Mini App callback hash verification failed.', $payload);
            }
        }

        $hashValue = $payload['hashValue'] ?? null;
        $orderId = Json::scalarString($payload['orderId'] ?? null);
        $merchantReferenceId = Json::scalarString($payload['merchantRefId'] ?? null);
        $amount = Json::scalarString($payload['amount'] ?? null);

        if (! is_string($hashValue) || ! $this->signer->verify($hashValue, $amount, $orderId, $this->config->miniAppId, $merchantReferenceId, $callbackUrl)) {
            throw new SignatureVerificationException('Wave Pay Mini App callback hash verification failed.', $payload);
        }

        if ($orderId === '' || $merchantReferenceId === '' || preg_match('/^[0-9]+\z/', $amount) !== 1) {
            throw new SignatureVerificationException('Wave Pay Mini App callback is malformed.', $payload);
        }

        $gatewayStatus = trim(Json::scalarString($payload['transactionStatus'] ?? null));

        return new PaymentCallback(
            orderId: $orderId,
            status: PaymentStatus::resolve(self::STATUSES, strtolower($gatewayStatus)),
            gatewayStatus: $gatewayStatus,
            gatewayReference: Json::scalar($payload['transactionId'] ?? null),
            amount: $amount,
            raw: $payload,
        );
    }

    private function tokenCacheKey(): string
    {
        return self::TOKEN_CACHE_PREFIX.hash('sha256', $this->config->baseUrl.'|'.$this->config->clientId);
    }

    /**
     * A response field as text, or null when it is missing, empty or not a scalar.
     */
    private static function nonEmpty(mixed $value): ?string
    {
        $text = Json::scalarString($value);

        return $text !== '' ? $text : null;
    }
}
