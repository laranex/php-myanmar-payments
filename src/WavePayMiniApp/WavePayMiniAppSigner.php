<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\WavePayMiniApp;

/**
 * Wave's mini app hash (Wave Money JS SDK spec v1.8): lowercase hex HMAC-SHA256, keyed with the secret key, over
 * `<amount><orderID><miniAppID><merchantRefID><callBackUrl>` with no separator. The payment and its callback use the
 * same hash.
 */
final class WavePayMiniAppSigner
{
    public function __construct(private readonly string $secretKey) {}

    /**
     * @param  string  $amount  The amount's exact whole-number text, e.g. `"5000"`.
     */
    public function hash(string $amount, string $orderId, string $miniAppId, string $merchantReferenceId, string $callbackUrl): string
    {
        return hash_hmac('sha256', $amount.$orderId.$miniAppId.$merchantReferenceId.$callbackUrl, $this->secretKey);
    }

    /**
     * Whether `$hashValue` is the expected hash, compared in constant time and ignoring the case of the hex digits.
     */
    public function verify(string $hashValue, string $amount, string $orderId, string $miniAppId, string $merchantReferenceId, string $callbackUrl): bool
    {
        return hash_equals($this->hash($amount, $orderId, $miniAppId, $merchantReferenceId, $callbackUrl), strtolower($hashValue));
    }
}
