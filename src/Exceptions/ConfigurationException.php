<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Exceptions;

/**
 * Thrown when a gateway is missing a credential or setting it needs.
 */
class ConfigurationException extends PaymentException
{
    public static function missing(string $gateway, string $key): self
    {
        return new self("The {$gateway} configuration is missing [{$key}].");
    }
}
