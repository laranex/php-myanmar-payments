<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Exceptions;

/**
 * Thrown when a payment data object is constructed with values the gateway would reject.
 */
class InvalidPaymentDataException extends PaymentException
{
    /**
     * @param  array<string, string>  $errors  Field name => error message.
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct(implode(PHP_EOL, $errors));
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
