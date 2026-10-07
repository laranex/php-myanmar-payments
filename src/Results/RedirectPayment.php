<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Results;

use Laranex\PhpMyanmarPayments\Enums\PaymentFlow;

/**
 * Send the customer's browser to `$url` to complete the payment.
 */
final class RedirectPayment implements PaymentResult
{
    /**
     * @param  string  $orderId  Your order id, as sent to the gateway.
     * @param  string  $url  The gateway page to redirect the customer to.
     * @param  string|null  $gatewayReference  The gateway's id for this payment attempt (Wave `transaction_id`, KBZ `prepay_id`).
     * @param  array<array-key, mixed>  $raw  The gateway's response, for logging.
     */
    public function __construct(
        public readonly string $orderId,
        public readonly string $url,
        public readonly ?string $gatewayReference = null,
        public readonly array $raw = [],
    ) {}

    public function flow(): PaymentFlow
    {
        return PaymentFlow::Redirect;
    }
}
