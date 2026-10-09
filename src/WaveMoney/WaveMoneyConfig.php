<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\WaveMoney;

use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\Support\Config;
use Laranex\PhpMyanmarPayments\Support\Env;

/**
 * Credentials and endpoints for Wave Money (WavePay payment gateway).
 */
final class WaveMoneyConfig
{
    public const SANDBOX_URL = 'https://preprodpayments.wavemoney.io:8107';

    public const PRODUCTION_URL = 'https://payments.wavemoney.io';

    public const SANDBOX_AUTHENTICATE_URL = 'https://preprodpayments.wavemoney.io';

    public const PRODUCTION_AUTHENTICATE_URL = 'https://payments.wavemoney.io';

    public readonly string $baseUrl;

    public readonly string $authenticateUrl;

    public readonly int $timeToLiveSeconds;

    /**
     * @param  string  $merchantId  The merchant id Wave issued.
     * @param  string  $secretKey  The hash secret key Wave issued.
     * @param  string  $merchantName  Your business name, shown on Wave's payment page.
     * @param  int  $timeToLiveSeconds  How long the customer has to pay. Zero or less falls back to 300.
     * @param  bool  $sandbox  Use the test environment instead of production.
     * @param  string|null  $baseUrl  Override the API base URL.
     * @param  string|null  $authenticateUrl  Override the host the customer is redirected to. Wave serves it without the API port.
     *
     * @throws ConfigurationException When a credential is blank.
     */
    public function __construct(
        public readonly string $merchantId,
        public readonly string $secretKey,
        public readonly string $merchantName,
        int $timeToLiveSeconds = 300,
        public readonly bool $sandbox = true,
        ?string $baseUrl = null,
        ?string $authenticateUrl = null,
    ) {
        Config::requireValue('wave_money', 'merchant_id', $merchantId);
        Config::requireValue('wave_money', 'secret_key', $secretKey);
        Config::requireValue('wave_money', 'merchant_name', $merchantName);

        $this->timeToLiveSeconds = $timeToLiveSeconds > 0 ? $timeToLiveSeconds : 300;
        $this->baseUrl = rtrim(Config::optionalValue($baseUrl) ?? ($sandbox ? self::SANDBOX_URL : self::PRODUCTION_URL), '/');
        $this->authenticateUrl = rtrim(Config::optionalValue($authenticateUrl) ?? ($sandbox ? self::SANDBOX_AUTHENTICATE_URL : self::PRODUCTION_AUTHENTICATE_URL), '/');
    }

    /**
     * @param  array{merchant_id?: string|null, secret_key?: string|null, merchant_name?: string|null, time_to_live_in_seconds?: int|string|null, sandbox?: bool|string|null, base_url?: string|null, authenticate_url?: string|null}  $config
     *
     * @throws ConfigurationException When a credential is missing.
     */
    public static function fromArray(array $config): self
    {
        $config = new Config('wave_money', $config);

        return new self(
            merchantId: $config->required('merchant_id'),
            secretKey: $config->required('secret_key'),
            merchantName: $config->required('merchant_name'),
            timeToLiveSeconds: $config->int('time_to_live_in_seconds', 300),
            sandbox: $config->bool('sandbox', true),
            baseUrl: $config->string('base_url'),
            authenticateUrl: $config->string('authenticate_url'),
        );
    }

    /**
     * Read `WAVE_MONEY_MERCHANT_ID`, `WAVE_MONEY_SECRET_KEY`, `WAVE_MONEY_MERCHANT_NAME` (falling back to `APP_NAME`),
     * `WAVE_MONEY_TIME_TO_LIVE_IN_SECONDS`, `WAVE_MONEY_SANDBOX`, `WAVE_MONEY_BASE_URL` and `WAVE_MONEY_AUTHENTICATE_URL`.
     *
     * @param  array<array-key, mixed>|null  $env  Variables to read; defaults to `getenv()` merged with `$_ENV`.
     *
     * @throws ConfigurationException When a credential is missing.
     */
    public static function fromEnv(?array $env = null): self
    {
        $env = new Env($env);

        return self::fromArray([
            'merchant_id' => $env->first('WAVE_MONEY_MERCHANT_ID'),
            'secret_key' => $env->first('WAVE_MONEY_SECRET_KEY'),
            'merchant_name' => $env->first('WAVE_MONEY_MERCHANT_NAME', 'APP_NAME'),
            'time_to_live_in_seconds' => $env->first('WAVE_MONEY_TIME_TO_LIVE_IN_SECONDS'),
            'sandbox' => $env->first('WAVE_MONEY_SANDBOX'),
            'base_url' => $env->first('WAVE_MONEY_BASE_URL'),
            'authenticate_url' => $env->first('WAVE_MONEY_AUTHENTICATE_URL'),
        ]);
    }
}
