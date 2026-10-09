<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\AyaPay;

use Laranex\PhpMyanmarPayments\Support\Json;

/**
 * AYA's checksums: HMAC-SHA256 with the app secret over values joined with ":", in an order fixed by the spec. Booleans sign as `true` / `false`.
 *
 * @internal
 */
final class AyaPaySigner
{
    /**
     * Field order of the decoded callback / enquiry payload. The payload spells `currenyCode` this way.
     * AYA leaves out fields that do not apply (e.g. card fields for wallet payments) and signs only the ones present.
     */
    private const PAYLOAD_FIELDS = [
        'merchOrderId', 'tranId', 'amount', 'currencyCode', 'statusCode',
        'paymentCardNumber', 'paymentMobileNumber', 'cardTypeName', 'cardExpiryDate', 'nameOnCard',
        'approvalCode', 'tranRef', 'userRef1', 'userRef2', 'userRef3', 'userRef4', 'userRef5',
        'description', 'dateTime',
    ];

    public function __construct(private readonly string $appSecret) {}

    /**
     * Standard base64, either correctly padded or without padding. Anything else (partial padding, the URL-safe
     * alphabet, whitespace) is rejected, and so is a payload that is not UTF-8.
     */
    private static function decodeBase64(string $value): ?string
    {
        if (preg_match('/^[A-Za-z0-9+\/]+={0,2}\z/', $value) !== 1) {
            return null;
        }

        $padded = str_contains($value, '=');

        if (($padded && strlen($value) % 4 !== 0) || (! $padded && strlen($value) % 4 === 1)) {
            return null;
        }

        $decoded = base64_decode($value, true);

        return $decoded === false || preg_match('//u', $decoded) !== 1 ? null : $decoded;
    }

    /**
     * @param  list<scalar|null>  $values
     */
    public function checksum(array $values): string
    {
        return hash_hmac('sha256', implode(':', array_map(Json::scalarString(...), $values)), $this->appSecret);
    }

    /**
     * Decode a `payload` + `checkSum` pair and return the payload if the checksum matches.
     *
     * @return array<string, mixed>|null
     */
    public function verifyPayload(string $payload, string $checkSum): ?array
    {
        $json = self::decodeBase64($payload);
        $decoded = $json === null ? null : Json::decode($json);

        if ($decoded === null) {
            return null;
        }

        $values = [];

        foreach (self::PAYLOAD_FIELDS as $field) {
            $key = $field === 'currencyCode' && array_key_exists('currenyCode', $decoded) ? 'currenyCode' : $field;

            if (! array_key_exists($key, $decoded)) {
                continue;
            }

            if (Json::isNested($decoded[$key])) {
                return null;
            }

            $values[] = $decoded[$key];
        }

        return hash_equals($this->checksum($values), strtolower($checkSum)) ? $decoded : null;
    }
}
