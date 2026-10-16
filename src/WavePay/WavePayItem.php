<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\WavePay;

use Laranex\PhpMyanmarPayments\Amount;

/**
 * A line item shown on Wave's payment page. `WavePayPaymentData` validates it (`items.<i>.name`, `items.<i>.amount`).
 */
final class WavePayItem
{
    /**
     * Item amount in whole kyat.
     */
    public readonly Amount $amount;

    /**
     * Whether the amount was given as a negative integer. `WavePayPaymentData` reports it as `items.<i>.amount`.
     *
     * @internal
     */
    public readonly bool $negativeAmount;

    /**
     * @param  string  $name  Item name.
     * @param  Amount|int  $amount  Item amount in whole kyat, e.g. `Amount::kyat(1000)`. Wave does not accept decimals.
     *                              A negative integer is reported by `WavePayPaymentData` with its other errors.
     */
    public function __construct(
        public readonly string $name,
        Amount|int $amount,
    ) {
        $this->negativeAmount = is_int($amount) && $amount < 0;
        $this->amount = $this->negativeAmount ? Amount::kyat(0) : Amount::from($amount);
    }

    /**
     * @return array{name: string, amount: int}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'amount' => (int) $this->amount->wholePart()];
    }

    /**
     * The item as Wave's JSON object, with the amount's exact digits (no integer limit).
     *
     * @internal
     */
    public function toJson(): string
    {
        return '{"name":'.json_encode($this->name, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE).',"amount":'.$this->amount->wholePart().'}';
    }
}
