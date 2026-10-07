<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Results;

use Laranex\PhpMyanmarPayments\Enums\PaymentStatus;

/**
 * The state of a payment as reported by the gateway's status API.
 */
final class PaymentStatusResult
{
    /**
     * @param  string|null  $orderId  Your order id, when the gateway returns it.
     * @param  PaymentStatus  $status  The status mapped onto this package's statuses.
     * @param  string  $gatewayStatus  The gateway's own status value, unmapped.
     * @param  string|null  $gatewayReference  The gateway's id for the payment.
     * @param  string|null  $amount  The amount the gateway reports, as it sent it.
     * @param  array<array-key, mixed>  $raw  The gateway's response, for logging.
     */
    public function __construct(
        public readonly ?string $orderId,
        public readonly PaymentStatus $status,
        public readonly string $gatewayStatus,
        public readonly ?string $gatewayReference = null,
        public readonly ?string $amount = null,
        public readonly array $raw = [],
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === PaymentStatus::Successful;
    }
}
