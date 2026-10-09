<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\AyaPay;

use Laranex\PhpMyanmarPayments\Contracts\PaymentGateway;
use Laranex\PhpMyanmarPayments\Enums\PaymentStatus;
use Laranex\PhpMyanmarPayments\Exceptions\ApiException;
use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\Exceptions\SignatureVerificationException;
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;
use Laranex\PhpMyanmarPayments\Http\HttpResponse;
use Laranex\PhpMyanmarPayments\Http\Transport;
use Laranex\PhpMyanmarPayments\Results\FormPayment;
use Laranex\PhpMyanmarPayments\Results\PaymentCallback;
use Laranex\PhpMyanmarPayments\Results\PaymentStatusResult;
use Laranex\PhpMyanmarPayments\Support\Json;
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

    public readonly AyaPayConfig $config;

    private readonly Transport $transport;

    private readonly AyaPaySigner $signer;

    /**
     * @param  AyaPayConfig|array<string, mixed>  $config  A config object or an `AyaPayConfig::fromArray()` array.
     *
     * @throws ConfigurationException When a credential is missing.
     */
    public function __construct(
        AyaPayConfig|array $config,
        ?ClientInterface $httpClient = null,
    ) {
        $this->config = $config instanceof AyaPayConfig ? $config : AyaPayConfig::fromArray($config);
        $this->transport = new Transport($httpClient);
        $this->signer = new AyaPaySigner($this->config->appSecret);
    }

    /**
     * A gateway configured from the `AYA_PAY_*` (or `AYA_PGW_*`) environment variables.
     *
     * @param  array<array-key, mixed>|null  $env  Variables to read; defaults to `getenv()` merged with `$_ENV`.
     *
     * @throws ConfigurationException When a credential is missing.
     */
    public static function fromEnv(?array $env = null, ?ClientInterface $httpClient = null): self
    {
        return new self(AyaPayConfig::fromEnv($env), $httpClient);
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
            if (! is_array($service) || ! is_scalar($service['key'] ?? null) || (string) $service['key'] === '') {
                continue;
            }

            $methods = array_map(Json::scalarString(...), array_values(array_filter((array) ($service['methods'] ?? []), is_scalar(...))));

            $name = Json::scalarString($service['name'] ?? null);

            $services[] = new AyaPayService(
                name: $name !== '' ? $name : (string) $service['key'],
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

        $reportedOrderId = Json::scalarString($payload['merchOrderId'] ?? null);

        return new PaymentStatusResult(
            orderId: $reportedOrderId !== '' ? $reportedOrderId : $orderId,
            status: PaymentStatus::resolve(self::STATUSES, $statusCode),
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
        return $this->toCallback($this->verifiedPayload($request->queryInput(), 'redirect'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function toCallback(array $payload): PaymentCallback
    {
        $statusCode = trim((string) ($payload['statusCode'] ?? ''));

        return new PaymentCallback(
            orderId: (string) ($payload['merchOrderId'] ?? ''),
            status: PaymentStatus::resolve(self::STATUSES, $statusCode),
            gatewayStatus: $statusCode,
            gatewayReference: isset($payload['tranId']) ? (string) $payload['tranId'] : null,
            amount: isset($payload['amount']) ? (string) $payload['amount'] : null,
            raw: $payload,
        );
    }

    /**
     * A `+` in the base64 payload that arrived as a space (an unencoded query string) is read back as `+`;
     * the checksum is still verified.
     *
     * @param  array<string, mixed>  $input  Holds `payload` (base64 JSON) and `checkSum`.
     * @return array<string, mixed>
     */
    private function verifiedPayload(array $input, string $context): array
    {
        $payload = is_string($input['payload'] ?? null) && is_string($input['checkSum'] ?? null)
            ? $this->signer->verifyPayload(str_replace(' ', '+', $input['payload']), $input['checkSum'])
            : null;

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
                "AYA Pay {$endpoint} failed".($status !== null && $status !== '' ? rtrim(": [{$status}] {$message}") : " with HTTP {$response->status}."),
                gatewayCode: $status,
                gatewayMessage: $message,
                httpStatus: $response->status,
                raw: $body,
            );
        }

        return $body;
    }
}
