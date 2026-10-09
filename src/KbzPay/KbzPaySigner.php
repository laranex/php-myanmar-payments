<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\KbzPay;

/**
 * KBZ Pay's signature: every non-empty scalar field except `sign` and `sign_type`, sorted by key,
 * joined as raw `key=value` pairs, with `&key=<app key>` appended, hashed with SHA256 and uppercased.
 *
 * @internal
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
            if ($value !== null && ! is_scalar($value)) {
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

        $fields = array_filter($fields, fn (mixed $value): bool => is_scalar($value) && (string) $value !== '');

        ksort($fields, SORT_STRING);

        return implode('&', array_map(
            fn (string $key, mixed $value): string => $key.'='.(is_bool($value) ? ($value ? 'true' : 'false') : $value),
            array_keys($fields),
            $fields,
        ));
    }
}
