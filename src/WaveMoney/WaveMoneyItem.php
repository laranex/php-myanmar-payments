<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\WaveMoney;

use Laranex\PhpMyanmarPayments\Amount;
use Laranex\PhpMyanmarPayments\Exceptions\InvalidPaymentDataException;

/**
 * A line item shown on Wave's payment page. `WaveMoneyPaymentData` validates it (`items.<i>.name`, `items.<i>.amount`).
 */
final class WaveMoneyItem
{
    /**
     * Item amount in whole kyat.
     */
    public readonly Amount $amount;

    /**
     * @param  string  $name  Item name.
     * @param  Amount|int  $amount  Item amount in whole kyat, e.g. `Amount::kyat(1000)`. Wave does not accept decimals.
     *
     * @throws InvalidPaymentDataException When the amount is a negative integer.
     */
    public function __construct(
        public readonly string $name,
        Amount|int $amount,
    ) {
        $this->amount = Amount::from($amount);
    }

    /**
     * @return array{name: string, amount: int}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'amount' => (int) $this->amount->wholePart()];
    }
}
