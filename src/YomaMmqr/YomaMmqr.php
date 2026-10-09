<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\YomaMmqr;

use DateTimeImmutable;
use Laranex\PhpMyanmarPayments\Contracts\PaymentGateway;
use Laranex\PhpMyanmarPayments\Enums\PaymentStatus;
use Laranex\PhpMyanmarPayments\Exceptions\ApiException;
use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\Exceptions\SignatureVerificationException;
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;
use Laranex\PhpMyanmarPayments\Http\HttpResponse;
use Laranex\PhpMyanmarPayments\Http\Transport;
use Laranex\PhpMyanmarPayments\Results\PaymentCallback;
use Laranex\PhpMyanmarPayments\Results\PaymentStatusResult;
use Laranex\PhpMyanmarPayments\Results\QrPayment;
use Laranex\PhpMyanmarPayments\Support\ArrayCache;
use Laranex\PhpMyanmarPayments\Support\Json;
use Psr\Http\Client\ClientInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Yoma Bank MMQR: QR payments, status checks and callbacks.
 *
 * Access tokens last 8 hours and are cached; pass a shared PSR-16 cache so they survive between requests.
 */
class YomaMmqr implements PaymentGateway
{
    /**
     * Seconds a generated QR stays payable.
     */
    public const QR_LIFETIME_SECONDS = 120;

    private const STATUSES = [
        'SUCCESS' => PaymentStatus::Successful,
        'PENDING' => PaymentStatus::Pending,
        'FAILED' => PaymentStatus::Failed,
        'FAIL' => PaymentStatus::Failed,
    ];

    /**
     * Prefix of the access token's cache key, the same in every Laranex SDK so services in different languages
     * can share one cache. The key ends with the SHA-256 of `<base url>|<client id>`.
     */
    public const TOKEN_CACHE_PREFIX = 'myanmar-payments.yoma-mmqr.token.';

    public readonly YomaMmqrConfig $config;

    private readonly Transport $transport;

    private readonly CacheInterface $cache;

    /**
     * @param  YomaMmqrConfig|array<string, mixed>  $config  A config object or a `YomaMmqrConfig::fromArray()` array.
     *
     * @throws ConfigurationException When a credential is missing.
     */
    public function __construct(
        YomaMmqrConfig|array $config,
        ?ClientInterface $httpClient = null,
        ?CacheInterface $cache = null,
    ) {
        $this->config = $config instanceof YomaMmqrConfig ? $config : YomaMmqrConfig::fromArray($config);
        $this->transport = new Transport($httpClient);
        $this->cache = $cache ?? new ArrayCache;
    }

    /**
     * A gateway configured from the `YOMA_MMQR_*` environment variables.
     *
     * @param  array<array-key, mixed>|null  $env  Variables to read; defaults to `getenv()` merged with `$_ENV`.
     *
     * @throws ConfigurationException When a credential is missing.
     */
    public static function fromEnv(?array $env = null, ?ClientInterface $httpClient = null, ?CacheInterface $cache = null): self
    {
        return new self(YomaMmqrConfig::fromEnv($env), $httpClient, $cache);
    }

    /**
     * Check the order out with Yoma and generate its first QR. Call once per order.
     *
     * @throws ApiException
     */
    public function initiate(YomaMmqrPaymentData $data): QrPayment
    {
        $body = $this->call('payment/checkout', [
            'merchantId' => $this->config->merchantId,
            'orderNumber' => $data->orderId,
            'amount' => $data->amount->toString(),
            'description' => $data->description,
        ]);

        if (($body['checkOutStatus'] ?? null) !== true) {
            throw new ApiException('Yoma MMQR did not confirm the checkout.', raw: $body);
        }

        return $this->renewQr($data->orderId);
    }

    /**
     * Generate a new QR for an order that is already checked out, e.g. after the previous one expired.
     * The previous QR's reference stops working.
     *
     * @throws ApiException
     */
    public function renewQr(string $orderId): QrPayment
    {
        $body = $this->call('qr/generate', [
            'merchantId' => $this->config->merchantId,
            'orderNumber' => $orderId,
        ]);

        if (! is_string($body['qrString'] ?? null) || $body['qrString'] === '' || ! is_scalar($body['refLabel'] ?? null) || (string) $body['refLabel'] === '') {
            throw new ApiException('Yoma MMQR did not return a QR.', raw: $body);
        }

        return new QrPayment(
            orderId: $orderId,
            qrImage: $body['qrString'],
            expiresAt: new DateTimeImmutable('+'.self::QR_LIFETIME_SECONDS.' seconds'),
            reference: (string) $body['refLabel'],
            raw: $body,
        );
    }

    /**
     * Check a QR's payment status by its reference (`QrPayment::$reference`). An expired QR reports `Expired`.
     *
     * @throws ApiException
     */
    public function status(string $reference): PaymentStatusResult
    {
        $body = $this->call('payment/check-status', [
            'merchantId' => $this->config->merchantId,
            'refLabel' => $reference,
        ], allowErrors: ['QR EXPIRED']);

        if (($body['errorCode'] ?? null) === 'QR EXPIRED') {
            return new PaymentStatusResult(
                orderId: null,
                status: PaymentStatus::Expired,
                gatewayStatus: 'QR EXPIRED',
                gatewayReference: $reference,
                raw: $body,
            );
        }

        $paymentStatus = trim(Json::scalarString($body['paymentStatus'] ?? null));
        $refLabel = Json::scalarString($body['refLabel'] ?? null);

        return new PaymentStatusResult(
            orderId: null,
            status: PaymentStatus::resolve(self::STATUSES, strtoupper($paymentStatus)),
            gatewayStatus: $paymentStatus,
            gatewayReference: $refLabel !== '' ? $refLabel : $reference,
            raw: $body,
        );
    }

    /**
     * Verify Yoma's payment callback.
     *
     * @throws SignatureVerificationException
     */
    public function handleCallback(CallbackRequest $request): PaymentCallback
    {
        $payload = $request->parsedBody();

        if ($this->config->webhookSecret !== null && ! hash_equals($this->config->webhookSecret, (string) $request->header('X-Webhook-Secret'))) {
            throw new SignatureVerificationException('Yoma MMQR callback has a missing or wrong X-Webhook-Secret header.', $payload);
        }

        $orderNumber = Json::scalarString($payload['orderNumber'] ?? null);
        $status = trim(Json::scalarString($payload['status'] ?? null));
        $hashValue = strtolower(Json::scalarString($payload['hashValue'] ?? null));
        $expected = hash_hmac('sha256', "orderNumber={$orderNumber}&status={$status}", $orderNumber.$this->config->webhookHashKey);

        if ($orderNumber === '' || Json::isNested($payload['status'] ?? null) || ! hash_equals($expected, $hashValue)) {
            throw new SignatureVerificationException('Yoma MMQR callback hash verification failed.', $payload);
        }

        return new PaymentCallback(
            orderId: $orderNumber,
            status: PaymentStatus::resolve(self::STATUSES, strtoupper($status)),
            gatewayStatus: $status,
            raw: $payload,
        );
    }

    /**
     * Forget the cached access token, e.g. after rotating the client secret.
     */
    public function forgetToken(): void
    {
        $this->cache->delete($this->tokenCacheKey());
    }

    /**
     * @param  array<string, string>  $data
     * @param  list<string>  $allowErrors  Business error codes the caller handles itself.
     * @return array<array-key, mixed>
     */
    private function call(string $path, array $data, array $allowErrors = []): array
    {
        $url = "{$this->config->baseUrl}/payment-gateway/{$this->config->apiVersion}/api/{$path}";
        $response = $this->transport->postJson($url, $data, ['Authorization' => 'Bearer '.$this->token()]);

        if ($response->status === 401) {
            $this->forgetToken();
            $response = $this->transport->postJson($url, $data, ['Authorization' => 'Bearer '.$this->token()]);
        }

        $body = $response->json();
        $errorCode = isset($body['errorCode']) ? (string) $body['errorCode'] : null;

        if (! $response->successful() || ($errorCode !== null && ! in_array($errorCode, $allowErrors, true))) {
            $this->fail($path, $response, $body, $errorCode);
        }

        return $body;
    }

    private function token(): string
    {
        $token = $this->cache->get($this->tokenCacheKey());

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $response = $this->transport->postForm("{$this->config->baseUrl}/token", ['grant_type' => 'client_credentials'], [
            'Authorization' => 'Basic '.base64_encode("{$this->config->clientId}:{$this->config->clientSecret}"),
        ]);

        $body = $response->json();
        $token = $body['access_token'] ?? null;

        if (! $response->successful() || ! is_string($token) || $token === '') {
            $this->fail('token', $response, $body, isset($body['error']) ? (string) $body['error'] : null);
        }

        // The leading digits of `expires_in`, e.g. 28800 for `28800` or `"28800.0"`; anything else means one hour.
        $expiresIn = preg_match('/^\s*([0-9]+)/', Json::scalarString($body['expires_in'] ?? null), $match) === 1 ? (int) $match[1] : 0;
        $this->cache->set($this->tokenCacheKey(), $token, max(60, ($expiresIn > 0 ? $expiresIn : 3600) - 60));

        return $token;
    }

    private function tokenCacheKey(): string
    {
        return self::TOKEN_CACHE_PREFIX.hash('sha256', $this->config->baseUrl.'|'.$this->config->clientId);
    }

    /**
     * @param  array<array-key, mixed>  $body
     */
    private function fail(string $endpoint, HttpResponse $response, array $body, ?string $errorCode): never
    {
        $message = isset($body['errorDescription']) ? (string) $body['errorDescription'] : (isset($body['error_description']) ? (string) $body['error_description'] : null);

        throw new ApiException(
            "Yoma MMQR {$endpoint} failed".($errorCode !== null && $errorCode !== '' ? rtrim(": [{$errorCode}] {$message}") : " with HTTP {$response->status}."),
            gatewayCode: $errorCode,
            gatewayMessage: $message,
            httpStatus: $response->status,
            raw: $body,
        );
    }
}
