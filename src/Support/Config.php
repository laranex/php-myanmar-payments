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
     * The largest whole number a setting may hold, the same in every Laranex SDK (2^31 - 1).
     */
    private const MAX_INTEGER = 2147483647;

    private const MIN_INTEGER = -2147483648;

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
     * The value as a string, or the default when it is missing, blank or neither a string nor an integer. An integer
     * becomes its decimal text (`200123` becomes `"200123"`); floats and booleans count as missing.
     */
    public function string(string $key, ?string $default = null): ?string
    {
        $value = $this->config[$key] ?? null;

        if (is_int($value)) {
            $value = (string) $value;
        }

        return is_string($value) && trim($value) !== '' ? $value : $default;
    }

    /**
     * A whole number from 1 to 2147483647, given as an integer or integer text such as `"300"`. A missing or blank
     * value is the default when one is given.
     *
     * @throws ConfigurationException When the value is missing or blank without a default, or not a whole number from 1 to 2147483647.
     */
    public function seconds(string $key, ?int $default = null): int
    {
        $value = $this->integer($key);

        if ($value === null) {
            return $default ?? throw ConfigurationException::missing($this->gateway, $key);
        }

        if ($value === false) {
            throw ConfigurationException::invalid($this->gateway, $key);
        }

        return self::requirePositive($this->gateway, $key, $value);
    }

    /**
     * A required whole number, given as an integer or integer text such as `"30"`.
     *
     * @throws ConfigurationException When the value is missing or blank, or not a whole number.
     */
    public function requiredInteger(string $key, string $requirement): int
    {
        return $this->optionalInteger($key, $requirement) ?? throw ConfigurationException::missing($this->gateway, $key);
    }

    /**
     * A whole number given as an integer or integer text, or null when it is missing or blank.
     *
     * @throws ConfigurationException When the value is set but not a whole number from -2147483648 to 2147483647, naming `$requirement`.
     */
    public function optionalInteger(string $key, string $requirement): ?int
    {
        $value = $this->integer($key);

        if ($value === false) {
            throw ConfigurationException::invalid($this->gateway, $key, $requirement);
        }

        return $value;
    }

    /**
     * The value as a 32-bit whole number, null when it is missing or blank, and false when it is anything else
     * (a float, a boolean, text that is not an integer, or a number outside -2147483648 to 2147483647).
     */
    private function integer(string $key): int|false|null
    {
        $value = $this->config[$key] ?? null;

        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if (is_string($value)) {
            $text = trim($value);

            if (preg_match('/^[+-]?[0-9]+\z/', $text) !== 1) {
                return false;
            }

            $digits = ltrim(ltrim($text, '+-'), '0');

            if (strlen($digits) > 10) {
                return false;
            }

            $value = (int) $text;
        }

        return is_int($value) && $value >= self::MIN_INTEGER && $value <= self::MAX_INTEGER ? $value : false;
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
     * A whole number of seconds the constructor was given: zero or less, or more than 2147483647, is invalid.
     */
    public static function requirePositive(string $gateway, string $key, int $value): int
    {
        if ($value <= 0 || $value > self::MAX_INTEGER) {
            throw ConfigurationException::invalid($gateway, $key);
        }

        return $value;
    }
}
