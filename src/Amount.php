<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments;

use JsonSerializable;
use Laranex\PhpMyanmarPayments\Exceptions\InvalidPaymentDataException;
use Laranex\PhpMyanmarPayments\Support\Json;
use Stringable;

/**
 * An exact, non-negative payment amount. Floats are never accepted, so no amount is ever rounded.
 *
 * Use `Amount::kyat(1000)` for whole units and `Amount::parse('1000.50')` for decimals. Whether a
 * gateway accepts decimals is decided by that gateway's payment data, following its official docs.
 */
final class Amount implements JsonSerializable, Stringable
{
    private const PATTERN = '/^[0-9]+(\.[0-9]+)?\z/';

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
     * Signs, exponents, spaces and thousands separators are rejected. Leading zeros of the whole part are
     * removed (`007.50` becomes `7.50`); the fractional digits are kept exactly as given.
     *
     * @throws InvalidPaymentDataException
     */
    public static function parse(string $amount): self
    {
        if (preg_match(self::PATTERN, $amount) !== 1) {
            throw new InvalidPaymentDataException(['amount' => 'The amount field must be a number such as 1000 or 1000.50, got '.Json::quote($amount).'.']);
        }

        [$whole, $fraction] = explode('.', $amount, 2) + [1 => null];
        $whole = ltrim($whole, '0') === '' ? '0' : ltrim($whole, '0');

        return new self($fraction === null ? $whole : "{$whole}.{$fraction}");
    }

    /**
     * @throws InvalidPaymentDataException
     */
    public static function from(self|int $amount): self
    {
        return $amount instanceof self ? $amount : self::kyat($amount);
    }

    /**
     * The amount as given, without leading zeros, e.g. `"1000.50"`.
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

    /**
     * Whether both amounts have the same value, e.g. to compare a callback's amount with your order.
     *
     * Leading zeros and trailing fractional zeros are ignored (`01000`, `1000` and `1000.00` are equal).
     * Text that is not plain digits with an optional decimal part, and null, are never equal.
     */
    public function equals(self|string|null $other): bool
    {
        if ($other === null) {
            return false;
        }

        $text = $other instanceof self ? $other->value : $other;

        return preg_match(self::PATTERN, $text) === 1 && self::normalize($text) === self::normalize($this->value);
    }

    /**
     * The amount as a JSON string, e.g. `"1000.50"`, so no JSON consumer reads it as a float.
     */
    public function jsonSerialize(): string
    {
        return $this->value;
    }

    private static function normalize(string $value): string
    {
        [$whole, $fraction] = explode('.', $value, 2) + [1 => ''];
        $whole = ltrim($whole, '0') === '' ? '0' : ltrim($whole, '0');
        $fraction = rtrim($fraction, '0');

        return $fraction === '' ? $whole : "{$whole}.{$fraction}";
    }
}
