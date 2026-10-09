<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\KbzPay;

use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\Support\Config;
use Laranex\PhpMyanmarPayments\Support\Env;

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
     *
     * @throws ConfigurationException When a credential is blank.
     */
    public function __construct(
        public readonly string $appId,
        public readonly string $appKey,
        public readonly string $merchantCode,
        public readonly bool $sandbox = true,
        ?string $apiUrl = null,
        ?string $pwaUrl = null,
    ) {
        Config::requireValue('kbz_pay', 'app_id', $appId);
        Config::requireValue('kbz_pay', 'app_key', $appKey);
        Config::requireValue('kbz_pay', 'merchant_code', $merchantCode);

        $this->apiUrl = rtrim(Config::optionalValue($apiUrl) ?? ($sandbox ? self::SANDBOX_API_URL : self::PRODUCTION_API_URL), '/');
        $this->pwaUrl = rtrim(Config::optionalValue($pwaUrl) ?? ($sandbox ? self::SANDBOX_PWA_URL : self::PRODUCTION_PWA_URL), '/').'/';
    }

    /**
     * @param  array{app_id?: string|null, app_key?: string|null, merchant_code?: string|null, sandbox?: bool|string|null, api_url?: string|null, pwa_url?: string|null}  $config
     *
     * @throws ConfigurationException When a credential is missing.
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

    /**
     * Read `KBZ_PAY_APP_ID`, `KBZ_PAY_APP_KEY`, `KBZ_PAY_MERCHANT_CODE`, `KBZ_PAY_SANDBOX`, `KBZ_PAY_BASE_URL`
     * and `KBZ_PAY_PWA_BASE_REDIRECT_URL`.
     *
     * @param  array<array-key, mixed>|null  $env  Variables to read; defaults to `getenv()` merged with `$_ENV`.
     *
     * @throws ConfigurationException When a credential is missing.
     */
    public static function fromEnv(?array $env = null): self
    {
        $env = new Env($env);

        return self::fromArray([
            'app_id' => $env->first('KBZ_PAY_APP_ID'),
            'app_key' => $env->first('KBZ_PAY_APP_KEY'),
            'merchant_code' => $env->first('KBZ_PAY_MERCHANT_CODE'),
            'sandbox' => $env->first('KBZ_PAY_SANDBOX'),
            'api_url' => $env->first('KBZ_PAY_BASE_URL'),
            'pwa_url' => $env->first('KBZ_PAY_PWA_BASE_REDIRECT_URL'),
        ]);
    }
}
