<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\YomaMmqr;

use Laranex\PhpMyanmarPayments\Amount;
use Laranex\PhpMyanmarPayments\Support\Validator;

/**
 * A Yoma MMQR order. Yoma accepts each order number once; renew an expired QR with `YomaMmqr::renewQr()`.
 */
final class YomaMmqrPaymentData
{
    /**
     * Amount in whole kyat.
     */
    public readonly Amount $amount;

    /**
     * @param  string  $orderId  Your unique order number, at most 20 characters.
     * @param  Amount|int  $amount  Amount in whole kyat, e.g. `Amount::kyat(1000)`. Yoma does not accept decimals.
     * @param  string  $description  Shown on the payment slip, at most 50 characters.
     */
    public function __construct(
        public readonly string $orderId,
        Amount|int $amount,
        public readonly string $description,
    ) {
        $this->amount = Amount::from($amount);

        (new Validator)
            ->required('orderId', $orderId)
            ->max('orderId', $orderId, 20)
            ->amount('Yoma MMQR', $this->amount, maxDecimals: 0)
            ->required('description', $description)
            ->max('description', $description, 50)
            ->validate();
    }
}
