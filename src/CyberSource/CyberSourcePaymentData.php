<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\CyberSource;

use Laranex\PhpMyanmarPayments\Support\Validator;

/**
 * A CyberSource Secure Acceptance card payment.
 */
final class CyberSourcePaymentData
{
    /**
     * @param  string  $orderId  Your order id (`reference_number`), at most 50 characters. Echoed back as `req_reference_number`.
     * @param  int|string  $amount  Order total in `$currency`, decimals allowed as a string, e.g. `'10.50'`. At most 15 characters.
     * @param  string  $callbackUrl  HTTPS URL CyberSource posts the result to (`override_backoffice_post_url`), at most 255 characters.
     * @param  string|null  $returnUrl  HTTPS receipt page the customer is sent to (`override_custom_receipt_page`), at most 255 characters.
     * @param  string|null  $cancelUrl  HTTPS page the customer is sent to on cancel (`override_custom_cancel_page`), at most 255 characters.
     * @param  string  $currency  ISO 4217 currency code.
     * @param  CyberSourceTransactionType  $transactionType  What CyberSource does with the card.
     * @param  string  $locale  Language of the hosted page, e.g. `en-us`.
     */
    public function __construct(
        public readonly string $orderId,
        public readonly int|string $amount,
        public readonly string $callbackUrl,
        public readonly ?string $returnUrl = null,
        public readonly ?string $cancelUrl = null,
        public readonly string $currency = 'MMK',
        public readonly CyberSourceTransactionType $transactionType = CyberSourceTransactionType::Sale,
        public readonly string $locale = 'en-us',
    ) {
        (new Validator)
            ->required('orderId', $orderId)
            ->max('orderId', $orderId, 50)
            ->decimal('amount', $amount, maxDecimals: 3, maxLength: 15, allowZero: true)
            ->required('callbackUrl', $callbackUrl)
            ->url('callbackUrl', $callbackUrl)
            ->https('callbackUrl', $callbackUrl)
            ->max('callbackUrl', $callbackUrl, 255)
            ->url('returnUrl', $returnUrl)
            ->https('returnUrl', $returnUrl)
            ->max('returnUrl', $returnUrl, 255)
            ->url('cancelUrl', $cancelUrl)
            ->https('cancelUrl', $cancelUrl)
            ->max('cancelUrl', $cancelUrl, 255)
            ->pattern('currency', $currency, '/^[A-Z]{3}$/', 'a three letter ISO 4217 code')
            ->pattern('locale', $locale, '/^[a-z]{2}-[a-z]{2}$/', 'a locale code such as en-us')
            ->validate();
    }
}
