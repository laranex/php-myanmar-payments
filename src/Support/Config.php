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
        $value = $this->config[$key] ?? null;

        if ($value === null || $value === '') {
            throw ConfigurationException::missing($this->gateway, $key);
        }

        return (string) $value;
    }

    public function string(string $key, ?string $default = null): ?string
    {
        $value = $this->config[$key] ?? null;

        return $value === null || $value === '' ? $default : (string) $value;
    }

    public function int(string $key, int $default): int
    {
        $value = $this->config[$key] ?? null;

        return $value === null || $value === '' ? $default : (int) $value;
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
