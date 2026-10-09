<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Support;

/**
 * Decodes gateway JSON without losing number precision: every number keeps its exact text as a string,
 * so `1000.50` stays `"1000.50"` and signatures are checked against what the gateway actually sent.
 *
 * @internal
 */
final class Json
{
    /**
     * @return array<array-key, mixed>|null Null when the text is not a JSON object or array.
     */
    public static function decode(string $json): ?array
    {
        if (! is_array(json_decode($json, true))) {
            return null;
        }

        $decoded = json_decode(self::quoteNumbers($json), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * A scalar as the string a gateway signs: booleans become `true` / `false`, null becomes an empty string.
     */
    public static function scalarString(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Wrap every number literal outside a string in quotes. The input is already known to be valid JSON.
     */
    private static function quoteNumbers(string $json): string
    {
        $out = '';
        $length = strlen($json);
        $inString = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];

            if ($inString) {
                $out .= $char;

                if ($char === '\\') {
                    $out .= $json[++$i];
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
                $out .= $char;

                continue;
            }

            if ($char === '-' || ($char >= '0' && $char <= '9')) {
                $end = $i + strspn($json, '0123456789+-.eE', $i);
                $out .= '"'.substr($json, $i, $end - $i).'"';
                $i = $end - 1;

                continue;
            }

            $out .= $char;
        }

        return $out;
    }
}
