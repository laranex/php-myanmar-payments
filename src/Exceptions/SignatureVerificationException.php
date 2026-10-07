<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Exceptions;

/**
 * Thrown when a callback or gateway response fails signature verification. Never trust its payload.
 */
class SignatureVerificationException extends PaymentException
{
    /**
     * @param  array<array-key, mixed>  $raw  The unverified payload, for logging only.
     */
    public function __construct(string $message, public readonly array $raw = [])
    {
        parent::__construct($message);
    }
}
