<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\AyaPay;

use Laranex\PhpMyanmarPayments\Support\Validator;

/**
 * An AYA Payment Gateway order.
 */
final class AyaPayPaymentData
{
    /**
     * @param  string  $orderId  Your unique order id (`merchOrderId`), 6 to 40 characters.
     * @param  int  $amount  Amount in whole kyat.
     * @param  string  $channel  The channel key from `AyaPay::services()`, e.g. `aya_pay`, `kbz_pay`, `visa`.
     * @param  AyaPayMethod  $method  How the customer pays through that channel.
     * @param  string|null  $returnUrl  Where AYA sends the customer afterwards. Defaults to the URL registered with AYA.
     * @param  string|null  $description  Shown to the customer.
     * @param  list<string>  $userRefs  Up to five of your own reference values, echoed back in the callback.
     */
    public function __construct(
        public readonly string $orderId,
        public readonly int $amount,
        public readonly string $channel,
        public readonly AyaPayMethod $method,
        public readonly ?string $returnUrl = null,
        public readonly ?string $description = null,
        public readonly array $userRefs = [],
    ) {
        (new Validator)
            ->required('orderId', $orderId)
            ->length('orderId', $orderId, 6, 40)
            ->positive('amount', $amount)
            ->required('channel', $channel)
            ->url('returnUrl', $returnUrl)
            ->when(count($userRefs) > 5, 'userRefs', 'The userRefs field must not have more than 5 items.')
            ->when(! array_is_list($userRefs) || array_filter($userRefs, fn (mixed $ref): bool => ! is_string($ref)) !== [], 'userRefs', 'The userRefs field must be a list of strings.')
            ->validate();
    }
}
