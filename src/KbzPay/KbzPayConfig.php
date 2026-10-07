<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\KbzPay;

use Laranex\PhpMyanmarPayments\Support\Config;

/**
 * Credentials and endpoints for KBZ Pay. UAT and production issue separate credentials.
 */
final class KbzPayConfig
{
    public const SANDBOX_API_URL = 'http://api-uat.kbzpay.com/payment/gateway/uat';

    public const PRODUCTION_API_URL = 'https://api.kbzpay.com/payment/gateway';

    public const SANDBOX_PWA_URL = 'https://static.kbzpay.com/pgw/uat/pwa/#/';

    public const PRODUCTION_PWA_URL = 'https://wap.kbzpay.com/pgw/pwa/#/';

    public readonly string $apiUrl;

    public readonly string $pwaUrl;

    /**
     * @param  string  $appId  The `appid` KBZ issued for your merchant app.
     * @param  string  $appKey  The secret key used to sign requests.
     * @param  string  $merchantCode  The `merch_code` KBZ issued.
     * @param  bool  $sandbox  Use the UAT endpoints instead of production.
     * @param  string|null  $apiUrl  Override the API base URL.
     * @param  string|null  $pwaUrl  Override the PWA checkout URL, e.g. `https://static.kbzpay.com/pgw/uat/pwa/#/`.
     */
    public function __construct(
        public readonly string $appId,
        public readonly string $appKey,
        public readonly string $merchantCode,
        public readonly bool $sandbox = true,
        ?string $apiUrl = null,
        ?string $pwaUrl = null,
    ) {
        $this->apiUrl = rtrim($apiUrl ?? ($sandbox ? self::SANDBOX_API_URL : self::PRODUCTION_API_URL), '/');
        $this->pwaUrl = rtrim($pwaUrl ?? ($sandbox ? self::SANDBOX_PWA_URL : self::PRODUCTION_PWA_URL), '/').'/';
    }

    /**
     * @param  array{app_id?: string, app_key?: string, merchant_code?: string, sandbox?: bool|string, api_url?: string, pwa_url?: string}  $config
     */
    public static function fromArray(array $config): self
    {
        $config = new Config('kbz_pay', $config);

        return new self(
            appId: $config->required('app_id'),
            appKey: $config->required('app_key'),
            merchantCode: $config->required('merchant_code'),
            sandbox: $config->bool('sandbox', true),
            apiUrl: $config->string('api_url'),
            pwaUrl: $config->string('pwa_url'),
        );
    }
}
