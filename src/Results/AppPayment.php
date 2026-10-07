<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Results;

use Laranex\PhpMyanmarPayments\Enums\PaymentFlow;

/**
 * Pass these values to your mobile app, which hands them to the wallet's SDK to open the payment screen.
 */
final class AppPayment implements PaymentResult
{
    /**
     * @param  string  $orderId  Your order id, as sent to the gateway.
     * @param  string  $orderInfo  The signed order string the SDK expects.
     * @param  string  $sign  The signature of `$orderInfo`.
     * @param  string  $signType  The signature algorithm, e.g. `SHA256`.
     * @param  array<array-key, mixed>  $raw  The gateway's response, for logging.
     */
    public function __construct(
        public readonly string $orderId,
        public readonly string $orderInfo,
        public readonly string $sign,
        public readonly string $signType,
        public readonly array $raw = [],
    ) {}

    public function flow(): PaymentFlow
    {
        return PaymentFlow::App;
    }

    /**
     * @return array{orderId: string, orderInfo: string, sign: string, signType: string}
     */
    public function toArray(): array
    {
        return [
            'orderId' => $this->orderId,
            'orderInfo' => $this->orderInfo,
            'sign' => $this->sign,
            'signType' => $this->signType,
        ];
    }
}
