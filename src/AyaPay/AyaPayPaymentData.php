<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\AyaPay;

use Laranex\PhpMyanmarPayments\Amount;
use Laranex\PhpMyanmarPayments\Support\Validator;

/**
 * An AYA Payment Gateway order.
 */
final class AyaPayPaymentData
{
    /**
     * Amount in whole kyat.
     */
    public readonly Amount $amount;

    /**
     * @param  string  $orderId  Your unique order id (`merchOrderId`), 6 to 40 characters.
     * @param  Amount|int  $amount  Amount in whole kyat, e.g. `Amount::kyat(1000)`. AYA does not accept decimals.
     * @param  string  $channel  The channel key from `AyaPay::services()`, e.g. `aya_pay`, `kbz_pay`, `visa`.
     * @param  AyaPayMethod  $method  How the customer pays through that channel.
     * @param  string|null  $returnUrl  Where AYA sends the customer afterwards. Defaults to the URL registered with AYA.
     * @param  string|null  $description  Shown to the customer.
     * @param  list<string>  $userRefs  Up to five of your own reference values, echoed back in the callback.
     */
    public function __construct(
        public readonly string $orderId,
        Amount|int $amount,
        public readonly string $channel,
        public readonly AyaPayMethod $method,
        public readonly ?string $returnUrl = null,
        public readonly ?string $description = null,
        public readonly array $userRefs = [],
    ) {
        $this->amount = Amount::from($amount);

        (new Validator)
            ->required('orderId', $orderId)
            ->length('orderId', $orderId, 6, 40)
            ->amount('AYA Pay', $this->amount, maxDecimals: 0)
            ->required('channel', $channel)
            ->url('returnUrl', $returnUrl)
            ->when(count($userRefs) > 5, 'userRefs', 'The userRefs field must not have more than 5 items.')
            ->when(! array_is_list($userRefs) || array_filter($userRefs, fn (mixed $ref): bool => ! is_string($ref)) !== [], 'userRefs', 'The userRefs field must be a list of strings.')
            ->validate();
    }
}
