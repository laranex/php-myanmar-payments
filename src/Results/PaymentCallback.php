<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Results;

use Laranex\PhpMyanmarPayments\Enums\PaymentStatus;
use Laranex\PhpMyanmarPayments\Http\Acknowledgement;

/**
 * A gateway notification whose signature has been verified.
 *
 * Always compare `$amount` with your order before fulfilling it, and handle duplicates: gateways retry.
 */
final class PaymentCallback
{
    /**
     * @param  string  $orderId  Your order id.
     * @param  PaymentStatus  $status  The status mapped onto this package's statuses.
     * @param  string  $gatewayStatus  The gateway's own status value, unmapped.
     * @param  string|null  $gatewayReference  The gateway's id for the payment.
     * @param  string|null  $amount  The amount the gateway reports, as it sent it.
     * @param  array<array-key, mixed>  $raw  The verified payload.
     * @param  Acknowledgement  $acknowledgement  The response to send back so the gateway stops retrying.
     */
    public function __construct(
        public readonly string $orderId,
        public readonly PaymentStatus $status,
        public readonly string $gatewayStatus,
        public readonly ?string $gatewayReference = null,
        public readonly ?string $amount = null,
        public readonly array $raw = [],
        public readonly Acknowledgement $acknowledgement = new Acknowledgement,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === PaymentStatus::Successful;
    }
}
