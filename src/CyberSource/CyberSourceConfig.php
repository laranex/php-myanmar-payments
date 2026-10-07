<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\CyberSource;

use Laranex\PhpMyanmarPayments\Support\Config;

/**
 * Credentials and endpoints for CyberSource Secure Acceptance (hosted checkout).
 */
final class CyberSourceConfig
{
    public const SANDBOX_URL = 'https://testsecureacceptance.cybersource.com';

    public const PRODUCTION_URL = 'https://secureacceptance.cybersource.com';

    public readonly string $baseUrl;

    /**
     * @param  string  $profileId  The Secure Acceptance profile id.
     * @param  string  $accessKey  The profile's access key.
     * @param  string  $secretKey  The profile's secret key, used to sign fields.
     * @param  bool  $sandbox  Use the test environment instead of production.
     * @param  string|null  $baseUrl  Override the Secure Acceptance base URL.
     */
    public function __construct(
        public readonly string $profileId,
        public readonly string $accessKey,
        public readonly string $secretKey,
        public readonly bool $sandbox = true,
        ?string $baseUrl = null,
    ) {
        $this->baseUrl = rtrim($baseUrl ?? ($sandbox ? self::SANDBOX_URL : self::PRODUCTION_URL), '/');
    }

    /**
     * @param  array{profile_id?: string, access_key?: string, secret_key?: string, sandbox?: bool|string, base_url?: string}  $config
     */
    public static function fromArray(array $config): self
    {
        $config = new Config('cyber_source', $config);

        return new self(
            profileId: $config->required('profile_id'),
            accessKey: $config->required('access_key'),
            secretKey: $config->required('secret_key'),
            sandbox: $config->bool('sandbox', true),
            baseUrl: $config->string('base_url'),
        );
    }
}
