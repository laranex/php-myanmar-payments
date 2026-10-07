<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\AyaPay;

use Laranex\PhpMyanmarPayments\Support\Config;

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
     */
    public function __construct(
        public readonly string $appKey,
        public readonly string $appSecret,
        public readonly bool $sandbox = true,
        ?string $baseUrl = null,
    ) {
        $this->baseUrl = rtrim($baseUrl ?? ($sandbox ? self::SANDBOX_URL : self::PRODUCTION_URL), '/');
    }

    /**
     * @param  array{app_key?: string, app_secret?: string, sandbox?: bool|string, base_url?: string}  $config
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
}
