<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Contracts;

use Laranex\PhpMyanmarPayments\Exceptions\SignatureVerificationException;
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;
use Laranex\PhpMyanmarPayments\Results\PaymentCallback;

/**
 * Every gateway verifies its own callbacks. Initiating a payment is gateway-specific, so each
 * gateway exposes its own typed methods for that.
 */
interface PaymentGateway
{
    /**
     * Verify a callback from the gateway and read its result.
     *
     * @throws SignatureVerificationException
     */
    public function handleCallback(CallbackRequest $request): PaymentCallback;
}
