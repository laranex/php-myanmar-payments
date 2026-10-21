<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\WavePayMiniApp;

use Laranex\PhpMyanmarPayments\Amount;

/**
 * A line item of a mini app payment (`items` in `makePayment`). `WavePayMiniAppPaymentData` validates it
 * (`items.<i>.name`, `items.<i>.amount`).
 */
final class WavePayMiniAppItem
{
    /**
     * Item amount in whole kyat.
     */
    public readonly Amount $amount;

    /**
     * Whether the amount was given as a negative integer. `WavePayMiniAppPaymentData` reports it as `items.<i>.amount`.
     *
     * @internal
     */
    public readonly bool $negativeAmount;

    /**
     * @param  string  $name  Product name.
     * @param  Amount|int  $amount  Product price in whole kyat, e.g. `Amount::kyat(1000)`. Wave takes an integer.
     *                              A negative integer is reported by `WavePayMiniAppPaymentData` with its other errors.
     */
    public function __construct(
        public readonly string $name,
        Amount|int $amount,
    ) {
        $this->negativeAmount = is_int($amount) && $amount < 0;
        $this->amount = $this->negativeAmount ? Amount::kyat(0) : Amount::from($amount);
    }
}
