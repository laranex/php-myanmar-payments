<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\CyberSource;

use Laranex\PhpMyanmarPayments\Contracts\PaymentGateway;
use Laranex\PhpMyanmarPayments\Enums\PaymentStatus;
use Laranex\PhpMyanmarPayments\Exceptions\SignatureVerificationException;
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;
use Laranex\PhpMyanmarPayments\Results\FormPayment;
use Laranex\PhpMyanmarPayments\Results\PaymentCallback;
use Laranex\PhpMyanmarPayments\Support\StatusMap;

/**
 * CyberSource Secure Acceptance hosted checkout for card payments.
 */
class CyberSource implements PaymentGateway
{
    private const SIGNED_FIELDS = [
        'access_key', 'profile_id', 'transaction_uuid', 'signed_field_names', 'signed_date_time', 'locale',
        'transaction_type', 'reference_number', 'amount', 'currency',
        'override_custom_receipt_page', 'override_backoffice_post_url', 'override_custom_cancel_page',
    ];

    private const STATUSES = [
        'ACCEPT' => PaymentStatus::Successful,
        'REVIEW' => PaymentStatus::Pending,
        'DECLINE' => PaymentStatus::Failed,
        'ERROR' => PaymentStatus::Failed,
        'CANCEL' => PaymentStatus::Cancelled,
    ];

    public function __construct(public readonly CyberSourceConfig $config) {}

    /**
     * Sign the payment fields. The customer's browser must POST the returned form to CyberSource.
     */
    public function initiate(CyberSourcePaymentData $data): FormPayment
    {
        $fields = [
            'access_key' => $this->config->accessKey,
            'profile_id' => $this->config->profileId,
            'transaction_uuid' => bin2hex(random_bytes(16)),
            'signed_field_names' => implode(',', self::SIGNED_FIELDS),
            'signed_date_time' => gmdate('Y-m-d\TH:i:s\Z'),
            'locale' => $data->locale,
            'transaction_type' => $data->transactionType->value,
            'reference_number' => $data->orderId,
            'amount' => $data->amount->toString(),
            'currency' => $data->currency,
            'override_custom_receipt_page' => (string) $data->returnUrl,
            'override_backoffice_post_url' => $data->callbackUrl,
            'override_custom_cancel_page' => (string) $data->cancelUrl,
        ];

        $fields['signature'] = (string) $this->sign($fields);

        return new FormPayment(
            orderId: $data->orderId,
            action: "{$this->config->baseUrl}/pay",
            fields: $fields,
        );
    }

    /**
     * Verify CyberSource's result post. The same check works for the browser post to your receipt page.
     *
     * Only the fields listed in `signed_field_names` are read, so unsigned extra fields can't change the result.
     *
     * @throws SignatureVerificationException
     */
    public function handleCallback(CallbackRequest $request): PaymentCallback
    {
        $payload = $request->input();
        $expected = $this->sign($payload);

        if ($expected === null || ! is_string($payload['signature'] ?? null) || ! hash_equals($expected, $payload['signature'])) {
            throw new SignatureVerificationException('CyberSource callback signature verification failed.', $payload);
        }

        $signed = array_intersect_key($payload, array_flip($this->signedFieldNames($payload)));
        $decision = strtoupper(trim((string) ($signed['decision'] ?? '')));

        return new PaymentCallback(
            orderId: (string) ($signed['req_reference_number'] ?? ''),
            status: StatusMap::resolve(self::STATUSES, $decision),
            gatewayStatus: $decision,
            gatewayReference: isset($signed['transaction_id']) ? (string) $signed['transaction_id'] : null,
            amount: $this->amount($signed),
            raw: $signed + ['signature' => $payload['signature']],
        );
    }

    /**
     * Sign the fields listed in `signed_field_names`. Returns null when a listed field is missing.
     *
     * @param  array<string, mixed>  $fields
     */
    private function sign(array $fields): ?string
    {
        $names = $this->signedFieldNames($fields);

        if ($names === []) {
            return null;
        }

        $pairs = [];

        foreach ($names as $name) {
            if (! array_key_exists($name, $fields) || ! is_scalar($fields[$name])) {
                return null;
            }

            $pairs[] = $name.'='.$fields[$name];
        }

        return base64_encode(hash_hmac('sha256', implode(',', $pairs), $this->config->secretKey, true));
    }

    /**
     * `auth_amount`, falling back to `req_amount` when it is missing or empty.
     *
     * @param  array<array-key, mixed>  $fields
     */
    private function amount(array $fields): ?string
    {
        foreach (['auth_amount', 'req_amount'] as $field) {
            if (isset($fields[$field]) && (string) $fields[$field] !== '') {
                return (string) $fields[$field];
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $fields
     * @return list<string>
     */
    private function signedFieldNames(array $fields): array
    {
        $names = $fields['signed_field_names'] ?? null;

        return is_string($names) ? array_values(array_filter(explode(',', $names), fn (string $name): bool => $name !== '')) : [];
    }
}
