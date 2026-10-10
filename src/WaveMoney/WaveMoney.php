<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\WaveMoney;

use Laranex\PhpMyanmarPayments\Contracts\PaymentGateway;
use Laranex\PhpMyanmarPayments\Enums\PaymentStatus;
use Laranex\PhpMyanmarPayments\Exceptions\ApiException;
use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\Exceptions\SignatureVerificationException;
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;
use Laranex\PhpMyanmarPayments\Http\Transport;
use Laranex\PhpMyanmarPayments\Results\PaymentCallback;
use Laranex\PhpMyanmarPayments\Results\RedirectPayment;
use Laranex\PhpMyanmarPayments\Support\Json;
use Psr\Http\Client\ClientInterface;

/**
 * Wave Money: redirect payments and callbacks. Wave has no status API, so the callback is the only result.
 */
class WaveMoney implements PaymentGateway
{
    private const STATUSES = [
        'PAYMENT_CONFIRMED' => PaymentStatus::Successful,
        'INSUFFICIENT_BALANCE' => PaymentStatus::Pending,
        'ACCOUNT_LOCKED' => PaymentStatus::Failed,
        'BILL_COLLECTION_FAILED' => PaymentStatus::Failed,
        'PAYMENT_REQUEST_CANCELLED' => PaymentStatus::Canceled,
        'TRANSACTION_TIMED_OUT' => PaymentStatus::Expired,
        'SCHEDULER_TRANSACTION_TIMED_OUT' => PaymentStatus::Expired,
    ];

    public readonly WaveMoneyConfig $config;

    private readonly Transport $transport;

    private readonly WaveMoneySigner $signer;

    /**
     * @param  WaveMoneyConfig|array<string, mixed>  $config  A config object or a `WaveMoneyConfig::fromArray()` array.
     *
     * @throws ConfigurationException When a credential is missing.
     */
    public function __construct(
        WaveMoneyConfig|array $config,
        ?ClientInterface $httpClient = null,
    ) {
        $this->config = $config instanceof WaveMoneyConfig ? $config : WaveMoneyConfig::fromArray($config);
        $this->transport = new Transport($httpClient, $this->config->timeoutSeconds);
        $this->signer = new WaveMoneySigner($this->config->secretKey);
    }

    /**
     * A gateway configured from the `WAVE_MONEY_*` environment variables.
     *
     * @param  array<array-key, mixed>|null  $env  Variables to read; defaults to `getenv()` merged with `$_ENV`.
     *
     * @throws ConfigurationException When a credential is missing.
     */
    public static function fromEnv(?array $env = null, ?ClientInterface $httpClient = null): self
    {
        return new self(WaveMoneyConfig::fromEnv($env), $httpClient);
    }

    /**
     * Create a payment request and get Wave's page to redirect the customer to.
     *
     * @throws ApiException
     */
    public function initiate(WaveMoneyPaymentData $data): RedirectPayment
    {
        $response = $this->transport->postForm("{$this->config->baseUrl}/payment", [
            'time_to_live_in_seconds' => (string) $this->config->timeToLiveSeconds,
            'merchant_id' => $this->config->merchantId,
            'order_id' => $data->orderId,
            'merchant_reference_id' => $data->merchantReferenceId,
            'frontend_result_url' => $data->returnUrl,
            'backend_result_url' => $data->callbackUrl,
            'amount' => $data->amount->toString(),
            'payment_description' => $data->description,
            'merchant_name' => $this->config->merchantName,
            'items' => json_encode(array_map(fn (WaveMoneyItem $item): array => $item->toArray(), $data->items), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'hash' => $this->signer->requestHash(
                $this->config->timeToLiveSeconds,
                $this->config->merchantId,
                $data->orderId,
                $data->amount->toString(),
                $data->callbackUrl,
                $data->merchantReferenceId,
            ),
        ]);

        $body = $response->json();
        $transactionId = $body['transaction_id'] ?? null;

        if (! $response->successful() || ($body['message'] ?? null) !== 'success' || ! is_string($transactionId) || $transactionId === '') {
            $message = $this->errorMessage($body);

            throw new ApiException(
                "Wave Money payment request failed with HTTP {$response->status}: {$message}",
                gatewayCode: isset($body['errors']) ? 'VALIDATION_ERROR' : (isset($body['message']) ? (string) $body['message'] : null),
                gatewayMessage: $message,
                httpStatus: $response->status,
                raw: $body,
            );
        }

        return new RedirectPayment(
            orderId: $data->orderId,
            url: "{$this->config->authenticateUrl}/authenticate?transaction_id=".urlencode($transactionId),
            gatewayReference: $transactionId,
            raw: $body,
        );
    }

    /**
     * Verify Wave's callback. Only `PAYMENT_CONFIRMED` means the customer paid.
     *
     * `orderId` falls back to `merchantReferenceId` when it is missing, null or empty, because Wave marks `orderId` as optional.
     *
     * @throws SignatureVerificationException
     */
    public function handleCallback(CallbackRequest $request): PaymentCallback
    {
        $payload = $request->parsedBody();

        if (! $this->signer->verifyCallback($payload)) {
            throw new SignatureVerificationException('Wave Money callback hash verification failed.', $payload);
        }

        $gatewayStatus = trim((string) ($payload['status'] ?? ''));

        return new PaymentCallback(
            orderId: $this->orderId($payload),
            status: PaymentStatus::resolve(self::STATUSES, $gatewayStatus),
            gatewayStatus: $gatewayStatus,
            gatewayReference: isset($payload['transactionId']) ? (string) $payload['transactionId'] : null,
            amount: isset($payload['amount']) ? (string) $payload['amount'] : null,
            raw: $payload,
        );
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private function orderId(array $payload): string
    {
        $orderId = (string) ($payload['orderId'] ?? '');

        return $orderId !== '' ? $orderId : (string) ($payload['merchantReferenceId'] ?? '');
    }

    /**
     * @param  array<array-key, mixed>  $body
     */
    private function errorMessage(array $body): string
    {
        if (is_array($body['errors'] ?? null)) {
            $errors = $body['errors'];
            $fields = array_map('strval', array_keys($errors));
            sort($fields, SORT_STRING);
            $messages = [];

            foreach ($fields as $field) {
                $texts = array_map(Json::scalarString(...), array_filter((array) $errors[$field], is_scalar(...)));
                $messages[] = $field.': '.implode(' ', $texts);
            }

            return implode('; ', $messages);
        }

        $message = Json::scalarString($body['message'] ?? null);

        return $message !== '' ? $message : 'unexpected response';
    }
}
