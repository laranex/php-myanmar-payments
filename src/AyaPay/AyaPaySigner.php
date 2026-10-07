<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\AyaPay;

/**
 * AYA's checksums: HMAC-SHA256 with the app secret over values joined with ":", in an order fixed by the spec.
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
     * @param  list<scalar|null>  $values
     */
    public function checksum(array $values): string
    {
        return hash_hmac('sha256', implode(':', array_map(fn (mixed $value): string => (string) $value, $values)), $this->appSecret);
    }

    /**
     * Decode a `payload` + `checkSum` pair and return the payload if the checksum matches.
     *
     * @return array<string, mixed>|null
     */
    public function verifyPayload(string $payload, string $checkSum): ?array
    {
        $json = base64_decode($payload, true);
        $decoded = $json === false ? null : json_decode($json, true);

        if (! is_array($decoded)) {
            return null;
        }

        $values = [];

        foreach (self::PAYLOAD_FIELDS as $field) {
            $key = $field === 'currencyCode' && array_key_exists('currenyCode', $decoded) ? 'currenyCode' : $field;

            if (array_key_exists($key, $decoded)) {
                $values[] = is_scalar($decoded[$key]) ? $decoded[$key] : null;
            }
        }

        return hash_equals($this->checksum($values), strtolower($checkSum)) ? $decoded : null;
    }
}
