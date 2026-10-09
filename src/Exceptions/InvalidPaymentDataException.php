<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Exceptions;

/**
 * Thrown when a payment data object is constructed with values the gateway would reject.
 */
class InvalidPaymentDataException extends PaymentException
{
    /**
     * The message is `Invalid payment data: ` followed by every error, sorted by field.
     *
     * @param  array<string, string>  $errors  Field name => error message.
     */
    public function __construct(private readonly array $errors)
    {
        $fields = array_keys($errors);
        sort($fields, SORT_STRING);

        parent::__construct('Invalid payment data: '.implode(' ', array_map(fn (string $field): string => $errors[$field], $fields)));
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
