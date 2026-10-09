<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\YomaMmqr;

use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\Support\Config;
use Laranex\PhpMyanmarPayments\Support\Env;

/**
 * Credentials and endpoints for Yoma Bank MMQR.
 */
final class YomaMmqrConfig
{
    public const SANDBOX_URL = 'https://devapi.yomabank.net';

    public const PRODUCTION_URL = 'https://paymenthubapi.yomabank.com';

    public const DEFAULT_API_VERSION = 'v1rc';

    public readonly string $baseUrl;

    /**
     * The secret callbacks must carry in `X-Webhook-Secret`, or null when not checked.
     */
    public readonly ?string $webhookSecret;

    /**
     * The `{version}` segment of the API paths in use.
     */
    public readonly string $apiVersion;

    /**
     * @param  string  $merchantId  The merchant id Yoma issued.
     * @param  string  $clientId  OAuth client id.
     * @param  string  $clientSecret  OAuth client secret.
     * @param  string  $webhookHashKey  The hash key Yoma issued for verifying callbacks.
     * @param  string|null  $webhookSecret  The secret you shared with Yoma; when set, callbacks must carry it in `X-Webhook-Secret`.
     * @param  bool  $sandbox  Use the UAT environment instead of production.
     * @param  string|null  $baseUrl  Override the API base URL.
     * @param  string  $apiVersion  The `{version}` segment of the API paths. Blank means `v1rc`.
     *
     * @throws ConfigurationException When a credential is blank.
     */
    public function __construct(
        public readonly string $merchantId,
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly string $webhookHashKey,
        ?string $webhookSecret = null,
        public readonly bool $sandbox = true,
        ?string $baseUrl = null,
        string $apiVersion = self::DEFAULT_API_VERSION,
    ) {
        Config::requireValue('yoma_mmqr', 'merchant_id', $merchantId);
        Config::requireValue('yoma_mmqr', 'client_id', $clientId);
        Config::requireValue('yoma_mmqr', 'client_secret', $clientSecret);
        Config::requireValue('yoma_mmqr', 'webhook_hashkey', $webhookHashKey);

        $this->webhookSecret = Config::optionalValue($webhookSecret);
        $this->baseUrl = rtrim(Config::optionalValue($baseUrl) ?? ($sandbox ? self::SANDBOX_URL : self::PRODUCTION_URL), '/');
        $this->apiVersion = Config::optionalValue($apiVersion) ?? self::DEFAULT_API_VERSION;
    }

    /**
     * @param  array{merchant_id?: string|null, client_id?: string|null, client_secret?: string|null, webhook_hashkey?: string|null, webhook_secret?: string|null, sandbox?: bool|string|null, base_url?: string|null, api_version?: string|null}  $config
     *
     * @throws ConfigurationException When a credential is missing.
     */
    public static function fromArray(array $config): self
    {
        $config = new Config('yoma_mmqr', $config);

        return new self(
            merchantId: $config->required('merchant_id'),
            clientId: $config->required('client_id'),
            clientSecret: $config->required('client_secret'),
            webhookHashKey: $config->required('webhook_hashkey'),
            webhookSecret: $config->string('webhook_secret'),
            sandbox: $config->bool('sandbox', true),
            baseUrl: $config->string('base_url'),
            apiVersion: (string) $config->string('api_version', self::DEFAULT_API_VERSION),
        );
    }

    /**
     * Read `YOMA_MMQR_MERCHANT_ID`, `YOMA_MMQR_CLIENT_ID`, `YOMA_MMQR_CLIENT_SECRET`, `YOMA_MMQR_WEBHOOK_HASHKEY`,
     * `YOMA_MMQR_WEBHOOK_SECRET`, `YOMA_MMQR_SANDBOX`, `YOMA_MMQR_BASE_URL` and `YOMA_MMQR_API_VERSION`.
     *
     * @param  array<array-key, mixed>|null  $env  Variables to read; defaults to `getenv()` merged with `$_ENV`.
     *
     * @throws ConfigurationException When a credential is missing.
     */
    public static function fromEnv(?array $env = null): self
    {
        $env = new Env($env);

        return self::fromArray([
            'merchant_id' => $env->first('YOMA_MMQR_MERCHANT_ID'),
            'client_id' => $env->first('YOMA_MMQR_CLIENT_ID'),
            'client_secret' => $env->first('YOMA_MMQR_CLIENT_SECRET'),
            'webhook_hashkey' => $env->first('YOMA_MMQR_WEBHOOK_HASHKEY'),
            'webhook_secret' => $env->first('YOMA_MMQR_WEBHOOK_SECRET'),
            'sandbox' => $env->first('YOMA_MMQR_SANDBOX'),
            'base_url' => $env->first('YOMA_MMQR_BASE_URL'),
            'api_version' => $env->first('YOMA_MMQR_API_VERSION'),
        ]);
    }
}
