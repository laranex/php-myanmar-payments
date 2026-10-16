<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\YomaMmqr;

use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\MyanmarPayments;
use Laranex\PhpMyanmarPayments\Support\Config;
use Laranex\PhpMyanmarPayments\Support\Env;

/**
 * Credentials and endpoints for Yoma Bank MMQR. Every setting is required except the HTTP timeout; `PRODUCTION_URL`
 * names production's API base URL.
 */
final class YomaMmqrConfig
{
    public const PRODUCTION_URL = 'https://paymenthubapi.yomabank.com';

    public readonly string $baseUrl;

    /**
     * @param  string  $merchantId  The merchant id Yoma issued.
     * @param  string  $clientId  OAuth client id.
     * @param  string  $clientSecret  OAuth client secret.
     * @param  string  $webhookHashKey  The hash key Yoma issued for verifying callbacks.
     * @param  string  $apiVersion  The `{version}` segment of the API paths, e.g. `v1rc`.
     * @param  string  $webhookSecret  The secret you shared with Yoma; every callback must carry it in `X-Webhook-Secret`.
     * @param  string  $baseUrl  The API base URL, e.g. `PRODUCTION_URL` or `https://devapi.yomabank.net`.
     * @param  int  $timeoutSeconds  Seconds before the default HTTP client gives up, 30 by default. A client you pass keeps its own timeout.
     *
     * @throws ConfigurationException When a setting is blank or the timeout is not greater than 0.
     */
    public function __construct(
        public readonly string $merchantId,
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly string $webhookHashKey,
        public readonly string $apiVersion,
        public readonly string $webhookSecret,
        string $baseUrl,
        public readonly int $timeoutSeconds = MyanmarPayments::DEFAULT_TIMEOUT_SECONDS,
    ) {
        Config::requireValue('yoma_mmqr', 'merchant_id', $merchantId);
        Config::requireValue('yoma_mmqr', 'client_id', $clientId);
        Config::requireValue('yoma_mmqr', 'client_secret', $clientSecret);
        Config::requireValue('yoma_mmqr', 'webhook_hashkey', $webhookHashKey);
        Config::requireValue('yoma_mmqr', 'api_version', $apiVersion);
        Config::requireValue('yoma_mmqr', 'webhook_secret', $webhookSecret);
        Config::requireValue('yoma_mmqr', 'base_url', $baseUrl);
        Config::requirePositive('yoma_mmqr', 'timeout_in_seconds', $timeoutSeconds);

        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * @param  array{merchant_id?: string|null, client_id?: string|null, client_secret?: string|null, webhook_hashkey?: string|null, api_version?: string|null, webhook_secret?: string|null, base_url?: string|null, timeout_in_seconds?: int|string|null}  $config
     *
     * @throws ConfigurationException When a setting is missing or invalid.
     */
    public static function fromArray(array $config): self
    {
        $config = new Config('yoma_mmqr', $config);

        return new self(
            merchantId: $config->required('merchant_id'),
            clientId: $config->required('client_id'),
            clientSecret: $config->required('client_secret'),
            webhookHashKey: $config->required('webhook_hashkey'),
            apiVersion: $config->required('api_version'),
            webhookSecret: $config->required('webhook_secret'),
            baseUrl: $config->required('base_url'),
            timeoutSeconds: $config->seconds('timeout_in_seconds', MyanmarPayments::DEFAULT_TIMEOUT_SECONDS),
        );
    }

    /**
     * Read `YOMA_MMQR_MERCHANT_ID`, `YOMA_MMQR_CLIENT_ID`, `YOMA_MMQR_CLIENT_SECRET`, `YOMA_MMQR_WEBHOOK_HASHKEY`,
     * `YOMA_MMQR_API_VERSION`, `YOMA_MMQR_WEBHOOK_SECRET`, `YOMA_MMQR_BASE_URL` and `MYANMAR_PAYMENTS_HTTP_TIMEOUT`.
     *
     * @param  array<array-key, mixed>|null  $env  Variables to read; defaults to `getenv()` merged with `$_ENV`.
     *
     * @throws ConfigurationException When a setting is missing or invalid.
     */
    public static function fromEnv(?array $env = null): self
    {
        $env = new Env($env);

        return self::fromArray([
            'merchant_id' => $env->first('YOMA_MMQR_MERCHANT_ID'),
            'client_id' => $env->first('YOMA_MMQR_CLIENT_ID'),
            'client_secret' => $env->first('YOMA_MMQR_CLIENT_SECRET'),
            'webhook_hashkey' => $env->first('YOMA_MMQR_WEBHOOK_HASHKEY'),
            'api_version' => $env->first('YOMA_MMQR_API_VERSION'),
            'timeout_in_seconds' => $env->first('MYANMAR_PAYMENTS_HTTP_TIMEOUT'),
            'webhook_secret' => $env->first('YOMA_MMQR_WEBHOOK_SECRET'),
            'base_url' => $env->first('YOMA_MMQR_BASE_URL'),
        ]);
    }
}
