<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\YomaMmqr;

use Laranex\PhpMyanmarPayments\Support\Config;

/**
 * Credentials and endpoints for Yoma Bank MMQR.
 */
final class YomaMmqrConfig
{
    public const SANDBOX_URL = 'https://devapi.yomabank.net';

    public const PRODUCTION_URL = 'https://paymenthubapi.yomabank.com';

    public readonly string $baseUrl;

    /**
     * @param  string  $merchantId  The merchant id Yoma issued.
     * @param  string  $clientId  OAuth client id.
     * @param  string  $clientSecret  OAuth client secret.
     * @param  string  $webhookHashKey  The hash key Yoma issued for verifying callbacks.
     * @param  string|null  $webhookSecret  The secret you shared with Yoma; when set, callbacks must carry it in `X-Webhook-Secret`.
     * @param  bool  $sandbox  Use the UAT environment instead of production.
     * @param  string|null  $baseUrl  Override the API base URL.
     * @param  string  $apiVersion  The `{version}` segment of the API paths.
     */
    public function __construct(
        public readonly string $merchantId,
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly string $webhookHashKey,
        public readonly ?string $webhookSecret = null,
        public readonly bool $sandbox = true,
        ?string $baseUrl = null,
        public readonly string $apiVersion = 'v1rc',
    ) {
        $this->baseUrl = rtrim($baseUrl ?? ($sandbox ? self::SANDBOX_URL : self::PRODUCTION_URL), '/');
    }

    /**
     * @param  array{merchant_id?: string, client_id?: string, client_secret?: string, webhook_hashkey?: string, webhook_secret?: string, sandbox?: bool|string, base_url?: string, api_version?: string}  $config
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
            apiVersion: (string) $config->string('api_version', 'v1rc'),
        );
    }
}
