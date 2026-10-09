<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\WaveMoney;

use Laranex\PhpMyanmarPayments\Support\Config;

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
        $this->timeToLiveSeconds = $timeToLiveSeconds > 0 ? $timeToLiveSeconds : 300;
        $this->baseUrl = rtrim($baseUrl ?? ($sandbox ? self::SANDBOX_URL : self::PRODUCTION_URL), '/');
        $this->authenticateUrl = rtrim($authenticateUrl ?? ($sandbox ? self::SANDBOX_AUTHENTICATE_URL : self::PRODUCTION_AUTHENTICATE_URL), '/');
    }

    /**
     * @param  array{merchant_id?: string, secret_key?: string, merchant_name?: string, time_to_live_in_seconds?: int|string, sandbox?: bool|string, base_url?: string, authenticate_url?: string}  $config
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
}
