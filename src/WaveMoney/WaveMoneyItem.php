<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\WaveMoney;

use Laranex\PhpMyanmarPayments\Support\Validator;

/**
 * A line item shown on Wave's payment page.
 */
final class WaveMoneyItem
{
    /**
     * @param  string  $name  Item name.
     * @param  int  $amount  Item amount in whole kyat.
     */
    public function __construct(
        public readonly string $name,
        public readonly int $amount,
    ) {
        (new Validator)
            ->required('name', $name)
            ->positive('amount', $amount)
            ->validate();
    }

    /**
     * @return array{name: string, amount: int}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'amount' => $this->amount];
    }
}
