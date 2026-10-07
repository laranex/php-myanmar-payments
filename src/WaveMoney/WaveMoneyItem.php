<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\WaveMoney;

use Laranex\PhpMyanmarPayments\Amount;
use Laranex\PhpMyanmarPayments\Support\Validator;

/**
 * A line item shown on Wave's payment page.
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
     */
    public function __construct(
        public readonly string $name,
        Amount|int $amount,
    ) {
        $this->amount = Amount::from($amount);

        (new Validator)
            ->required('name', $name)
            ->amount('Wave Money', $this->amount, maxDecimals: 0)
            ->validate();
    }

    /**
     * @return array{name: string, amount: int}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'amount' => (int) $this->amount->wholePart()];
    }
}
