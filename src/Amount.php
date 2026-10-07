<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments;

use Laranex\PhpMyanmarPayments\Exceptions\InvalidPaymentDataException;
use Stringable;

/**
 * An exact, non-negative payment amount. Floats are never accepted, so no amount is ever rounded.
 *
 * Use `Amount::kyat(1000)` for whole units and `Amount::parse('1000.50')` for decimals. Whether a
 * gateway accepts decimals is decided by that gateway's payment data, following its official docs.
 */
final class Amount implements Stringable
{
    private function __construct(private readonly string $value) {}

    /**
     * A whole amount, e.g. `Amount::kyat(1000)`. Works for whole units of any currency.
     *
     * @throws InvalidPaymentDataException
     */
    public static function kyat(int $amount): self
    {
        if ($amount < 0) {
            throw new InvalidPaymentDataException(['amount' => 'The amount field must not be negative.']);
        }

        return new self((string) $amount);
    }

    /**
     * A decimal amount written as plain digits, e.g. `Amount::parse('1000.50')`.
     *
     * Signs, exponents, spaces and thousands separators are rejected.
     *
     * @throws InvalidPaymentDataException
     */
    public static function parse(string $amount): self
    {
        if (preg_match('/^\d+(\.\d+)?$/', $amount) !== 1) {
            throw new InvalidPaymentDataException(['amount' => "The amount field must be plain digits with an optional decimal part, e.g. 1000 or 1000.50; got [{$amount}]."]);
        }

        return new self($amount);
    }

    /**
     * @throws InvalidPaymentDataException
     */
    public static function from(self|int $amount): self
    {
        return $amount instanceof self ? $amount : self::kyat($amount);
    }

    /**
     * The amount exactly as given, e.g. `"1000.50"`.
     */
    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function decimalPlaces(): int
    {
        $dot = strpos($this->value, '.');

        return $dot === false ? 0 : strlen($this->value) - $dot - 1;
    }

    /**
     * The digits before the decimal point, without leading zeros, e.g. `"1000"` for `"1000.50"`.
     */
    public function wholePart(): string
    {
        $whole = explode('.', $this->value)[0];

        return ltrim($whole, '0') === '' ? '0' : ltrim($whole, '0');
    }

    public function isZero(): bool
    {
        return trim(str_replace('.', '', $this->value), '0') === '';
    }

    public function isPositive(): bool
    {
        return ! $this->isZero();
    }
}
