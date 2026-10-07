<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\KbzPay;

use Laranex\PhpMyanmarPayments\Support\Validator;

/**
 * A KBZ Pay order. Used for the PWA, QR and in-app flows alike.
 */
final class KbzPayPaymentData
{
    /**
     * @param  string  $orderId  Your unique order id (`merch_order_id`). Letters, digits and `_` only, at most 40.
     * @param  int|string  $amount  Amount in kyat. Up to 2 decimal places, as a string, e.g. `'1000.50'`.
     * @param  string  $callbackUrl  Public URL KBZ posts the payment result to (`notify_url`). No query string.
     * @param  string|null  $title  Product name shown to the customer.
     * @param  int|null  $timeoutMinutes  Minutes before the order expires, 1 to 120. KBZ defaults to 120.
     * @param  string|null  $callbackInfo  Free text KBZ sends back unchanged in the callback.
     */
    public function __construct(
        public readonly string $orderId,
        public readonly int|string $amount,
        public readonly string $callbackUrl,
        public readonly ?string $title = null,
        public readonly ?int $timeoutMinutes = null,
        public readonly ?string $callbackInfo = null,
    ) {
        (new Validator)
            ->required('orderId', $orderId)
            ->max('orderId', $orderId, 40)
            ->pattern('orderId', $orderId, '/^[A-Za-z0-9_]+$/', 'letters, numbers and underscores')
            ->decimal('amount', $amount, maxDecimals: 2)
            ->required('callbackUrl', $callbackUrl)
            ->url('callbackUrl', $callbackUrl)
            ->max('callbackUrl', $callbackUrl, 512)
            ->when(str_contains($callbackUrl, '?'), 'callbackUrl', 'The callbackUrl field must not contain a query string.')
            ->between('timeoutMinutes', $timeoutMinutes, 1, 120)
            ->max('callbackInfo', $callbackInfo === null ? null : urlencode($callbackInfo), 512)
            ->validate();
    }
}
