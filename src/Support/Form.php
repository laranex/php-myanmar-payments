<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Support;

/**
 * Decodes a urlencoded body like Go, Node and Python do: the first value of a repeated key wins,
 * names are kept exactly as sent (no `[]`, `.` or space mangling) and `+` is a space.
 *
 * @internal
 */
final class Form
{
    /**
     * @return array<string, string>
     */
    public static function decode(string $body): array
    {
        $form = [];

        foreach (explode('&', $body) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$name, $value] = explode('=', $pair, 2) + [1 => ''];
            $name = urldecode($name);

            if (! array_key_exists($name, $form)) {
                $form[$name] = urldecode($value);
            }
        }

        return $form;
    }
}
