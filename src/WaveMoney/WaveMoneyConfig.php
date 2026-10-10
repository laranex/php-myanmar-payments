<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\WaveMoney;

use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\Support\Config;
use Laranex\PhpMyanmarPayments\Support\Env;

/**
 * Credentials and endpoints for Wave Money (WavePay payment gateway). To test against UAT, set the URL overrides to
 * the UAT URLs.
 */
final class WaveMoneyConfig
{
    public const PRODUCTION_URL = 'https://payments.wavemoney.io';

    public const PRODUCTION_AUTHENTICATE_URL = 'https://payments.wavemoney.io';

    public readonly string $baseUrl;

    public readonly string $authenticateUrl;

    /**
     * @param  string  $merchantId  The merchant id Wave issued.
     * @param  string  $secretKey  The hash secret key Wave issued.
     * @param  string  $merchantName  Your business name, shown on Wave's payment page.
     * @param  int  $timeToLiveSeconds  How long the customer has to pay, greater than 0.
     * @param  int  $timeoutSeconds  Seconds before the default HTTP client gives up. A client you pass keeps its own timeout.
     * @param  string|null  $baseUrl  Override the API base URL; production when unset.
     * @param  string|null  $authenticateUrl  Override the host the customer is redirected to; production when unset. Wave serves it without the API port.
     *
     * @throws ConfigurationException When a setting is blank or a time setting is not greater than 0.
     */
    public function __construct(
        public readonly string $merchantId,
        public readonly string $secretKey,
        public readonly string $merchantName,
        public readonly int $timeToLiveSeconds,
        public readonly int $timeoutSeconds,
        ?string $baseUrl = null,
        ?string $authenticateUrl = null,
    ) {
        Config::requireValue('wave_money', 'merchant_id', $merchantId);
        Config::requireValue('wave_money', 'secret_key', $secretKey);
        Config::requireValue('wave_money', 'merchant_name', $merchantName);
        Config::requirePositive('wave_money', 'time_to_live_in_seconds', $timeToLiveSeconds);
        Config::requirePositive('wave_money', 'timeout_in_seconds', $timeoutSeconds);

        $this->baseUrl = rtrim(Config::optionalValue($baseUrl) ?? self::PRODUCTION_URL, '/');
        $this->authenticateUrl = rtrim(Config::optionalValue($authenticateUrl) ?? self::PRODUCTION_AUTHENTICATE_URL, '/');
    }

    /**
     * @param  array{merchant_id?: string|null, secret_key?: string|null, merchant_name?: string|null, time_to_live_in_seconds?: int|string|null, timeout_in_seconds?: int|string|null, base_url?: string|null, authenticate_url?: string|null}  $config
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
            timeToLiveSeconds: $config->seconds('time_to_live_in_seconds'),
            timeoutSeconds: $config->seconds('timeout_in_seconds'),
            baseUrl: $config->string('base_url'),
            authenticateUrl: $config->string('authenticate_url'),
        );
    }

    /**
     * Read `WAVE_MONEY_MERCHANT_ID`, `WAVE_MONEY_SECRET_KEY`, `WAVE_MONEY_MERCHANT_NAME`,
     * `WAVE_MONEY_TIME_TO_LIVE_IN_SECONDS`, `MYANMAR_PAYMENTS_HTTP_TIMEOUT`, `WAVE_MONEY_BASE_URL` and
     * `WAVE_MONEY_AUTHENTICATE_URL`.
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
            'authenticate_url' => $env->first('WAVE_MONEY_AUTHENTICATE_URL'),
        ]);
    }
}
