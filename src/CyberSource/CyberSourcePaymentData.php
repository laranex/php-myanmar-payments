<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\CyberSource;

use Laranex\PhpMyanmarPayments\Amount;
use Laranex\PhpMyanmarPayments\Exceptions\InvalidPaymentDataException;
use Laranex\PhpMyanmarPayments\Support\Validator;

/**
 * A CyberSource Secure Acceptance card payment.
 */
final class CyberSourcePaymentData
{
    /**
     * Order total in the payment currency. Decimals allowed, at most 15 characters.
     */
    public readonly Amount $amount;

    /**
     * @param  string  $orderId  Your order id (`reference_number`), at most 50 characters. Echoed back as `req_reference_number`.
     * @param  Amount|int  $amount  Order total in `$currency`, e.g. `Amount::parse('10.50')`. Decimals allowed, at most 15 characters.
     * @param  string  $callbackUrl  URL CyberSource posts the result to (`override_backoffice_post_url`), at most 255 characters.
     * @param  string  $currency  ISO 4217 currency code, e.g. `MMK`.
     * @param  CyberSourceTransactionType  $transactionType  What CyberSource does with the card, e.g. `CyberSourceTransactionType::Sale`.
     * @param  string  $locale  Language of the hosted page, e.g. `en-us`.
     * @param  string|null  $returnUrl  Receipt page the customer is sent to (`override_custom_receipt_page`), at most 255 characters.
     * @param  string|null  $cancelUrl  Page the customer is sent to on cancel (`override_custom_cancel_page`), at most 255 characters.
     */
    public function __construct(
        public readonly string $orderId,
        Amount|int $amount,
        public readonly string $callbackUrl,
        public readonly string $currency,
        public readonly CyberSourceTransactionType $transactionType,
        public readonly string $locale,
        public readonly ?string $returnUrl = null,
        public readonly ?string $cancelUrl = null,
    ) {
        $this->amount = Amount::from($amount);

        $this->validate();
    }

    /**
     * Check the data against the gateway's documented rules again. The constructor already does.
     *
     * @throws InvalidPaymentDataException
     */
    public function validate(): void
    {
        (new Validator)
            ->required('orderId', $this->orderId)
            ->max('orderId', $this->orderId, 50)
            ->amount('CyberSource', $this->amount, maxDecimals: null, allowZero: true, maxLength: 15)
            ->required('callbackUrl', $this->callbackUrl)
            ->url('callbackUrl', $this->callbackUrl)
            ->max('callbackUrl', $this->callbackUrl, 255)
            ->url('returnUrl', $this->returnUrl)
            ->max('returnUrl', $this->returnUrl, 255)
            ->url('cancelUrl', $this->cancelUrl)
            ->max('cancelUrl', $this->cancelUrl, 255)
            ->required('currency', $this->currency)
            ->pattern('currency', $this->currency, '/^[A-Z]{3}\z/', 'a three letter ISO 4217 code')
            ->required('locale', $this->locale)
            ->pattern('locale', $this->locale, '/^[a-z]{2}-[a-z]{2}\z/', 'a locale code such as en-us')
            ->validate();
    }
}
