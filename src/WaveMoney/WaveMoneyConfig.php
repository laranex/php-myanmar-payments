<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\WaveMoney;

use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\MyanmarPayments;
use Laranex\PhpMyanmarPayments\Support\Config;
use Laranex\PhpMyanmarPayments\Support\Env;

/**
 * Credentials and endpoints for Wave Money (WavePay payment gateway). Every setting is required except the HTTP
 * timeout; `PRODUCTION_URL` names production's API and payment page host.
 */
final class WaveMoneyConfig
{
    public const PRODUCTION_URL = 'https://payments.wavemoney.io';

    public readonly string $baseUrl;

    public readonly string $baseRedirectUrl;

    /**
     * @param  string  $merchantId  The merchant id Wave issued.
     * @param  string  $secretKey  The hash secret key Wave issued.
     * @param  string  $merchantName  Your business name, shown on Wave's payment page.
     * @param  string  $baseUrl  The API base URL, e.g. `PRODUCTION_URL` or `https://preprodpayments.wavemoney.io:8107`. Payments are requested at its `/payment`.
     * @param  string  $baseRedirectUrl  The host the customer is sent to, e.g. `PRODUCTION_URL` or `https://preprodpayments.wavemoney.io`. The customer approves the payment at its `/authenticate`.
     * @param  int  $timeToLiveSeconds  How long the customer has to pay, greater than 0. A payment's own `timeToLiveSeconds` overrides it.
     * @param  int  $timeoutSeconds  Seconds before the default HTTP client gives up, 30 by default. A client you pass keeps its own timeout.
     *
     * @throws ConfigurationException When a setting is blank or a time setting is not greater than 0.
     */
    public function __construct(
        public readonly string $merchantId,
        public readonly string $secretKey,
        public readonly string $merchantName,
        string $baseUrl,
        string $baseRedirectUrl,
        public readonly int $timeToLiveSeconds,
        public readonly int $timeoutSeconds = MyanmarPayments::DEFAULT_TIMEOUT_SECONDS,
    ) {
        Config::requireValue('wave_money', 'merchant_id', $merchantId);
        Config::requireValue('wave_money', 'secret_key', $secretKey);
        Config::requireValue('wave_money', 'merchant_name', $merchantName);
        Config::requireValue('wave_money', 'base_url', $baseUrl);
        Config::requireValue('wave_money', 'base_redirect_url', $baseRedirectUrl);
        Config::requirePositive('wave_money', 'time_to_live_in_seconds', $timeToLiveSeconds);
        Config::requirePositive('wave_money', 'timeout_in_seconds', $timeoutSeconds);

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->baseRedirectUrl = rtrim($baseRedirectUrl, '/');
    }

    /**
     * @param  array{merchant_id?: string|null, secret_key?: string|null, merchant_name?: string|null, base_url?: string|null, base_redirect_url?: string|null, time_to_live_in_seconds?: int|string|null, timeout_in_seconds?: int|string|null}  $config
     *
     * @throws ConfigurationException When a setting is missing or invalid.
     */
    public static function fromArray(array $config): self
    {
        $config = new Config('wave_money', $config);

        return new self(
            merchantId: $config->required('merchant_id'),
            secretKey: $config->required('secret_key'),
            merchantName: $config->required('merchant_name'),
            baseUrl: $config->required('base_url'),
            baseRedirectUrl: $config->required('base_redirect_url'),
            timeToLiveSeconds: $config->seconds('time_to_live_in_seconds'),
            timeoutSeconds: $config->seconds('timeout_in_seconds', MyanmarPayments::DEFAULT_TIMEOUT_SECONDS),
        );
    }

    /**
     * Read `WAVE_MONEY_MERCHANT_ID`, `WAVE_MONEY_SECRET_KEY`, `WAVE_MONEY_MERCHANT_NAME`,
     * `WAVE_MONEY_BASE_URL`, `WAVE_MONEY_BASE_REDIRECT_URL`, `WAVE_MONEY_TIME_TO_LIVE_IN_SECONDS` and
     * `MYANMAR_PAYMENTS_HTTP_TIMEOUT`.
     *
     * @param  array<array-key, mixed>|null  $env  Variables to read; defaults to `getenv()` merged with `$_ENV`.
     *
     * @throws ConfigurationException When a setting is missing or invalid.
     */
    public static function fromEnv(?array $env = null): self
    {
        $env = new Env($env);

        return self::fromArray([
            'merchant_id' => $env->first('WAVE_MONEY_MERCHANT_ID'),
            'secret_key' => $env->first('WAVE_MONEY_SECRET_KEY'),
            'merchant_name' => $env->first('WAVE_MONEY_MERCHANT_NAME'),
            'time_to_live_in_seconds' => $env->first('WAVE_MONEY_TIME_TO_LIVE_IN_SECONDS'),
            'timeout_in_seconds' => $env->first('MYANMAR_PAYMENTS_HTTP_TIMEOUT'),
            'base_url' => $env->first('WAVE_MONEY_BASE_URL'),
            'base_redirect_url' => $env->first('WAVE_MONEY_BASE_REDIRECT_URL'),
        ]);
    }
}
