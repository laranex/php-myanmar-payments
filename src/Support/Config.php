<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Support;

use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;

/**
 * Reads values out of a snake_case configuration array.
 *
 * @internal
 */
final class Config
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly string $gateway, private readonly array $config) {}

    public function required(string $key): string
    {
        $value = $this->string($key);

        if ($value === null) {
            throw ConfigurationException::missing($this->gateway, $key);
        }

        return $value;
    }

    /**
     * The value as a string, or the default when it is missing, blank or not a scalar.
     */
    public function string(string $key, ?string $default = null): ?string
    {
        $value = $this->config[$key] ?? null;

        return is_scalar($value) && ! is_bool($value) && trim((string) $value) !== '' ? (string) $value : $default;
    }

    /**
     * A whole number greater than 0, given as an integer or integer text such as `"300"`.
     *
     * @throws ConfigurationException When the value is missing, blank or not a whole number greater than 0.
     */
    public function seconds(string $key): int
    {
        $value = $this->config[$key] ?? null;

        if ($value === null || (is_string($value) && trim($value) === '')) {
            throw ConfigurationException::missing($this->gateway, $key);
        }

        if (is_string($value) && preg_match('/^[+-]?[0-9]+\z/', trim($value)) === 1) {
            $value = (int) trim($value);
        }

        if (! is_int($value) || $value <= 0) {
            throw ConfigurationException::invalid($this->gateway, $key);
        }

        return $value;
    }

    /**
     * A credential the constructor was given: blank after trimming means missing.
     */
    public static function requireValue(string $gateway, string $key, string $value): string
    {
        if (trim($value) === '') {
            throw ConfigurationException::missing($gateway, $key);
        }

        return $value;
    }

    /**
     * A whole number of seconds the constructor was given: zero or less is invalid.
     */
    public static function requirePositive(string $gateway, string $key, int $value): int
    {
        if ($value <= 0) {
            throw ConfigurationException::invalid($gateway, $key);
        }

        return $value;
    }

    /**
     * An optional override: blank means unset.
     */
    public static function optionalValue(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : $value;
    }
}
