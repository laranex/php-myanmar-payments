<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\WavePayMiniApp;

use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\MyanmarPayments;
use Laranex\PhpMyanmarPayments\Support\Config;
use Laranex\PhpMyanmarPayments\Support\Env;

/**
 * Credentials and endpoint for Wave Pay Mini App (Wave Money JS SDK). Every setting is required except the HTTP
 * timeout; `PRODUCTION_URL` names production's API base URL.
 */
final class WavePayMiniAppConfig
{
    public const PRODUCTION_URL = 'https://api.wavemoney.io:8100';

    public readonly string $baseUrl;

    /**
     * @param  string  $clientId  The client id Wave issued, sent to the token endpoint.
     * @param  string  $clientSecret  The client secret Wave issued, sent to the token endpoint.
     * @param  string  $miniAppId  The mini app id Wave issued (`miniAppID` in `makePayment`).
     * @param  string  $secretKey  The hash secret key Wave issued for payment and callback hashes.
     * @param  string  $baseUrl  The API base URL, e.g. `PRODUCTION_URL` or `https://preprodnewapi.wavemoney.io`. Tokens are requested at its `/auth/v1/external-utility/token`.
     * @param  int  $timeoutSeconds  Seconds before the default HTTP client gives up, 30 by default. A client you pass keeps its own timeout.
     *
     * @throws ConfigurationException When a setting is blank or the timeout is not greater than 0.
     */
    public function __construct(
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly string $miniAppId,
        public readonly string $secretKey,
        string $baseUrl,
        public readonly int $timeoutSeconds = MyanmarPayments::DEFAULT_TIMEOUT_SECONDS,
    ) {
        Config::requireValue('wave_pay_mini_app', 'client_id', $clientId);
        Config::requireValue('wave_pay_mini_app', 'client_secret', $clientSecret);
        Config::requireValue('wave_pay_mini_app', 'mini_app_id', $miniAppId);
        Config::requireValue('wave_pay_mini_app', 'secret_key', $secretKey);
        Config::requireValue('wave_pay_mini_app', 'base_url', $baseUrl);
        Config::requirePositive('wave_pay_mini_app', 'timeout_in_seconds', $timeoutSeconds);

        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * @param  array{client_id?: string|null, client_secret?: string|null, mini_app_id?: string|null, secret_key?: string|null, base_url?: string|null, timeout_in_seconds?: int|string|null}  $config
     *
     * @throws ConfigurationException When a setting is missing or invalid.
     */
    public static function fromArray(array $config): self
    {
        $config = new Config('wave_pay_mini_app', $config);

        return new self(
            clientId: $config->required('client_id'),
            clientSecret: $config->required('client_secret'),
            miniAppId: $config->required('mini_app_id'),
            secretKey: $config->required('secret_key'),
            baseUrl: $config->required('base_url'),
            timeoutSeconds: $config->seconds('timeout_in_seconds', MyanmarPayments::DEFAULT_TIMEOUT_SECONDS),
        );
    }

    /**
     * Read `WAVE_PAY_MINI_APP_CLIENT_ID`, `WAVE_PAY_MINI_APP_CLIENT_SECRET`, `WAVE_PAY_MINI_APP_MINI_APP_ID`,
     * `WAVE_PAY_MINI_APP_SECRET_KEY`, `WAVE_PAY_MINI_APP_BASE_URL` and `MYANMAR_PAYMENTS_HTTP_TIMEOUT`.
     *
     * @param  array<array-key, mixed>|null  $env  Variables to read; defaults to `getenv()` merged with `$_ENV`.
     *
     * @throws ConfigurationException When a setting is missing or invalid.
     */
    public static function fromEnv(?array $env = null): self
    {
        $env = new Env($env);

        return self::fromArray([
            'client_id' => $env->first('WAVE_PAY_MINI_APP_CLIENT_ID'),
            'client_secret' => $env->first('WAVE_PAY_MINI_APP_CLIENT_SECRET'),
            'mini_app_id' => $env->first('WAVE_PAY_MINI_APP_MINI_APP_ID'),
            'secret_key' => $env->first('WAVE_PAY_MINI_APP_SECRET_KEY'),
            'base_url' => $env->first('WAVE_PAY_MINI_APP_BASE_URL'),
            'timeout_in_seconds' => $env->first('MYANMAR_PAYMENTS_HTTP_TIMEOUT'),
        ]);
    }
}
