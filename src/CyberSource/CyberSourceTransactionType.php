<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\CyberSource;

/**
 * What CyberSource does with the card.
 */
enum CyberSourceTransactionType: string
{
    /** Authorize and capture in one step. */
    case Sale = 'sale';

    /** Authorize only; capture later. */
    case Authorization = 'authorization';

    /** Sale, and save the card as a payment token. */
    case SaleAndCreateToken = 'sale,create_payment_token';

    /** Authorization, and save the card as a payment token. */
    case AuthorizationAndCreateToken = 'authorization,create_payment_token';
}
