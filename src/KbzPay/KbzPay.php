<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\KbzPay;

use DateTimeImmutable;
use Laranex\PhpMyanmarPayments\Contracts\PaymentGateway;
use Laranex\PhpMyanmarPayments\Enums\PaymentStatus;
use Laranex\PhpMyanmarPayments\Exceptions\ApiException;
use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\Exceptions\SignatureVerificationException;
use Laranex\PhpMyanmarPayments\Http\Acknowledgement;
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;
use Laranex\PhpMyanmarPayments\Http\Transport;
use Laranex\PhpMyanmarPayments\Results\AppPayment;
use Laranex\PhpMyanmarPayments\Results\PaymentCallback;
use Laranex\PhpMyanmarPayments\Results\PaymentStatusResult;
use Laranex\PhpMyanmarPayments\Results\QrPayment;
use Laranex\PhpMyanmarPayments\Results\RedirectPayment;
use Laranex\PhpMyanmarPayments\Support\Form;
use Laranex\PhpMyanmarPayments\Support\Json;
use Psr\Http\Client\ClientInterface;

/**
 * KBZ Pay: PWA (redirect), QR and in-app payments, status queries and callbacks.
 */
class KbzPay implements PaymentGateway
{
    private const STATUSES = [
        'PAY_SUCCESS' => PaymentStatus::Successful,
        'WAIT_PAY' => PaymentStatus::Pending,
        'PAYING' => PaymentStatus::Pending,
        'PAY_FAILED' => PaymentStatus::Failed,
        'ORDER_CLOSED' => PaymentStatus::Canceled,
        'ORDER_EXPIRED' => PaymentStatus::Expired,
    ];

    public readonly KbzPayConfig $config;

    /**
     * KBZ Pay's request signer, for custom calls.
     */
    public readonly KbzPaySigner $signer;

    private readonly Transport $transport;

    /**
     * @param  KbzPayConfig|array<string, mixed>  $config  A config object or a `KbzPayConfig::fromArray()` array.
     *
     * @throws ConfigurationException When a credential is missing.
     */
    public function __construct(
        KbzPayConfig|array $config,
        ?ClientInterface $httpClient = null,
    ) {
        $this->config = $config instanceof KbzPayConfig ? $config : KbzPayConfig::fromArray($config);
        $this->transport = new Transport($httpClient, $this->config->timeoutSeconds);
        $this->signer = new KbzPaySigner($this->config->appKey);
    }

    /**
     * A gateway configured from the `KBZ_PAY_*` environment variables.
     *
     * @param  array<array-key, mixed>|null  $env  Variables to read; defaults to `getenv()` merged with `$_ENV`.
     *
     * @throws ConfigurationException When a credential is missing.
     */
    public static function fromEnv(?array $env = null, ?ClientInterface $httpClient = null): self
    {
        return new self(KbzPayConfig::fromEnv($env), $httpClient);
    }

    /**
     * Create an order and get the KBZ Pay PWA checkout URL to redirect the customer to.
     *
     * The redirect only works on a phone with the KBZ Pay app installed, and its Referer must match
     * the URL registered with KBZ.
     *
     * @throws ApiException
     */
    public function pwa(KbzPayPaymentData $data): RedirectPayment
    {
        $order = $this->precreate($data, 'PWAAPP');
        $orderInfo = $this->orderInfo($order['prepay_id'], $order['nonce_str'], $order['timestamp']);

        return new RedirectPayment(
            orderId: $data->orderId,
            url: $this->config->pwaUrl.'?'.$orderInfo.'&sign='.$this->signer->sign($this->orderInfoFields($order['prepay_id'], $order['nonce_str'], $order['timestamp'])),
            gatewayReference: $order['prepay_id'],
            raw: $order['response'],
        );
    }

    /**
     * Create an order and get a QR payload for the customer to scan with the KBZ Pay app.
     *
     * @throws ApiException
     */
    public function qr(KbzPayPaymentData $data): QrPayment
    {
        $order = $this->precreate($data, 'PAY_BY_QRCODE');
        $qrCode = Json::scalar($order['response']['qrCode'] ?? null) ?? '';

        if ($qrCode === '') {
            throw new ApiException('KBZ Pay did not return a QR code.', raw: $order['response']);
        }

        return new QrPayment(
            orderId: $data->orderId,
            qrString: $qrCode,
            expiresAt: new DateTimeImmutable("+{$order['time_to_live_minutes']} minutes"),
            reference: $order['prepay_id'],
            raw: $order['response'],
        );
    }

    /**
     * Create an order and get the signed values your mobile app passes to `KBZPay.startPay()`.
     *
     * The SDK's own result only means the payment screen closed. Rely on the callback or `status()`.
     *
     * @throws ApiException
     */
    public function app(KbzPayPaymentData $data): AppPayment
    {
        $order = $this->precreate($data, 'APP');
        $fields = $this->orderInfoFields($order['prepay_id'], $order['nonce_str'], $order['timestamp']);

        return new AppPayment(
            orderId: $data->orderId,
            orderInfo: $this->signer->signString($fields),
            sign: $this->signer->sign($fields),
            signType: 'SHA256',
            raw: $order['response'],
        );
    }

    /**
     * Ask KBZ Pay for the current state of an order. Use it when a callback is late or missing.
     *
     * @throws ApiException
     */
    public function status(string $orderId): PaymentStatusResult
    {
        $response = $this->call('queryorder', 'kbz.payment.queryorder', '3.0', [
            'appid' => $this->config->appId,
            'merch_code' => $this->config->merchantCode,
            'merch_order_id' => $orderId,
        ]);

        $tradeStatus = trim(Json::scalarString($response['trade_status'] ?? null));
        $reportedOrderId = Json::scalarString($response['merch_order_id'] ?? null);

        return new PaymentStatusResult(
            orderId: $reportedOrderId !== '' ? $reportedOrderId : $orderId,
            status: PaymentStatus::resolve(self::STATUSES, $tradeStatus),
            gatewayStatus: $tradeStatus,
            gatewayReference: Json::scalar($response['mm_order_id'] ?? null),
            amount: Json::scalar($response['total_amount'] ?? null),
            raw: $response,
        );
    }

    /**
     * Verify KBZ Pay's payment notification. Reply with `acknowledgement()` (plain `success`) or KBZ retries.
     *
     * @throws SignatureVerificationException
     */
    public function handleCallback(CallbackRequest $request): PaymentCallback
    {
        $input = $request->parsedBody();
        $fields = is_array($input['Request'] ?? null) ? $input['Request'] : $input;

        if (! $this->signer->verify($fields)) {
            throw new SignatureVerificationException('KBZ Pay callback signature verification failed.', $input);
        }

        $tradeStatus = trim(Json::scalarString($fields['trade_status'] ?? null));

        return new PaymentCallback(
            orderId: Json::scalarString($fields['merch_order_id'] ?? null),
            status: PaymentStatus::resolve(self::STATUSES, $tradeStatus),
            gatewayStatus: $tradeStatus,
            gatewayReference: Json::scalar($fields['mm_order_id'] ?? null),
            amount: Json::scalar($fields['total_amount'] ?? null),
            raw: $fields,
            acknowledgement: new Acknowledgement(200, 'success'),
        );
    }

    /**
     * @return array{prepay_id: string, nonce_str: string, timestamp: string, time_to_live_minutes: int, response: array<string, mixed>}
     */
    private function precreate(KbzPayPaymentData $data, string $tradeType): array
    {
        $timeToLiveMinutes = $data->timeToLiveMinutes ?? $this->config->timeToLiveMinutes;
        $biz = array_filter([
            'appid' => $this->config->appId,
            'merch_code' => $this->config->merchantCode,
            'merch_order_id' => $data->orderId,
            'trade_type' => $tradeType,
            'total_amount' => $data->amount->toString(),
            'trans_currency' => 'MMK',
            'title' => $data->title,
            'timeout_express' => $timeToLiveMinutes.'m',
            'callback_info' => $data->callbackInfo === null ? null : Form::escape($data->callbackInfo),
        ], fn (?string $value): bool => $value !== null && $value !== '');

        $nonce = $this->nonce();
        $timestamp = (string) time();
        $response = $this->call('precreate', 'kbz.payment.precreate', '1.0', $biz, [
            'notify_url' => $data->callbackUrl,
        ], $nonce, $timestamp);

        $prepayId = Json::scalar($response['prepay_id'] ?? null) ?? '';

        if ($prepayId === '') {
            throw new ApiException('KBZ Pay did not return a prepay_id.', raw: $response);
        }

        return ['prepay_id' => $prepayId, 'nonce_str' => $nonce, 'timestamp' => $timestamp, 'time_to_live_minutes' => $timeToLiveMinutes, 'response' => $response];
    }

    /**
     * Send a signed request and return the `Response` object, throwing when KBZ reports a failure.
     *
     * @param  array<string, string>  $biz
     * @param  array<string, string>  $common
     * @return array<string, mixed>
     */
    private function call(string $endpoint, string $method, string $version, array $biz, array $common = [], ?string $nonce = null, ?string $timestamp = null): array
    {
        $common = [
            'timestamp' => $timestamp ?? (string) time(),
            'method' => $method,
            'nonce_str' => $nonce ?? $this->nonce(),
            'version' => $version,
        ] + $common;

        $request = $common + [
            'sign_type' => 'SHA256',
            'sign' => $this->signer->sign($common + $biz),
            'biz_content' => $biz,
        ];

        $response = $this->transport->postJson("{$this->config->apiUrl}/{$endpoint}", ['Request' => $request]);
        $body = $response->json();
        $result = is_array($body['Response'] ?? null) ? $body['Response'] : [];

        $code = Json::scalar($result['code'] ?? null);

        if (! $response->successful() || Json::scalar($result['result'] ?? null) !== 'SUCCESS' || $code !== '0') {
            $message = Json::scalar($result['msg'] ?? null);

            throw new ApiException(
                "KBZ Pay {$endpoint} failed".($code !== null && $code !== '' ? rtrim(": [{$code}] {$message}") : " with HTTP {$response->status}."),
                gatewayCode: $code,
                gatewayMessage: $message,
                httpStatus: $response->status,
                raw: $body,
            );
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function orderInfoFields(string $prepayId, string $nonce, string $timestamp): array
    {
        return [
            'appid' => $this->config->appId,
            'merch_code' => $this->config->merchantCode,
            'nonce_str' => $nonce,
            'prepay_id' => $prepayId,
            'timestamp' => $timestamp,
        ];
    }

    private function orderInfo(string $prepayId, string $nonce, string $timestamp): string
    {
        return $this->signer->signString($this->orderInfoFields($prepayId, $nonce, $timestamp));
    }

    private function nonce(): string
    {
        return bin2hex(random_bytes(16));
    }
}
