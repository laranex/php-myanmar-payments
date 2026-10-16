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

    /**
     * A negative whole-number amount, kept by the payment data so it is reported with the other errors.
     */
    public function negative(string $field, bool $negative): self
    {
        return $this->when($negative, $field, "The {$field} field must not be negative.");
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
        if ($value !== null && $value !== '' && (filter_var($value, FILTER_VALIDATE_URL) === false || ! in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true) || ! self::validPort($value))) {
            $this->fail($field, "The {$field} field must be a valid http or https URL.");
        }

        return $this;
    }

    /**
     * Whether the URL's port, when it has one, is 1 to 5 digits up to 65535. FILTER_VALIDATE_URL also accepts an
     * empty port or text after the digits (`https://shop.test:8a/`), which the other SDKs reject.
     */
    private static function validPort(string $url): bool
    {
        $authority = (string) preg_replace('~^[^:]+://([^/?#]*).*$~s', '$1', $url);
        $host = substr($authority, (int) strrpos('@'.$authority, '@'));
        $port = str_starts_with($host, '[') ? substr($host, (int) strpos($host, ']') + 1) : (string) strstr($host, ':');

        return $port === '' || (preg_match('/^:[0-9]{1,5}\z/', $port) === 1 && (int) substr($port, 1) <= 65535);
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
