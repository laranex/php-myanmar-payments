<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\KbzPay;

use Laranex\PhpMyanmarPayments\Amount;
use Laranex\PhpMyanmarPayments\Exceptions\InvalidPaymentDataException;
use Laranex\PhpMyanmarPayments\Support\Validator;

/**
 * A KBZ Pay order. Used for the PWA, QR and in-app flows alike.
 */
final class KbzPayPaymentData
{
    /**
     * Amount in kyat. Up to 2 decimal places (KBZ only accepts MMK).
     */
    public readonly Amount $amount;

    /**
     * @param  string  $orderId  Your unique order id (`merch_order_id`). Letters, digits and `_` only, at most 40.
     * @param  Amount|int  $amount  Amount in kyat, e.g. `Amount::kyat(1000)` or `Amount::parse('1000.50')`. Up to 2 decimal places.
     * @param  string  $callbackUrl  Public URL KBZ posts the payment result to (`notify_url`). No query string.
     * @param  string|null  $title  Product name shown to the customer.
     * @param  int|null  $timeoutMinutes  Minutes before the order expires, 1 to 120. KBZ defaults to 120.
     * @param  string|null  $callbackInfo  Free text KBZ sends back unchanged in the callback.
     */
    public function __construct(
        public readonly string $orderId,
        Amount|int $amount,
        public readonly string $callbackUrl,
        public readonly ?string $title = null,
        public readonly ?int $timeoutMinutes = null,
        public readonly ?string $callbackInfo = null,
    ) {
        $this->amount = Amount::from($amount);

        $this->validate();
    }

    /**
     * Check the data against the gateway's documented rules again. The constructor already does.
     *
     * @throws InvalidPaymentDataException
     */
    public function validate(): void
    {
        (new Validator)
            ->required('orderId', $this->orderId)
            ->max('orderId', $this->orderId, 40)
            ->pattern('orderId', $this->orderId, '/^[A-Za-z0-9_]+\z/', 'letters, numbers and underscores')
            ->amount('KBZ Pay', $this->amount, maxDecimals: 2)
            ->required('callbackUrl', $this->callbackUrl)
            ->url('callbackUrl', $this->callbackUrl)
            ->max('callbackUrl', $this->callbackUrl, 512)
            ->when(str_contains($this->callbackUrl, '?'), 'callbackUrl', 'The callbackUrl field must not contain a query string.')
            ->between('timeoutMinutes', $this->timeoutMinutes, 1, 120)
            ->max('callbackInfo', $this->callbackInfo === null ? null : urlencode($this->callbackInfo), 512)
            ->validate();
    }
}
