<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\WaveMoney;

use Laranex\PhpMyanmarPayments\Amount;
use Laranex\PhpMyanmarPayments\Exceptions\InvalidPaymentDataException;
use Laranex\PhpMyanmarPayments\Support\Validator;

/**
 * A Wave Money payment request.
 */
final class WaveMoneyPaymentData
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
     * @param  string  $orderId  Your order id. One order can have several payment attempts.
     * @param  string  $callbackUrl  URL that Wave posts the result to (`backend_result_url`).
     * @param  string  $returnUrl  URL Wave sends the customer back to (`frontend_result_url`). Not proof of payment.
     * @param  string  $description  Payment description shown to the customer.
     * @param  list<WaveMoneyItem>  $items  Line items shown on Wave's page.
     * @param  Amount|int|null  $amount  Total in whole kyat, e.g. `Amount::kyat(1000)`. Defaults to the sum of the items. Wave does not accept decimals.
     * @param  string|null  $merchantReferenceId  Unique id of this attempt. Defaults to a random id when null or empty.
     */
    public function __construct(
        public readonly string $orderId,
        public readonly string $callbackUrl,
        public readonly string $returnUrl,
        public readonly string $description,
        public readonly array $items,
        Amount|int|null $amount = null,
        ?string $merchantReferenceId = null,
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
            ->required('callbackUrl', $this->callbackUrl)
            ->url('callbackUrl', $this->callbackUrl)
            ->required('returnUrl', $this->returnUrl)
            ->url('returnUrl', $this->returnUrl)
            ->required('description', $this->description)
            ->when($this->items === [], 'items', 'The items field must have at least one item.')
            ->when(! array_is_list($this->items) || array_filter($this->items, fn (mixed $item): bool => ! $item instanceof WaveMoneyItem) !== [], 'items', 'The items field must be a list of '.WaveMoneyItem::class.'.');

        foreach (array_values($this->items) as $index => $item) {
            if ($item instanceof WaveMoneyItem) {
                $validator
                    ->required("items.{$index}.name", $item->name)
                    ->amount('Wave Money', $item->amount, maxDecimals: 0, field: "items.{$index}.amount");
            }
        }

        $validator
            ->amount('Wave Money', $this->amount, maxDecimals: 0)
            ->required('merchantReferenceId', $this->merchantReferenceId)
            ->validate();
    }

    /**
     * @param  array<array-key, mixed>  $items
     */
    private static function sumOf(array $items): Amount
    {
        $total = 0;

        foreach ($items as $item) {
            if ($item instanceof WaveMoneyItem) {
                $total += (int) $item->amount->wholePart();
            }
        }

        return Amount::kyat($total);
    }
}
