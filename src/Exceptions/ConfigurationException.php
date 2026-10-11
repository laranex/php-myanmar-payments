<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Exceptions;

/**
 * Thrown when a gateway is missing a credential or setting it needs, or a time setting is out of range (a whole
 * number greater than 0, or for KBZ Pay's time to live a multiple of 60 from 60 to 7200).
 */
class ConfigurationException extends PaymentException
{
    public const POSITIVE_WHOLE_NUMBER = 'a whole number greater than 0';

    /**
     * @param  string  $gateway  The gateway, e.g. `kbz_pay`.
     * @param  string  $key  The missing or invalid setting, e.g. `app_key`.
     * @param  bool  $invalid  The setting is set but out of range.
     * @param  string  $requirement  What a valid value is, used in the message when `$invalid`.
     */
    public function __construct(
        public readonly string $gateway,
        public readonly string $key,
        bool $invalid = false,
        string $requirement = self::POSITIVE_WHOLE_NUMBER,
    ) {
        parent::__construct($invalid
            ? "The {$gateway} configuration [{$key}] must be {$requirement}."
            : "The {$gateway} configuration is missing [{$key}].");
    }

    public static function missing(string $gateway, string $key): self
    {
        return new self($gateway, $key);
    }

    public static function invalid(string $gateway, string $key, string $requirement = self::POSITIVE_WHOLE_NUMBER): self
    {
        return new self($gateway, $key, true, $requirement);
    }
}
