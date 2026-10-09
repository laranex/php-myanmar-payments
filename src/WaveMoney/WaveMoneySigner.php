<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\WaveMoney;

use Laranex\PhpMyanmarPayments\Support\Json;

/**
 * Wave's HMAC-SHA256 hashes: fixed field order, no separator, lowercase hex. Null becomes the string "null", booleans `true` / `false`.
 *
 * @internal
 */
final class WaveMoneySigner
{
    private const CALLBACK_FIELDS = [
        'status',
        'timeToLiveSeconds',
        'merchantId',
        'orderId',
        'amount',
        'backendResultUrl',
        'merchantReferenceId',
        'initiatorMsisdn',
        'transactionId',
        'paymentRequestId',
        'requestTime',
    ];

    public function __construct(private readonly string $secretKey) {}

    public function requestHash(int $timeToLive, string $merchantId, string $orderId, string $amount, string $backendResultUrl, string $merchantReferenceId): string
    {
        return $this->hash([$timeToLive, $merchantId, $orderId, $amount, $backendResultUrl, $merchantReferenceId]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function callbackHash(array $payload): string
    {
        return $this->hash(array_map(fn (string $field): mixed => $payload[$field] ?? null, self::CALLBACK_FIELDS));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyCallback(array $payload): bool
    {
        foreach (self::CALLBACK_FIELDS as $field) {
            if (isset($payload[$field]) && ! is_scalar($payload[$field])) {
                return false;
            }
        }

        return is_string($payload['hashValue'] ?? null) && hash_equals($this->callbackHash($payload), strtolower($payload['hashValue']));
    }

    /**
     * @param  list<mixed>  $values
     */
    private function hash(array $values): string
    {
        return hash_hmac('sha256', implode('', array_map(fn (mixed $value): string => $value === null ? 'null' : Json::scalarString($value), $values)), $this->secretKey);
    }
}
