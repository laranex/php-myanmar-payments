<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\WavePay;

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
 * Wave Pay (Pay with Wave API v1.3): redirect payments and callbacks. Wave has no status API, so the callback is
 * the only result.
 */
class WavePay implements PaymentGateway
{
    private const CURRENCY = 'MMK';

    private const STATUSES = [
        'PAYMENT_CONFIRMED' => PaymentStatus::Successful,
        'BILL_COLLECTION_FAILED' => PaymentStatus::Failed,
        'INSUFFICIENT_BALANCE' => PaymentStatus::Failed,
        'ACCOUNT_LOCKED' => PaymentStatus::Failed,
        'PAYMENT_REQUEST_CANCELLED' => PaymentStatus::Canceled,
        'TRANSACTION_TIMED_OUT' => PaymentStatus::Expired,
        'SCHEDULER_TRANSACTION_TIMED_OUT' => PaymentStatus::Expired,
    ];

    public readonly WavePayConfig $config;

    private readonly Transport $transport;

    private readonly WavePaySigner $signer;

    /**
     * @param  WavePayConfig|array<string, mixed>  $config  A config object or a `WavePayConfig::fromArray()` array.
     *
     * @throws ConfigurationException When a credential is missing.
     */
    public function __construct(
        WavePayConfig|array $config,
        ?ClientInterface $httpClient = null,
    ) {
        $this->config = $config instanceof WavePayConfig ? $config : WavePayConfig::fromArray($config);
        $this->transport = new Transport($httpClient, $this->config->timeoutSeconds);
        $this->signer = new WavePaySigner($this->config->secretKey);
    }

    /**
     * A gateway configured from the `WAVE_PAY_*` environment variables.
     *
     * @param  array<array-key, mixed>|null  $env  Variables to read; defaults to `getenv()` merged with `$_ENV`.
     *
     * @throws ConfigurationException When a credential is missing.
     */
    public static function fromEnv(?array $env = null, ?ClientInterface $httpClient = null): self
    {
        return new self(WavePayConfig::fromEnv($env), $httpClient);
    }

    /**
     * Create a payment request (a JSON post to `{baseUrl}/payment`, currency MMK) and get Wave's page to redirect the
     * customer to (`{baseRedirectUrl}/authenticate?transaction_id=...`).
     *
     * @throws ApiException
     */
    public function initiate(WavePayPaymentData $data): RedirectPayment
    {
        $timeToLiveSeconds = $data->timeToLiveSeconds ?? $this->config->timeToLiveSeconds;

        $amount = $data->amount->toString();
        $payload = [
            'merchant_id' => $this->config->merchantId,
            'order_id' => $data->orderId,
            'merchant_reference_id' => $data->merchantReferenceId,
            'frontend_result_url' => $data->returnUrl,
            'backend_result_url' => $data->callbackUrl,
            'amount' => (int) $amount,
            'time_to_live_in_seconds' => $timeToLiveSeconds,
            'payment_description' => $data->description,
            'currency' => self::CURRENCY,
            'hash' => $this->signer->requestHash(
                $timeToLiveSeconds,
                $this->config->merchantId,
                $data->orderId,
                $amount,
                $data->callbackUrl,
                $data->merchantReferenceId,
            ),
            'merchant_name' => $data->merchantName ?? $this->config->merchantName,
            'items' => (string) json_encode(array_map(fn (WavePayItem $item): array => $item->toArray(), $data->items), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];

        foreach (['additional_field_3' => $data->additionalField3, 'additional_field_4' => $data->additionalField4, 'additional_field_5' => $data->additionalField5] as $field => $value) {
            if ($value !== null && $value !== '') {
                $payload[$field] = $value;
            }
        }

        $response = $this->transport->postJson("{$this->config->baseUrl}/payment", $payload);

        $body = $response->json();
        $transactionId = $body['transaction_id'] ?? null;

        if (! $response->successful() || ($body['message'] ?? null) !== 'success' || ! is_string($transactionId) || $transactionId === '') {
            $message = $this->errorMessage($body);

            throw new ApiException(
                "Wave Pay payment request failed with HTTP {$response->status}: {$message}",
                gatewayCode: isset($body['errors']) ? 'VALIDATION_ERROR' : (isset($body['message']) ? (string) $body['message'] : null),
                gatewayMessage: $message,
                httpStatus: $response->status,
                raw: $body,
            );
        }

        return new RedirectPayment(
            orderId: $data->orderId,
            url: "{$this->config->baseRedirectUrl}/authenticate?transaction_id=".urlencode($transactionId),
            gatewayReference: $transactionId,
            raw: $body,
        );
    }

    /**
     * Verify Wave's callback. Only `PAYMENT_CONFIRMED` means the customer paid.
     *
     * `orderId` falls back to `merchantReferenceId` when it is missing, null or empty, as a safeguard: earlier Wave documents marked `orderId` optional.
     * Wave's hash joins the values with no separator, so after the hash matches the amount must be a non-negative
     * decimal, at least one of `orderId` / `merchantReferenceId` must be present, and each (when sent) may only hold
     * letters, digits, `_` and `-`.
     *
     * @throws SignatureVerificationException
     */
    public function handleCallback(CallbackRequest $request): PaymentCallback
    {
        $payload = $request->parsedBody();

        if (! $this->signer->verifyCallback($payload)) {
            throw new SignatureVerificationException('Wave Pay callback hash verification failed.', $payload);
        }

        if (! self::wellFormed($payload)) {
            throw new SignatureVerificationException('Wave Pay callback is malformed.', $payload);
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
    private static function wellFormed(array $payload): bool
    {
        $hasId = false;

        foreach (['orderId', 'merchantReferenceId'] as $field) {
            $value = Json::scalarString($payload[$field] ?? null);

            if ($value !== '' && preg_match('/^[A-Za-z0-9_-]+\z/', $value) !== 1) {
                return false;
            }

            $hasId = $hasId || $value !== '';
        }

        return $hasId && preg_match('/^\d+(\.\d+)?\z/', Json::scalarString($payload['amount'] ?? null)) === 1;
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
