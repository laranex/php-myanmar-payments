<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Results;

use JsonSerializable;
use Laranex\PhpMyanmarPayments\Amount;
use Laranex\PhpMyanmarPayments\Enums\PaymentFlow;

/**
 * Pass `toArray()` (or `toJson()`) to your mini app page, which hands it unchanged to the wallet's JavaScript SDK
 * (Wave Pay Mini App: `WaveJsSDK.paymentModule.makePayment(payload)`). No request is sent to the gateway.
 */
final class MiniAppPayment implements JsonSerializable, PaymentResult
{
    /**
     * @param  string  $orderId  Your order id (`orderID`).
     * @param  Amount  $amount  The signed total, in whole units.
     * @param  string  $miniAppId  The configured mini app id (`miniAppID`).
     * @param  string  $merchantReferenceId  Your reference for this attempt (`merchantRefID`).
     * @param  string  $callbackUrl  The signed callback URL (`callBackUrl`).
     * @param  string  $hashValue  The payload's hash (`hashValue`).
     * @param  list<array{name: string, amount: Amount}>  $items  The line items.
     */
    public function __construct(
        public readonly string $orderId,
        public readonly Amount $amount,
        public readonly string $miniAppId,
        public readonly string $merchantReferenceId,
        public readonly string $callbackUrl,
        public readonly string $hashValue,
        public readonly array $items,
    ) {}

    public function flow(): PaymentFlow
    {
        return PaymentFlow::MiniApp;
    }

    /**
     * Exactly the object `makePayment` takes, with amounts as integers. An amount above `PHP_INT_MAX` can't be an
     * integer here: use `toJson()`, which writes every digit.
     *
     * @return array{amount: int, miniAppID: string, merchantRefID: string, orderID: string, callBackUrl: string, hashValue: string, items: list<array{name: string, amount: int}>}
     */
    public function toArray(): array
    {
        return [
            'amount' => (int) $this->amount->wholePart(),
            'miniAppID' => $this->miniAppId,
            'merchantRefID' => $this->merchantReferenceId,
            'orderID' => $this->orderId,
            'callBackUrl' => $this->callbackUrl,
            'hashValue' => $this->hashValue,
            'items' => array_map(fn (array $item): array => ['name' => $item['name'], 'amount' => (int) $item['amount']->wholePart()], $this->items),
        ];
    }

    /**
     * The `makePayment` object as JSON, with the amounts as JSON integers holding every digit (no integer limit).
     */
    public function toJson(): string
    {
        $encode = fn (string $value): string => (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $items = array_map(fn (array $item): string => '{"name":'.$encode($item['name']).',"amount":'.$item['amount']->wholePart().'}', $this->items);

        return '{"amount":'.$this->amount->wholePart()
            .',"miniAppID":'.$encode($this->miniAppId)
            .',"merchantRefID":'.$encode($this->merchantReferenceId)
            .',"orderID":'.$encode($this->orderId)
            .',"callBackUrl":'.$encode($this->callbackUrl)
            .',"hashValue":'.$encode($this->hashValue)
            .',"items":['.implode(',', $items).']}';
    }

    /**
     * @return array{amount: int, miniAppID: string, merchantRefID: string, orderID: string, callBackUrl: string, hashValue: string, items: list<array{name: string, amount: int}>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
