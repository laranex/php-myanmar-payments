<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\YomaMmqr;

use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\Support\Config;
use Laranex\PhpMyanmarPayments\Support\Env;

/**
 * Credentials and endpoints for Yoma Bank MMQR. To test against UAT, set the URL override to the UAT URL.
 */
final class YomaMmqrConfig
{
    public const PRODUCTION_URL = 'https://paymenthubapi.yomabank.com';

    public readonly string $baseUrl;

    /**
     * The secret callbacks must carry in `X-Webhook-Secret`, or null when not checked.
     */
    public readonly ?string $webhookSecret;

    /**
     * @param  string  $merchantId  The merchant id Yoma issued.
     * @param  string  $clientId  OAuth client id.
     * @param  string  $clientSecret  OAuth client secret.
     * @param  string  $webhookHashKey  The hash key Yoma issued for verifying callbacks.
     * @param  string  $apiVersion  The `{version}` segment of the API paths, e.g. `v1rc`.
     * @param  int  $timeoutSeconds  Seconds before the default HTTP client gives up. A client you pass keeps its own timeout.
     * @param  string|null  $webhookSecret  The secret you shared with Yoma; when set, callbacks must carry it in `X-Webhook-Secret`.
     * @param  string|null  $baseUrl  Override the API base URL; production when unset.
     *
     * @throws ConfigurationException When a setting is blank or the timeout is not greater than 0.
     */
    public function __construct(
        public readonly string $merchantId,
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly string $webhookHashKey,
        public readonly string $apiVersion,
        public readonly int $timeoutSeconds,
        ?string $webhookSecret = null,
        ?string $baseUrl = null,
    ) {
        Config::requireValue('yoma_mmqr', 'merchant_id', $merchantId);
        Config::requireValue('yoma_mmqr', 'client_id', $clientId);
        Config::requireValue('yoma_mmqr', 'client_secret', $clientSecret);
        Config::requireValue('yoma_mmqr', 'webhook_hashkey', $webhookHashKey);
        Config::requireValue('yoma_mmqr', 'api_version', $apiVersion);
        Config::requirePositive('yoma_mmqr', 'timeout_in_seconds', $timeoutSeconds);

        $this->webhookSecret = Config::optionalValue($webhookSecret);
        $this->baseUrl = rtrim(Config::optionalValue($baseUrl) ?? self::PRODUCTION_URL, '/');
    }

    /**
     * @param  array{merchant_id?: string|null, client_id?: string|null, client_secret?: string|null, webhook_hashkey?: string|null, api_version?: string|null, timeout_in_seconds?: int|string|null, webhook_secret?: string|null, base_url?: string|null}  $config
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
            timeoutSeconds: $config->seconds('timeout_in_seconds'),
            webhookSecret: $config->string('webhook_secret'),
            baseUrl: $config->string('base_url'),
        );
    }

    /**
     * Read `YOMA_MMQR_MERCHANT_ID`, `YOMA_MMQR_CLIENT_ID`, `YOMA_MMQR_CLIENT_SECRET`, `YOMA_MMQR_WEBHOOK_HASHKEY`,
     * `YOMA_MMQR_API_VERSION`, `MYANMAR_PAYMENTS_HTTP_TIMEOUT`, `YOMA_MMQR_WEBHOOK_SECRET` and `YOMA_MMQR_BASE_URL`.
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
