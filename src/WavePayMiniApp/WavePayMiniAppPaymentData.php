<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\WavePayMiniApp;

use Laranex\PhpMyanmarPayments\Amount;
use Laranex\PhpMyanmarPayments\Exceptions\InvalidPaymentDataException;
use Laranex\PhpMyanmarPayments\Support\Validator;

/**
 * A Wave Pay Mini App payment: the values signed into Wave's `makePayment` payload.
 */
final class WavePayMiniAppPaymentData
{
    /**
     * Total charged, in whole kyat.
     */
    public readonly Amount $amount;

    /**
     * Your reference for this payment attempt (`merchantRefID`). Store it: the callback's `merchantRefId` names the
     * attempt that was paid.
     */
    public readonly string $merchantReferenceId;

    private readonly bool $amountGiven;

    private readonly bool $negativeAmount;

    /**
     * The exact sum of the item amounts in digits, when `$amount` was not given and every item amount is valid.
     */
    private readonly ?string $itemsTotal;

    /**
     * @param  string  $orderId  Your order id (`orderID`).
     * @param  string  $callbackUrl  URL that Wave posts the result to (`callBackUrl`). It is signed into the hash, so
     *                               verify the callback with this same URL.
     * @param  list<WavePayMiniAppItem>  $items  Line items, at least one.
     * @param  Amount|int|null  $amount  Total in whole kyat, e.g. `Amount::kyat(5000)`. Defaults to the exact sum of the items. Wave does not accept decimals.
     * @param  string|null  $merchantReferenceId  Your reference for this attempt (`merchantRefID`). Defaults to a random 32-character hex id when null or empty.
     *
     * @throws InvalidPaymentDataException With every invalid field, checked in this order: `orderId`, `callbackUrl`,
     *                                     `items`, `merchantReferenceId`, each item's name and amount, then `amount`.
     *                                     A negative amount is reported here too.
     */
    public function __construct(
        public readonly string $orderId,
        public readonly string $callbackUrl,
        public readonly array $items,
        Amount|int|null $amount = null,
        ?string $merchantReferenceId = null,
    ) {
        $this->amountGiven = $amount !== null;
        $this->negativeAmount = is_int($amount) && $amount < 0;
        $this->itemsTotal = $amount === null ? self::sumOf($items) : null;
        $this->amount = match (true) {
            $this->negativeAmount, ! $this->amountGiven && $this->itemsTotal === null => Amount::kyat(0),
            $amount === null => Amount::parse((string) $this->itemsTotal),
            default => Amount::from($amount),
        };
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
            ->when($this->items === [], 'items', 'The items field must have at least one item.')
            ->when(! array_is_list($this->items) || array_filter($this->items, fn (mixed $item): bool => ! $item instanceof WavePayMiniAppItem) !== [], 'items', 'The items field must be a list of '.WavePayMiniAppItem::class.'.')
            ->required('merchantReferenceId', $this->merchantReferenceId);

        foreach (array_values($this->items) as $index => $item) {
            if ($item instanceof WavePayMiniAppItem) {
                $validator
                    ->required("items.{$index}.name", $item->name)
                    ->negative("items.{$index}.amount", $item->negativeAmount)
                    ->amount('Wave Pay Mini App', $item->amount, maxDecimals: 0, field: "items.{$index}.amount");
            }
        }

        if ($this->amountGiven) {
            $validator
                ->negative('amount', $this->negativeAmount)
                ->amount('Wave Pay Mini App', $this->amount, maxDecimals: 0);
        } elseif ($this->items === []) {
            $validator->when(true, 'amount', 'The amount field is required.');
        } elseif ($this->itemsTotal !== null) {
            $validator->amount('Wave Pay Mini App', $this->amount, maxDecimals: 0);
        }

        $validator->validate();
    }

    /**
     * The exact sum of the item amounts, or null when an item is not a `WavePayMiniAppItem` or its amount is negative
     * or has decimals (that item's own error is reported instead).
     *
     * @param  array<array-key, mixed>  $items
     */
    private static function sumOf(array $items): ?string
    {
        $total = '0';

        foreach ($items as $item) {
            if (! $item instanceof WavePayMiniAppItem || $item->negativeAmount || $item->amount->decimalPlaces() > 0) {
                return null;
            }

            $total = self::add($total, $item->amount->wholePart());
        }

        return $total;
    }

    /**
     * Add two non-negative whole numbers written as digits, without an integer limit.
     */
    private static function add(string $left, string $right): string
    {
        $sum = '';
        $carry = 0;

        for ($i = strlen($left) - 1, $j = strlen($right) - 1; $i >= 0 || $j >= 0 || $carry > 0; $i--, $j--) {
            $digit = ($i >= 0 ? (int) $left[$i] : 0) + ($j >= 0 ? (int) $right[$j] : 0) + $carry;
            $sum = ($digit % 10).$sum;
            $carry = intdiv($digit, 10);
        }

        return $sum;
    }
}
