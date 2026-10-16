<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\KbzPay;

use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\MyanmarPayments;
use Laranex\PhpMyanmarPayments\Support\Config;
use Laranex\PhpMyanmarPayments\Support\Env;

/**
 * Credentials and endpoints for KBZ Pay. Every setting is required except the HTTP timeout; UAT and production issue
 * separate credentials and use separate URLs (`PRODUCTION_API_URL` and `PRODUCTION_PWA_URL` name production's).
 */
final class KbzPayConfig
{
    public const PRODUCTION_API_URL = 'https://api.kbzpay.com/payment/gateway';

    public const PRODUCTION_PWA_URL = 'https://wap.kbzpay.com/pgw/pwa/#/';

    /**
     * What a valid `time_to_live_in_minutes` is: KBZ counts whole minutes, up to 120.
     */
    public const TIME_TO_LIVE_REQUIREMENT = 'a whole number from 1 to 120';

    public readonly string $apiUrl;

    public readonly string $pwaUrl;

    public readonly int $timeToLiveMinutes;

    /**
     * @param  string  $appId  The `appid` KBZ issued for your merchant app.
     * @param  string  $appKey  The secret key used to sign requests.
     * @param  string  $merchantCode  The `merch_code` KBZ issued.
     * @param  string  $apiUrl  The API base URL, e.g. `PRODUCTION_API_URL` or `http://api-uat.kbzpay.com/payment/gateway/uat`.
     * @param  string  $pwaUrl  The PWA checkout URL, e.g. `PRODUCTION_PWA_URL` or `https://static.kbzpay.com/pgw/uat/pwa/#/`.
     * @param  int  $timeToLiveMinutes  Minutes the customer has to pay each order, 1 to 120. A payment's own `timeToLiveMinutes` overrides it.
     * @param  int  $timeoutSeconds  Seconds before the default HTTP client gives up, 30 by default. A client you pass keeps its own timeout.
     *
     * @throws ConfigurationException When a setting is blank, the timeout is not greater than 0 or the time to live is out of range.
     */
    public function __construct(
        public readonly string $appId,
        public readonly string $appKey,
        public readonly string $merchantCode,
        string $apiUrl,
        string $pwaUrl,
        int $timeToLiveMinutes,
        public readonly int $timeoutSeconds = MyanmarPayments::DEFAULT_TIMEOUT_SECONDS,
    ) {
        Config::requireValue('kbz_pay', 'app_id', $appId);
        Config::requireValue('kbz_pay', 'app_key', $appKey);
        Config::requireValue('kbz_pay', 'merchant_code', $merchantCode);
        Config::requireValue('kbz_pay', 'api_url', $apiUrl);
        Config::requireValue('kbz_pay', 'pwa_url', $pwaUrl);
        Config::requirePositive('kbz_pay', 'timeout_in_seconds', $timeoutSeconds);

        if ($timeToLiveMinutes < 1 || $timeToLiveMinutes > 120) {
            throw ConfigurationException::invalid('kbz_pay', 'time_to_live_in_minutes', self::TIME_TO_LIVE_REQUIREMENT);
        }

        $this->apiUrl = rtrim($apiUrl, '/');
        $this->pwaUrl = rtrim($pwaUrl, '/').'/';
        $this->timeToLiveMinutes = $timeToLiveMinutes;
    }

    /**
     * @param  array{app_id?: string|null, app_key?: string|null, merchant_code?: string|null, api_url?: string|null, pwa_url?: string|null, time_to_live_in_minutes?: int|string|null, timeout_in_seconds?: int|string|null}  $config
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
            apiUrl: $config->required('api_url'),
            pwaUrl: $config->required('pwa_url'),
            timeToLiveMinutes: $config->requiredInteger('time_to_live_in_minutes', self::TIME_TO_LIVE_REQUIREMENT),
            timeoutSeconds: $config->seconds('timeout_in_seconds', MyanmarPayments::DEFAULT_TIMEOUT_SECONDS),
        );
    }

    /**
     * Read `KBZ_PAY_APP_ID`, `KBZ_PAY_APP_KEY`, `KBZ_PAY_MERCHANT_CODE`, `MYANMAR_PAYMENTS_HTTP_TIMEOUT`,
     * `KBZ_PAY_BASE_URL`, `KBZ_PAY_PWA_BASE_REDIRECT_URL` and `KBZ_PAY_TIME_TO_LIVE_IN_MINUTES`.
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
            'time_to_live_in_minutes' => $env->first('KBZ_PAY_TIME_TO_LIVE_IN_MINUTES'),
        ]);
    }
}
