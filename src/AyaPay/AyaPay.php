<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\AyaPay;

use Laranex\PhpMyanmarPayments\Contracts\PaymentGateway;
use Laranex\PhpMyanmarPayments\Enums\PaymentStatus;
use Laranex\PhpMyanmarPayments\Exceptions\ApiException;
use Laranex\PhpMyanmarPayments\Exceptions\SignatureVerificationException;
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;
use Laranex\PhpMyanmarPayments\Http\HttpResponse;
use Laranex\PhpMyanmarPayments\Http\Transport;
use Laranex\PhpMyanmarPayments\Results\FormPayment;
use Laranex\PhpMyanmarPayments\Results\PaymentCallback;
use Laranex\PhpMyanmarPayments\Results\PaymentStatusResult;
use Laranex\PhpMyanmarPayments\Support\StatusMap;
use Psr\Http\Client\ClientInterface;

/**
 * AYA Payment Gateway: one hosted checkout for AYA Pay, other wallets and cards.
 */
class AyaPay implements PaymentGateway
{
    private const CURRENCY_MMK = '104';

    private const STATUSES = [
        '00' => PaymentStatus::Successful,
        '01' => PaymentStatus::Pending,
        '02' => PaymentStatus::Failed,
        '03' => PaymentStatus::Failed,
        '04' => PaymentStatus::Expired,
    ];

    private readonly Transport $transport;

    private readonly AyaPaySigner $signer;

    public function __construct(
        public readonly AyaPayConfig $config,
        ?ClientInterface $httpClient = null,
    ) {
        $this->transport = new Transport($httpClient);
        $this->signer = new AyaPaySigner($config->appSecret);
    }

    /**
     * The payment channels enabled for your merchant account, and the methods each supports.
     *
     * @return list<AyaPayService>
     *
     * @throws ApiException
     */
    public function services(): array
    {
        $timestamp = time();
        $body = $this->ensureSuccess('services', $this->transport->postJson("{$this->config->baseUrl}/v1/payment/services", [
            'appKey' => $this->config->appKey,
            'timestamp' => $timestamp,
            'checkSum' => $this->signer->checksum([$this->config->appKey, $this->config->appSecret, $timestamp]),
        ]));

        $services = [];

        foreach ((array) ($body['data'] ?? []) as $service) {
            if (! is_array($service) || ! isset($service['key'])) {
                continue;
            }

            $methods = array_map('strval', (array) ($service['methods'] ?? []));

            $services[] = new AyaPayService(
                name: (string) ($service['name'] ?? $service['key']),
                key: (string) $service['key'],
                imageUrl: isset($service['image_url']) ? (string) $service['image_url'] : null,
                methods: array_values(array_filter(array_map(fn (string $method): ?AyaPayMethod => AyaPayMethod::tryFrom($method), $methods))),
                unknownMethods: array_values(array_filter($methods, fn (string $method): bool => AyaPayMethod::tryFrom($method) === null)),
            );
        }

        return $services;
    }

    /**
     * Sign the order. The customer's browser must POST the returned form to AYA's hosted checkout.
     */
    public function initiate(AyaPayPaymentData $data): FormPayment
    {
        $userRefs = array_pad($data->userRefs, 5, '');

        $fields = [
            'merchOrderId' => $data->orderId,
            'amount' => $data->amount->toString(),
            'appKey' => $this->config->appKey,
            'timestamp' => (string) time(),
            'userRef1' => $userRefs[0],
            'userRef2' => $userRefs[1],
            'userRef3' => $userRefs[2],
            'userRef4' => $userRefs[3],
            'userRef5' => $userRefs[4],
            'description' => (string) $data->description,
            'currencyCode' => self::CURRENCY_MMK,
            'channel' => $data->channel,
            'method' => $data->method->value,
            'overrideFrontendRedirectUrl' => (string) $data->returnUrl,
        ];

        $fields['checkSum'] = $this->signer->checksum(array_values($fields));

        return new FormPayment(
            orderId: $data->orderId,
            action: "{$this->config->baseUrl}/v1/payment/request",
            fields: $fields,
            enctype: 'multipart/form-data',
        );
    }

    /**
     * Ask AYA for the current state of an order.
     *
     * @throws ApiException
     * @throws SignatureVerificationException
     */
    public function status(string $orderId): PaymentStatusResult
    {
        $timestamp = time();
        $body = $this->ensureSuccess('enquiry', $this->transport->postJson("{$this->config->baseUrl}/v1/payment/enquiry", [
            'merchOrderId' => $orderId,
            'appKey' => $this->config->appKey,
            'timestamp' => $timestamp,
            'checkSum' => $this->signer->checksum([$orderId, $timestamp, $this->config->appKey]),
        ]));

        $payload = $this->verifiedPayload(is_array($body['data'] ?? null) ? $body['data'] : [], 'enquiry response');
        $statusCode = trim((string) ($payload['statusCode'] ?? ''));

        return new PaymentStatusResult(
            orderId: (string) ($payload['merchOrderId'] ?? $orderId),
            status: StatusMap::resolve(self::STATUSES, $statusCode),
            gatewayStatus: $statusCode,
            gatewayReference: isset($payload['tranId']) ? (string) $payload['tranId'] : null,
            amount: isset($payload['amount']) ? (string) $payload['amount'] : null,
            raw: $payload,
        );
    }

    /**
     * Verify AYA's backend callback.
     *
     * @throws SignatureVerificationException
     */
    public function handleCallback(CallbackRequest $request): PaymentCallback
    {
        return $this->toCallback($this->verifiedPayload($request->input(), 'callback'));
    }

    /**
     * Verify the signed query string AYA adds when it sends the customer back to your return URL.
     * Use it to show the right page; still fulfill orders from the backend callback.
     *
     * @throws SignatureVerificationException
     */
    public function verifyRedirect(CallbackRequest $request): PaymentCallback
    {
        return $this->toCallback($this->verifiedPayload($request->query + $request->parsedBody(), 'redirect'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function toCallback(array $payload): PaymentCallback
    {
        $statusCode = trim((string) ($payload['statusCode'] ?? ''));

        return new PaymentCallback(
            orderId: (string) ($payload['merchOrderId'] ?? ''),
            status: StatusMap::resolve(self::STATUSES, $statusCode),
            gatewayStatus: $statusCode,
            gatewayReference: isset($payload['tranId']) ? (string) $payload['tranId'] : null,
            amount: isset($payload['amount']) ? (string) $payload['amount'] : null,
            raw: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $input  Holds `payload` (base64 JSON) and `checkSum`.
     * @return array<string, mixed>
     */
    private function verifiedPayload(array $input, string $context): array
    {
        $payload = $this->signer->verifyPayload((string) ($input['payload'] ?? ''), (string) ($input['checkSum'] ?? ''));

        if ($payload === null) {
            throw new SignatureVerificationException("AYA Pay {$context} checksum verification failed.", $input);
        }

        return $payload;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function ensureSuccess(string $endpoint, HttpResponse $response): array
    {
        $body = $response->json();
        $status = isset($body['status']) ? (string) $body['status'] : null;

        if (! $response->successful() || $status !== '00') {
            $message = isset($body['message']) ? (string) $body['message'] : null;

            throw new ApiException(
                "AYA Pay {$endpoint} failed".($status ? ": [{$status}] {$message}" : " with HTTP {$response->status}."),
                gatewayCode: $status,
                gatewayMessage: $message,
                httpStatus: $response->status,
                raw: $body,
            );
        }

        return $body;
    }
}
