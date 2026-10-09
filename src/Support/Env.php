<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Support;

/**
 * Reads gateway settings from environment variables, with the same names in every Laranex SDK.
 *
 * @internal
 */
final class Env
{
    /**
     * @var array<array-key, mixed>
     */
    private readonly array $env;

    /**
     * @param  array<array-key, mixed>|null  $env  Variables to read; defaults to `getenv()` merged with `$_ENV`.
     */
    public function __construct(?array $env = null)
    {
        $this->env = $env ?? (getenv() + $_ENV);
    }

    /**
     * The first non-empty value among the keys, trimmed, or null.
     */
    public function first(string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->env[$key] ?? null;

            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }
}
