<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Exceptions;

/**
 * Thrown when a gateway is missing a credential or setting it needs.
 */
class ConfigurationException extends PaymentException
{
    /**
     * @param  string  $gateway  The gateway, e.g. `kbz_pay`.
     * @param  string  $key  The missing setting, e.g. `app_key`.
     */
    public function __construct(
        public readonly string $gateway,
        public readonly string $key,
    ) {
        parent::__construct("The {$gateway} configuration is missing [{$key}].");
    }

    public static function missing(string $gateway, string $key): self
    {
        return new self($gateway, $key);
    }
}
