<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\AyaPay;

use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\Support\Config;
use Laranex\PhpMyanmarPayments\Support\Env;

/**
 * Credentials and endpoints for the AYA Payment Gateway (APG).
 */
final class AyaPayConfig
{
    public const SANDBOX_URL = 'https://uat-pgw.ayainnovation.com';

    public const PRODUCTION_URL = 'https://pgw.ayainnovation.com';

    public readonly string $baseUrl;

    /**
     * @param  string  $appKey  The public application key, sent with every request.
     * @param  string  $appSecret  The secret used to sign requests and verify callbacks.
     * @param  bool  $sandbox  Use the UAT environment instead of production.
     * @param  string|null  $baseUrl  Override the gateway base URL.
     *
     * @throws ConfigurationException When a credential is blank.
     */
    public function __construct(
        public readonly string $appKey,
        public readonly string $appSecret,
        public readonly bool $sandbox = true,
        ?string $baseUrl = null,
    ) {
        Config::requireValue('aya_pay', 'app_key', $appKey);
        Config::requireValue('aya_pay', 'app_secret', $appSecret);

        $this->baseUrl = rtrim(Config::optionalValue($baseUrl) ?? ($sandbox ? self::SANDBOX_URL : self::PRODUCTION_URL), '/');
    }

    /**
     * @param  array{app_key?: string|null, app_secret?: string|null, sandbox?: bool|string|null, base_url?: string|null}  $config
     *
     * @throws ConfigurationException When a credential is missing.
     */
    public static function fromArray(array $config): self
    {
        $config = new Config('aya_pay', $config);

        return new self(
            appKey: $config->required('app_key'),
            appSecret: $config->required('app_secret'),
            sandbox: $config->bool('sandbox', true),
            baseUrl: $config->string('base_url'),
        );
    }

    /**
     * Read `AYA_PAY_APP_KEY`, `AYA_PAY_APP_SECRET`, `AYA_PAY_SANDBOX` and `AYA_PAY_BASE_URL`, falling back to the
     * `AYA_PGW_*` names.
     *
     * @param  array<array-key, mixed>|null  $env  Variables to read; defaults to `getenv()` merged with `$_ENV`.
     *
     * @throws ConfigurationException When a credential is missing.
     */
    public static function fromEnv(?array $env = null): self
    {
        $env = new Env($env);

        return self::fromArray([
            'app_key' => $env->first('AYA_PAY_APP_KEY', 'AYA_PGW_APP_KEY'),
            'app_secret' => $env->first('AYA_PAY_APP_SECRET', 'AYA_PGW_APP_SECRET'),
            'sandbox' => $env->first('AYA_PAY_SANDBOX'),
            'base_url' => $env->first('AYA_PAY_BASE_URL', 'AYA_PGW_BASE_URL'),
        ]);
    }
}
