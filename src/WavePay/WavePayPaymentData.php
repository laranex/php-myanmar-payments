<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\WavePay;

use Laranex\PhpMyanmarPayments\Amount;
use Laranex\PhpMyanmarPayments\Exceptions\InvalidPaymentDataException;
use Laranex\PhpMyanmarPayments\Support\Validator;

/**
 * A Wave Pay payment request.
 */
final class WavePayPaymentData
{
    /**
     * Total charged, in whole kyat.
     */
    public readonly Amount $amount;

    /**
     * Unique id of this payment attempt. Wave rejects a reused one, so retries of the same order need a new one.
     * Store it: Wave's callback may omit `orderId` but always carries this.
     */
    public readonly string $merchantReferenceId;

    /**
     * @param  string  $orderId  Your order id, at most 100 characters: letters, numbers, `_` and `-`. One order can have several payment attempts.
     * @param  string  $callbackUrl  URL that Wave posts the result to (`backend_result_url`), at most 255 characters.
     * @param  string  $returnUrl  URL Wave sends the customer back to (`frontend_result_url`), at most 255 characters. Not proof of payment.
     * @param  string  $description  Payment description shown to the customer (`payment_description`), at most 255 characters.
     * @param  list<WavePayItem>  $items  Line items shown on Wave's page.
     * @param  Amount|int|null  $amount  Total in whole kyat, e.g. `Amount::kyat(1000)`. Defaults to the sum of the items. Wave does not accept decimals.
     * @param  string|null  $merchantReferenceId  Unique id of this attempt, at most 100 characters: letters, numbers, `_` and `-`. Defaults to a random id when null or empty.
     * @param  string|null  $merchantName  Business name shown on Wave's page for this payment. Defaults to the configured `merchant_name`.
     * @param  int|null  $timeToLiveSeconds  How long the customer has to pay this payment, greater than 0. Overrides the configured `time_to_live_in_seconds` for this payment.
     * @param  string|null  $additionalField3  Extra information sent as `additional_field_3`, at most 50 characters; Wave echoes it as the callback's `additionalField3`. Not sent when null or empty.
     * @param  string|null  $additionalField4  Extra information sent as `additional_field_4`, at most 50 characters; echoed as `additionalField4`. Not sent when null or empty.
     * @param  string|null  $additionalField5  Extra information sent as `additional_field_5`, at most 50 characters; echoed as `additionalField5`. Not sent when null or empty.
     */
    public function __construct(
        public readonly string $orderId,
        public readonly string $callbackUrl,
        public readonly string $returnUrl,
        public readonly string $description,
        public readonly array $items,
        Amount|int|null $amount = null,
        ?string $merchantReferenceId = null,
        public readonly ?string $merchantName = null,
        public readonly ?int $timeToLiveSeconds = null,
        public readonly ?string $additionalField3 = null,
        public readonly ?string $additionalField4 = null,
        public readonly ?string $additionalField5 = null,
    ) {
        $this->amount = $amount === null ? self::sumOf($items) : Amount::from($amount);
        $this->merchantReferenceId = $merchantReferenceId === null || $merchantReferenceId === '' ? bin2hex(random_bytes(16)) : $merchantReferenceId;

        $this->validate();
    }

    /**
     * Check the data against the gateway's documented rules again. The constructor already does.
     *
     * @throws InvalidPaymentDataException
     */
    public function validate(): void
    {
        $validator = (new Validator)
            ->required('orderId', $this->orderId)
            ->max('orderId', $this->orderId, 100)
            ->pattern('orderId', $this->orderId, '/^[A-Za-z0-9_-]+\z/', 'letters, numbers, underscores and dashes')
            ->required('callbackUrl', $this->callbackUrl)
            ->url('callbackUrl', $this->callbackUrl)
            ->max('callbackUrl', $this->callbackUrl, 255)
            ->required('returnUrl', $this->returnUrl)
            ->url('returnUrl', $this->returnUrl)
            ->max('returnUrl', $this->returnUrl, 255)
            ->required('description', $this->description)
            ->max('description', $this->description, 255)
            ->when($this->items === [], 'items', 'The items field must have at least one item.')
            ->when(! array_is_list($this->items) || array_filter($this->items, fn (mixed $item): bool => ! $item instanceof WavePayItem) !== [], 'items', 'The items field must be a list of '.WavePayItem::class.'.');

        foreach (array_values($this->items) as $index => $item) {
            if ($item instanceof WavePayItem) {
                $validator
                    ->required("items.{$index}.name", $item->name)
                    ->amount('Wave Pay', $item->amount, maxDecimals: 0, field: "items.{$index}.amount");
            }
        }

        $validator
            ->amount('Wave Pay', $this->amount, maxDecimals: 0)
            ->required('merchantReferenceId', $this->merchantReferenceId)
            ->max('merchantReferenceId', $this->merchantReferenceId, 100)
            ->pattern('merchantReferenceId', $this->merchantReferenceId, '/^[A-Za-z0-9_-]+\z/', 'letters, numbers, underscores and dashes')
            ->when($this->merchantName !== null && trim($this->merchantName) === '', 'merchantName', 'The merchantName field is required.')
            ->when($this->timeToLiveSeconds !== null && $this->timeToLiveSeconds < 1, 'timeToLiveSeconds', 'The timeToLiveSeconds field must be greater than 0.')
            ->max('additionalField3', $this->additionalField3, 50)
            ->max('additionalField4', $this->additionalField4, 50)
            ->max('additionalField5', $this->additionalField5, 50)
            ->validate();
    }

    /**
     * @param  array<array-key, mixed>  $items
     */
    private static function sumOf(array $items): Amount
    {
        $total = 0;

        foreach ($items as $item) {
            if ($item instanceof WavePayItem) {
                $total += (int) $item->amount->wholePart();
            }
        }

        return Amount::kyat($total);
    }
}
