<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\YomaMmqr;

use Laranex\PhpMyanmarPayments\Support\Validator;

/**
 * A Yoma MMQR order. Yoma accepts each order number once; renew an expired QR with `YomaMmqr::renewQr()`.
 */
final class YomaMmqrPaymentData
{
    /**
     * @param  string  $orderId  Your unique order number, at most 20 characters.
     * @param  int  $amount  Amount in whole kyat.
     * @param  string  $description  Shown on the payment slip, at most 50 characters.
     */
    public function __construct(
        public readonly string $orderId,
        public readonly int $amount,
        public readonly string $description,
    ) {
        (new Validator)
            ->required('orderId', $orderId)
            ->max('orderId', $orderId, 20)
            ->positive('amount', $amount)
            ->required('description', $description)
            ->max('description', $description, 50)
            ->validate();
    }
}
