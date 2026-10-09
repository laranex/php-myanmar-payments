<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\KbzPay;

use Laranex\PhpMyanmarPayments\Support\Json;

/**
 * KBZ Pay's signature: every non-empty scalar field except `sign` and `sign_type`, sorted by key,
 * joined as raw `key=value` pairs (not URL-encoded), with `&key=<app key>` appended, hashed with SHA256 and
 * uppercased. Numbers sign as the exact text sent and booleans as `true` / `false`.
 */
final class KbzPaySigner
{
    public function __construct(private readonly string $appKey) {}

    /**
     * @param  array<string, mixed>  $fields
     */
    public function sign(array $fields): string
    {
        return strtoupper(hash('sha256', $this->signString($fields).'&key='.$this->appKey));
    }

    /**
     * Nested values are never signed, so a payload carrying one is rejected rather than partly trusted.
     *
     * @param  array<string, mixed>  $fields
     */
    public function verify(array $fields): bool
    {
        foreach ($fields as $value) {
            if (Json::isNested($value)) {
                return false;
            }
        }

        return is_string($fields['sign'] ?? null) && hash_equals($this->sign($fields), strtoupper($fields['sign']));
    }

    /**
     * The sorted `key=value` string, without the app key.
     *
     * @param  array<string, mixed>  $fields
     */
    public function signString(array $fields): string
    {
        unset($fields['sign'], $fields['sign_type']);

        $pairs = [];

        foreach ($fields as $key => $value) {
            if (is_scalar($value) && Json::scalarString($value) !== '') {
                $pairs[(string) $key] = Json::scalarString($value);
            }
        }

        ksort($pairs, SORT_STRING);

        return implode('&', array_map(fn (string $key, string $value): string => "{$key}={$value}", array_keys($pairs), $pairs));
    }
}
