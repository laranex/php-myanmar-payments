<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\WaveMoney;

use Laranex\PhpMyanmarPayments\Contracts\PaymentGateway;
use Laranex\PhpMyanmarPayments\Enums\PaymentStatus;
use Laranex\PhpMyanmarPayments\Exceptions\ApiException;
use Laranex\PhpMyanmarPayments\Exceptions\SignatureVerificationException;
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;
use Laranex\PhpMyanmarPayments\Http\Transport;
use Laranex\PhpMyanmarPayments\Results\PaymentCallback;
use Laranex\PhpMyanmarPayments\Results\RedirectPayment;
use Laranex\PhpMyanmarPayments\Support\StatusMap;
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
        'PAYMENT_REQUEST_CANCELLED' => PaymentStatus::Cancelled,
        'TRANSACTION_TIMED_OUT' => PaymentStatus::Expired,
        'SCHEDULER_TRANSACTION_TIMED_OUT' => PaymentStatus::Expired,
    ];

    private readonly Transport $transport;

    private readonly WaveMoneySigner $signer;

    public function __construct(
        public readonly WaveMoneyConfig $config,
        ?ClientInterface $httpClient = null,
    ) {
        $this->transport = new Transport($httpClient);
        $this->signer = new WaveMoneySigner($config->secretKey);
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
            'amount' => (string) $data->amount,
            'payment_description' => $data->description,
            'merchant_name' => $this->config->merchantName,
            'items' => json_encode(array_map(fn (WaveMoneyItem $item): array => $item->toArray(), $data->items), JSON_UNESCAPED_UNICODE),
            'hash' => $this->signer->requestHash(
                $this->config->timeToLiveSeconds,
                $this->config->merchantId,
                $data->orderId,
                $data->amount,
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
     * `orderId` falls back to `merchantReferenceId` because Wave marks `orderId` as optional.
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
            orderId: (string) ($payload['orderId'] ?? $payload['merchantReferenceId'] ?? ''),
            status: StatusMap::resolve(self::STATUSES, $gatewayStatus),
            gatewayStatus: $gatewayStatus,
            gatewayReference: isset($payload['transactionId']) ? (string) $payload['transactionId'] : null,
            amount: isset($payload['amount']) ? (string) $payload['amount'] : null,
            raw: $payload,
        );
    }

    /**
     * @param  array<array-key, mixed>  $body
     */
    private function errorMessage(array $body): string
    {
        if (is_array($body['errors'] ?? null)) {
            $messages = [];

            foreach ($body['errors'] as $field => $errors) {
                $messages[] = $field.': '.implode(' ', (array) $errors);
            }

            return implode('; ', $messages);
        }

        return (string) ($body['message'] ?? 'unexpected response');
    }
}
