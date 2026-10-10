<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\KbzPay;

use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\Support\Config;
use Laranex\PhpMyanmarPayments\Support\Env;

/**
 * Credentials and endpoints for KBZ Pay. UAT and production issue separate credentials; to test against UAT, set
 * the URL overrides to the UAT URLs.
 */
final class KbzPayConfig
{
    public const PRODUCTION_API_URL = 'https://api.kbzpay.com/payment/gateway';

    public const PRODUCTION_PWA_URL = 'https://wap.kbzpay.com/pgw/pwa/#/';

    public readonly string $apiUrl;

    public readonly string $pwaUrl;

    /**
     * @param  string  $appId  The `appid` KBZ issued for your merchant app.
     * @param  string  $appKey  The secret key used to sign requests.
     * @param  string  $merchantCode  The `merch_code` KBZ issued.
     * @param  int  $timeoutSeconds  Seconds before the default HTTP client gives up. A client you pass keeps its own timeout.
     * @param  string|null  $apiUrl  Override the API base URL; production when unset.
     * @param  string|null  $pwaUrl  Override the PWA checkout URL, e.g. `https://static.kbzpay.com/pgw/uat/pwa/#/`; production when unset.
     *
     * @throws ConfigurationException When a setting is blank or the timeout is not greater than 0.
     */
    public function __construct(
        public readonly string $appId,
        public readonly string $appKey,
        public readonly string $merchantCode,
        public readonly int $timeoutSeconds,
        ?string $apiUrl = null,
        ?string $pwaUrl = null,
    ) {
        Config::requireValue('kbz_pay', 'app_id', $appId);
        Config::requireValue('kbz_pay', 'app_key', $appKey);
        Config::requireValue('kbz_pay', 'merchant_code', $merchantCode);
        Config::requirePositive('kbz_pay', 'timeout_in_seconds', $timeoutSeconds);

        $this->apiUrl = rtrim(Config::optionalValue($apiUrl) ?? self::PRODUCTION_API_URL, '/');
        $this->pwaUrl = rtrim(Config::optionalValue($pwaUrl) ?? self::PRODUCTION_PWA_URL, '/').'/';
    }

    /**
     * @param  array{app_id?: string|null, app_key?: string|null, merchant_code?: string|null, timeout_in_seconds?: int|string|null, api_url?: string|null, pwa_url?: string|null}  $config
     *
     * @throws ConfigurationException When a setting is missing or invalid.
     */
    public static function fromArray(array $config): self
    {
        $config = new Config('kbz_pay', $config);

        return new self(
            appId: $config->required('app_id'),
            appKey: $config->required('app_key'),
            merchantCode: $config->required('merchant_code'),
            timeoutSeconds: $config->seconds('timeout_in_seconds'),
            apiUrl: $config->string('api_url'),
            pwaUrl: $config->string('pwa_url'),
        );
    }

    /**
     * Read `KBZ_PAY_APP_ID`, `KBZ_PAY_APP_KEY`, `KBZ_PAY_MERCHANT_CODE`, `MYANMAR_PAYMENTS_HTTP_TIMEOUT`,
     * `KBZ_PAY_BASE_URL` and `KBZ_PAY_PWA_BASE_REDIRECT_URL`.
     *
     * @param  array<array-key, mixed>|null  $env  Variables to read; defaults to `getenv()` merged with `$_ENV`.
     *
     * @throws ConfigurationException When a setting is missing or invalid.
     */
    public static function fromEnv(?array $env = null): self
    {
        $env = new Env($env);

        return self::fromArray([
            'app_id' => $env->first('KBZ_PAY_APP_ID'),
            'app_key' => $env->first('KBZ_PAY_APP_KEY'),
            'merchant_code' => $env->first('KBZ_PAY_MERCHANT_CODE'),
            'timeout_in_seconds' => $env->first('MYANMAR_PAYMENTS_HTTP_TIMEOUT'),
            'api_url' => $env->first('KBZ_PAY_BASE_URL'),
            'pwa_url' => $env->first('KBZ_PAY_PWA_BASE_REDIRECT_URL'),
        ]);
    }
}
