<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\CyberSource;

use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\Support\Config;
use Laranex\PhpMyanmarPayments\Support\Env;

/**
 * Credentials and endpoints for CyberSource Secure Acceptance (hosted checkout). Every setting is required;
 * `PRODUCTION_URL` names production's base URL.
 */
final class CyberSourceConfig
{
    public const PRODUCTION_URL = 'https://secureacceptance.cybersource.com';

    public readonly string $baseUrl;

    /**
     * @param  string  $profileId  The Secure Acceptance profile id.
     * @param  string  $accessKey  The profile's access key.
     * @param  string  $secretKey  The profile's secret key, used to sign fields.
     * @param  string  $baseUrl  The Secure Acceptance base URL, e.g. `PRODUCTION_URL` or `https://testsecureacceptance.cybersource.com`.
     *
     * @throws ConfigurationException When a setting is blank.
     */
    public function __construct(
        public readonly string $profileId,
        public readonly string $accessKey,
        public readonly string $secretKey,
        string $baseUrl,
    ) {
        Config::requireValue('cyber_source', 'profile_id', $profileId);
        Config::requireValue('cyber_source', 'access_key', $accessKey);
        Config::requireValue('cyber_source', 'secret_key', $secretKey);
        Config::requireValue('cyber_source', 'base_url', $baseUrl);

        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * @param  array{profile_id?: string|null, access_key?: string|null, secret_key?: string|null, base_url?: string|null}  $config
     *
     * @throws ConfigurationException When a setting is missing.
     */
    public static function fromArray(array $config): self
    {
        $config = new Config('cyber_source', $config);

        return new self(
            profileId: $config->required('profile_id'),
            accessKey: $config->required('access_key'),
            secretKey: $config->required('secret_key'),
            baseUrl: $config->required('base_url'),
        );
    }

    /**
     * Read `CYBER_SOURCE_PROFILE_ID`, `CYBER_SOURCE_ACCESS_KEY`, `CYBER_SOURCE_SECRET_KEY` and `CYBER_SOURCE_BASE_URL`.
     *
     * @param  array<array-key, mixed>|null  $env  Variables to read; defaults to `getenv()` merged with `$_ENV`.
     *
     * @throws ConfigurationException When a setting is missing.
     */
    public static function fromEnv(?array $env = null): self
    {
        $env = new Env($env);

        return self::fromArray([
            'profile_id' => $env->first('CYBER_SOURCE_PROFILE_ID'),
            'access_key' => $env->first('CYBER_SOURCE_ACCESS_KEY'),
            'secret_key' => $env->first('CYBER_SOURCE_SECRET_KEY'),
            'base_url' => $env->first('CYBER_SOURCE_BASE_URL'),
        ]);
    }
}
