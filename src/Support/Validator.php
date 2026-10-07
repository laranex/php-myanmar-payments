<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Support;

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

    public function positive(string $field, int $value): self
    {
        if ($value <= 0) {
            $this->fail($field, "The {$field} field must be greater than 0.");
        }

        return $this;
    }

    /**
     * A non-negative amount given as an int or a decimal string such as "1000.50".
     */
    public function decimal(string $field, int|string $value, int $maxDecimals, ?int $maxLength = null, bool $allowZero = false): self
    {
        $string = (string) $value;
        $pattern = $maxDecimals > 0 ? '/^\d+(\.\d{1,'.$maxDecimals.'})?$/' : '/^\d+$/';

        if (preg_match($pattern, $string) !== 1) {
            $this->fail($field, $maxDecimals > 0
                ? "The {$field} field must be a number with at most {$maxDecimals} decimal places."
                : "The {$field} field must be a whole number.");

            return $this;
        }

        if (! $allowZero && (float) $string <= 0) {
            $this->fail($field, "The {$field} field must be greater than 0.");
        }

        if ($maxLength !== null && strlen($string) > $maxLength) {
            $this->fail($field, "The {$field} field must not be greater than {$maxLength} characters.");
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
