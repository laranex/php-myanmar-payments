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
    public function amount(string $gateway, Amount $amount, ?int $maxDecimals, bool $allowZero = false, ?int $maxLength = null, string $field = 'amount'): self
    {
        $places = $amount->decimalPlaces();

        if ($maxDecimals === 0 && $places > 0) {
            $this->fail($field, "{$gateway} does not accept decimal amounts; the {$field} field must be a whole number.");
        } elseif ($maxDecimals !== null && $places > $maxDecimals) {
            $this->fail($field, "{$gateway} accepts at most {$maxDecimals} decimal places; the {$field} field has {$places}.");
        } elseif (! $allowZero && $amount->isZero()) {
            $this->fail($field, "The {$field} field must be greater than 0.");
        } elseif ($maxLength !== null && strlen($amount->toString()) > $maxLength) {
            $this->fail($field, "The {$field} field must not be greater than {$maxLength} characters.");
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
        if ($value !== null && $value !== '' && (filter_var($value, FILTER_VALIDATE_URL) === false || ! in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true))) {
            $this->fail($field, "The {$field} field must be a valid http or https URL.");
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
