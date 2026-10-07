<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Support;

use Laranex\PhpMyanmarPayments\Enums\PaymentStatus;

/**
 * @internal
 */
final class StatusMap
{
    /**
     * @param  array<string, PaymentStatus>  $map  Gateway status => package status.
     */
    public static function resolve(array $map, ?string $gatewayStatus): PaymentStatus
    {
        return $map[trim((string) $gatewayStatus)] ?? PaymentStatus::Unknown;
    }
}
