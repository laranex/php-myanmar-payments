<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Enums;

/**
 * Gateway-independent payment status. Every gateway's own status values are mapped onto these.
 */
enum PaymentStatus: string
{
    /** The customer paid. This is the only status that means money was collected. */
    case Successful = 'successful';

    /** The payment is still in progress or waiting on the customer. */
    case Pending = 'pending';

    /** The payment was attempted and failed or was rejected. */
    case Failed = 'failed';

    /** The payment or order was canceled or closed before completing. */
    case Canceled = 'canceled';

    /** The payment window ran out before the customer paid. */
    case Expired = 'expired';

    /** The gateway sent a status this package does not recognize. Inspect `gatewayStatus`. */
    case Unknown = 'unknown';

    public function isFinal(): bool
    {
        return ! in_array($this, [self::Pending, self::Unknown], true);
    }
}
