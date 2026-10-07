<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\AyaPay;

/**
 * How the customer pays through the chosen channel. `AyaPay::services()` lists the methods each channel supports.
 */
enum AyaPayMethod: string
{
    /** Pay on a hosted web page (cards, web checkout). */
    case Web = 'WEB';

    /** Scan a QR with the wallet app. */
    case Qr = 'QR';

    /** Approve a push notification in the wallet app. */
    case Noti = 'NOTI';
}
