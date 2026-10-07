<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\KbzPay;

use DateTimeImmutable;
use Laranex\PhpMyanmarPayments\Contracts\PaymentGateway;
use Laranex\PhpMyanmarPayments\Enums\PaymentStatus;
use Laranex\PhpMyanmarPayments\Exceptions\ApiException;
use Laranex\PhpMyanmarPayments\Exceptions\SignatureVerificationException;
use Laranex\PhpMyanmarPayments\Http\Acknowledgement;
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;
use Laranex\PhpMyanmarPayments\Http\Transport;
use Laranex\PhpMyanmarPayments\Results\AppPayment;
use Laranex\PhpMyanmarPayments\Results\PaymentCallback;
use Laranex\PhpMyanmarPayments\Results\PaymentStatusResult;
use Laranex\PhpMyanmarPayments\Results\QrPayment;
use Laranex\PhpMyanmarPayments\Results\RedirectPayment;
use Laranex\PhpMyanmarPayments\Support\StatusMap;
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
        'ORDER_CLOSED' => PaymentStatus::Cancelled,
        'ORDER_EXPIRED' => PaymentStatus::Expired,
    ];

    private readonly Transport $transport;

    private readonly KbzPaySigner $signer;

    public function __construct(
        public readonly KbzPayConfig $config,
        ?ClientInterface $httpClient = null,
    ) {
        $this->transport = new Transport($httpClient);
        $this->signer = new KbzPaySigner($config->appKey);
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
        $qrCode = $order['response']['qrCode'] ?? null;

        if (! is_string($qrCode) || $qrCode === '') {
            throw new ApiException('KBZ Pay did not return a QR code.', raw: $order['response']);
        }

        return new QrPayment(
            orderId: $data->orderId,
            qrString: $qrCode,
            expiresAt: $data->timeoutMinutes === null ? null : new DateTimeImmutable("+{$data->timeoutMinutes} minutes"),
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

        $tradeStatus = trim((string) ($response['trade_status'] ?? ''));

        return new PaymentStatusResult(
            orderId: (string) ($response['merch_order_id'] ?? $orderId),
            status: StatusMap::resolve(self::STATUSES, $tradeStatus),
            gatewayStatus: $tradeStatus,
            gatewayReference: isset($response['mm_order_id']) ? (string) $response['mm_order_id'] : null,
            amount: isset($response['total_amount']) ? (string) $response['total_amount'] : null,
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

        $tradeStatus = trim((string) ($fields['trade_status'] ?? ''));

        return new PaymentCallback(
            orderId: (string) ($fields['merch_order_id'] ?? ''),
            status: StatusMap::resolve(self::STATUSES, $tradeStatus),
            gatewayStatus: $tradeStatus,
            gatewayReference: isset($fields['mm_order_id']) ? (string) $fields['mm_order_id'] : null,
            amount: isset($fields['total_amount']) ? (string) $fields['total_amount'] : null,
            raw: $fields,
            acknowledgement: new Acknowledgement(200, 'success'),
        );
    }

    /**
     * @return array{prepay_id: string, nonce_str: string, timestamp: string, response: array<string, mixed>}
     */
    private function precreate(KbzPayPaymentData $data, string $tradeType): array
    {
        $biz = array_filter([
            'appid' => $this->config->appId,
            'merch_code' => $this->config->merchantCode,
            'merch_order_id' => $data->orderId,
            'trade_type' => $tradeType,
            'total_amount' => $data->amount->toString(),
            'trans_currency' => 'MMK',
            'title' => $data->title,
            'timeout_express' => $data->timeoutMinutes === null ? null : $data->timeoutMinutes.'m',
            'callback_info' => $data->callbackInfo === null ? null : urlencode($data->callbackInfo),
        ], fn (?string $value): bool => $value !== null && $value !== '');

        $nonce = $this->nonce();
        $timestamp = (string) time();
        $response = $this->call('precreate', 'kbz.payment.precreate', '1.0', $biz, [
            'notify_url' => $data->callbackUrl,
        ], $nonce, $timestamp);

        if (! is_string($response['prepay_id'] ?? null) || $response['prepay_id'] === '') {
            throw new ApiException('KBZ Pay did not return a prepay_id.', raw: $response);
        }

        return ['prepay_id' => $response['prepay_id'], 'nonce_str' => $nonce, 'timestamp' => $timestamp, 'response' => $response];
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

        if (! $response->successful() || ($result['result'] ?? null) !== 'SUCCESS' || (string) ($result['code'] ?? '') !== '0') {
            $code = isset($result['code']) ? (string) $result['code'] : null;
            $message = isset($result['msg']) ? (string) $result['msg'] : null;

            throw new ApiException(
                "KBZ Pay {$endpoint} failed".($code ? ": [{$code}] {$message}" : " with HTTP {$response->status}."),
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
