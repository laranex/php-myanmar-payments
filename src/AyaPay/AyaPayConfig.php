<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\AyaPay;

use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\MyanmarPayments;
use Laranex\PhpMyanmarPayments\Support\Config;
use Laranex\PhpMyanmarPayments\Support\Env;

/**
 * Credentials and endpoints for the AYA Payment Gateway (APG). Every setting is required except the HTTP timeout;
 * `PRODUCTION_URL` names production's base URL.
 */
final class AyaPayConfig
{
    public const PRODUCTION_URL = 'https://pgw.ayainnovation.com';

    public readonly string $baseUrl;

    /**
     * @param  string  $appKey  The public application key, sent with every request.
     * @param  string  $appSecret  The secret used to sign requests and verify callbacks.
     * @param  string  $baseUrl  The gateway base URL, e.g. `PRODUCTION_URL` or `https://uat-pgw.ayainnovation.com`.
     * @param  int  $timeoutSeconds  Seconds before the default HTTP client gives up, 30 by default. A client you pass keeps its own timeout.
     *
     * @throws ConfigurationException When a setting is blank or the timeout is not greater than 0.
     */
    public function __construct(
        public readonly string $appKey,
        public readonly string $appSecret,
        string $baseUrl,
        public readonly int $timeoutSeconds = MyanmarPayments::DEFAULT_TIMEOUT_SECONDS,
    ) {
        Config::requireValue('aya_pay', 'app_key', $appKey);
        Config::requireValue('aya_pay', 'app_secret', $appSecret);
        Config::requireValue('aya_pay', 'base_url', $baseUrl);
        Config::requirePositive('aya_pay', 'timeout_in_seconds', $timeoutSeconds);

        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * @param  array{app_key?: string|null, app_secret?: string|null, base_url?: string|null, timeout_in_seconds?: int|string|null}  $config
     *
     * @throws ConfigurationException When a setting is missing or invalid.
     */
    public static function fromArray(array $config): self
    {
        $config = new Config('aya_pay', $config);

        return new self(
            appKey: $config->required('app_key'),
            appSecret: $config->required('app_secret'),
            baseUrl: $config->required('base_url'),
            timeoutSeconds: $config->seconds('timeout_in_seconds', MyanmarPayments::DEFAULT_TIMEOUT_SECONDS),
        );
    }

    /**
     * Read `AYA_PAY_APP_KEY`, `AYA_PAY_APP_SECRET`, `AYA_PAY_BASE_URL` and `MYANMAR_PAYMENTS_HTTP_TIMEOUT`, falling
     * back to the `AYA_PGW_*` names.
     *
     * @param  array<array-key, mixed>|null  $env  Variables to read; defaults to `getenv()` merged with `$_ENV`.
     *
     * @throws ConfigurationException When a setting is missing or invalid.
     */
    public static function fromEnv(?array $env = null): self
    {
        $env = new Env($env);

        return self::fromArray([
            'app_key' => $env->first('AYA_PAY_APP_KEY', 'AYA_PGW_APP_KEY'),
            'app_secret' => $env->first('AYA_PAY_APP_SECRET', 'AYA_PGW_APP_SECRET'),
            'timeout_in_seconds' => $env->first('MYANMAR_PAYMENTS_HTTP_TIMEOUT'),
            'base_url' => $env->first('AYA_PAY_BASE_URL', 'AYA_PGW_BASE_URL'),
        ]);
    }
}
