<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Enums;

/**
 * How the customer completes a payment after it has been initiated.
 */
enum PaymentFlow: string
{
    /** Send the customer's browser to a gateway-hosted URL. */
    case Redirect = 'redirect';

    /** POST a signed form from the customer's browser to the gateway. */
    case Form = 'form';

    /** Show a QR code the customer scans with their wallet app. */
    case Qr = 'qr';

    /** Hand a signed payload to your mobile app, which opens the wallet SDK. */
    case App = 'app';
}
