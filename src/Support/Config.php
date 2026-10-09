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
     * An integer or integer text such as `"300"`; anything else is the default.
     */
    public function int(string $key, int $default): int
    {
        $value = $this->config[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && preg_match('/^[+-]?[0-9]+\z/', trim($value)) === 1 ? (int) trim($value) : $default;
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
     * An optional override: blank means unset.
     */
    public static function optionalValue(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : $value;
    }

    /**
     * `true`/`1`/`t`/`yes`/`on` and `false`/`0`/`f`/`no`/`off` in any case; anything else is the default.
     */
    public function bool(string $key, bool $default): bool
    {
        $value = $this->config[$key] ?? null;

        if (is_bool($value)) {
            return $value;
        }

        if (! is_scalar($value)) {
            return $default;
        }

        return match (strtolower(trim((string) $value))) {
            'true', '1', 't', 'yes', 'on' => true,
            'false', '0', 'f', 'no', 'off' => false,
            default => $default,
        };
    }
}
