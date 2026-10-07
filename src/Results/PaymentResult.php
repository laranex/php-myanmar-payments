<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Results;

use Laranex\PhpMyanmarPayments\Enums\PaymentFlow;

/**
 * Returned when a payment is initiated. Each flow has its own class with exactly the fields that flow needs.
 */
interface PaymentResult
{
    public function flow(): PaymentFlow;
}
