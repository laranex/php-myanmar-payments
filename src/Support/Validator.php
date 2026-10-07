<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Support;

use Laranex\PhpMyanmarPayments\Amount;
use Laranex\PhpMyanmarPayments\Exceptions\InvalidPaymentDataException;

/**
 * Collects validation errors for a payment data object and throws them together.
 *
 * @internal
 */
final class Validator
{
    /**
     * @var array<string, string>
     */
    private array $errors = [];

    public function required(string $field, ?string $value): self
    {
        if ($value === null || trim($value) === '') {
            $this->fail($field, "The {$field} field is required.");
        }

        return $this;
    }

    public function length(string $field, ?string $value, int $min, int $max): self
    {
        if ($value !== null && $value !== '' && (strlen($value) < $min || strlen($value) > $max)) {
            $this->fail($field, $min === $max
                ? "The {$field} field must be {$max} characters."
                : "The {$field} field must be between {$min} and {$max} characters.");
        }

        return $this;
    }

    public function max(string $field, ?string $value, int $max): self
    {
        if ($value !== null && strlen($value) > $max) {
            $this->fail($field, "The {$field} field must not be greater than {$max} characters.");
        }

        return $this;
    }

    public function pattern(string $field, ?string $value, string $pattern, string $description): self
    {
        if ($value !== null && $value !== '' && preg_match($pattern, $value) !== 1) {
            $this->fail($field, "The {$field} field may only contain {$description}.");
        }

        return $this;
    }

    /**
     * Gateway amount rules, taken from each gateway's docs.
     *
     * @param  int|null  $maxDecimals  Decimal places the gateway accepts; 0 means whole amounts only, null means any.
     */
    public function amount(string $gateway, Amount $amount, ?int $maxDecimals, bool $allowZero = false, ?int $maxLength = null): self
    {
        if ($maxDecimals === 0 && $amount->decimalPlaces() > 0) {
            $this->fail('amount', "{$gateway} does not accept decimal amounts.");

            return $this;
        }

        if ($maxDecimals !== null && $amount->decimalPlaces() > $maxDecimals) {
            $this->fail('amount', "{$gateway} accepts at most {$maxDecimals} decimal places.");

            return $this;
        }

        if (! $allowZero && $amount->isZero()) {
            $this->fail('amount', 'The amount field must be greater than 0.');
        }

        if ($maxLength !== null && strlen($amount->toString()) > $maxLength) {
            $this->fail('amount', "The amount field must not be greater than {$maxLength} characters.");
        }

        return $this;
    }

    public function https(string $field, ?string $value): self
    {
        if ($value !== null && $value !== '' && ! str_starts_with(strtolower($value), 'https://')) {
            $this->fail($field, "The {$field} field must be an HTTPS URL.");
        }

        return $this;
    }

    public function between(string $field, ?int $value, int $min, int $max): self
    {
        if ($value !== null && ($value < $min || $value > $max)) {
            $this->fail($field, "The {$field} field must be between {$min} and {$max}.");
        }

        return $this;
    }

    public function url(string $field, ?string $value): self
    {
        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_URL) === false) {
            $this->fail($field, "The {$field} field must be a valid URL.");
        }

        return $this;
    }

    public function when(bool $condition, string $field, string $message): self
    {
        if ($condition) {
            $this->fail($field, $message);
        }

        return $this;
    }

    /**
     * @throws InvalidPaymentDataException
     */
    public function validate(): void
    {
        if ($this->errors !== []) {
            throw new InvalidPaymentDataException($this->errors);
        }
    }

    private function fail(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }
}
