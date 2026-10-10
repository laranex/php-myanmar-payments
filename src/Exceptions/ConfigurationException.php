<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Exceptions;

/**
 * Thrown when a gateway is missing a credential or setting it needs, or a time setting is not a whole number
 * greater than 0.
 */
class ConfigurationException extends PaymentException
{
    /**
     * @param  string  $gateway  The gateway, e.g. `kbz_pay`.
     * @param  string  $key  The missing or invalid setting, e.g. `app_key`.
     * @param  bool  $invalid  The setting is set but is not a whole number greater than 0.
     */
    public function __construct(
        public readonly string $gateway,
        public readonly string $key,
        bool $invalid = false,
    ) {
        parent::__construct($invalid
            ? "The {$gateway} configuration [{$key}] must be a whole number greater than 0."
            : "The {$gateway} configuration is missing [{$key}].");
    }

    public static function missing(string $gateway, string $key): self
    {
        return new self($gateway, $key);
    }

    public static function invalid(string $gateway, string $key): self
    {
        return new self($gateway, $key, true);
    }
}
