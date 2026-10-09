---
name: php-myanmar-payments
description: >
  Integrate Myanmar payment gateways (KBZ Pay, Wave Money, AYA Pay, Yoma MMQR, CyberSource) in plain PHP or any non-Laravel framework with laranex/php-myanmar-payments.
license: MIT
metadata:
  author: Nay Thu Khant
---

# PHP Myanmar Payments

## When to use

Use this skill when a PHP application that is not Laravel (plain PHP, Symfony, Slim, WordPress, ...) takes payments through KBZ Pay, Wave Money, AYA Payment Gateway, Yoma MMQR or CyberSource. Start payments and verify callbacks with the package's typed API; never build gateway signatures by hand. In Laravel, use `laranex/laravel-myanmar-payments` instead; it wraps this package.

## Install

```bash
composer require laranex/php-myanmar-payments guzzlehttp/guzzle
```

Requires PHP 8.1+ and any PSR-18 HTTP client (Guzzle is one); the client is auto-discovered when you don't pass one.

## Configure

Build one gateway from its config object. Every config defaults to the sandbox; pass `sandbox: false` in production.

```php
use Laranex\PhpMyanmarPayments\KbzPay\KbzPay;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPayConfig;

$kbzPay = new KbzPay(new KbzPayConfig(appId: '...', appKey: '...', merchantCode: '...', sandbox: true));
```

- `WaveMoney` with `WaveMoneyConfig(merchantId:, secretKey:, merchantName:)`
- `AyaPay` with `AyaPayConfig(appKey:, appSecret:)`
- `YomaMmqr` with `YomaMmqrConfig(merchantId:, clientId:, clientSecret:, webhookHashKey:)`; its third constructor argument is a PSR-16 cache for the access token (default: in-memory `ArrayCache`, so pass a shared cache in production)
- `CyberSource` with `CyberSourceConfig(profileId:, accessKey:, secretKey:)` (no HTTP client)

The second constructor argument of `KbzPay`, `WaveMoney`, `AyaPay` and `YomaMmqr` is an optional PSR-18 client.

Or build every gateway from one snake_case array:

```php
use Laranex\PhpMyanmarPayments\MyanmarPayments;

$payments = new MyanmarPayments([
    'kbz_pay' => ['app_id' => '...', 'app_key' => '...', 'merchant_code' => '...', 'sandbox' => true],
], $psr18Client, $psr16Cache);

$kbzPay = $payments->kbzPay(); // also waveMoney(), ayaPay(), yomaMmqr(), cyberSource()
```

A missing key throws `ConfigurationException`.

## Use

### Amounts

Pass an `int` (whole kyat) or an `Amount` (`Amount::kyat(1000)`, `Amount::parse('1000.50')`), never a float. Only KBZ Pay (up to 2 decimals) and CyberSource accept decimals; Wave, AYA and Yoma take whole kyat. Invalid data throws `InvalidPaymentDataException`; read the messages with `errors()`.

### Start a payment

Each gateway takes a data object (`KbzPayPaymentData`, `WaveMoneyPaymentData` with `WaveMoneyItem`s, `AyaPayPaymentData` with an `AyaPayMethod`, `YomaMmqrPaymentData`, `CyberSourcePaymentData` with a `CyberSourceTransactionType`) and returns a typed result:

```php
use Laranex\PhpMyanmarPayments\KbzPay\KbzPayPaymentData;

$payment = $kbzPay->pwa(new KbzPayPaymentData('ORDER_1', 1000, 'https://shop.test/kbz/callback'));

header('Location: '.$payment->url);
```

- `RedirectPayment` from `KbzPay::pwa()` and `WaveMoney::initiate()`: redirect to `$payment->url`. For Wave, store `$data->merchantReferenceId` with the order.
- `FormPayment` from `AyaPay::initiate()` and `CyberSource::initiate()`: `echo $payment->toHtml()` for an auto-submitting page, or render `action` and `fields` yourself.
- `QrPayment` from `KbzPay::qr()` (encode `qrString`) and `YomaMmqr::initiate()` (`qrImage` as base64, `qrImageDataUri()`, `expiresAt`, `reference`). A Yoma QR lives `YomaMmqr::QR_LIFETIME_SECONDS` (120); renew it with `renewQr($orderId)`.
- `AppPayment` from `KbzPay::app()`: return `$payment->toArray()` to the mobile app.

AYA Pay needs a channel: list them with `$ayaPay->services()` (each `AyaPayService` has `key` and `supports(AyaPayMethod $method)`), then `$ayaPay->initiate(new AyaPayPaymentData('ORDER123', 1000, 'kbz_pay', AyaPayMethod::Qr))`. The order id must be 6 to 40 characters.

### Handle the callback

Wrap the incoming request, verify it, then reply:

```php
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;

$callback = $kbzPay->handleCallback(CallbackRequest::fromGlobals()); // or CallbackRequest::fromPsr7($request)

if ($callback->isSuccessful()) {
    // compare $callback->amount with the order, then fulfill $callback->orderId once
}

$callback->acknowledgement()->send(); // or copy its status, headers and body to your PSR-7 response
```

`handleCallback()` throws `SignatureVerificationException` when the signature is wrong. Check AYA's browser return with `$ayaPay->verifyRedirect($request)`. Disable CSRF protection for callback routes.

### Check status and handle errors

- `KbzPay::status($orderId)`, `AyaPay::status($orderId)` and `YomaMmqr::status($reference)` return a `PaymentStatusResult` with `status` and `isSuccessful()`.
- Statuses are the `PaymentStatus` enum: `Successful`, `Pending`, `Failed`, `Canceled`, `Expired`, `Unknown`.
- Gateway errors throw `ApiException` (`gatewayCode`, `gatewayMessage`, `httpStatus`, `raw`); catch `PaymentException` for every package error.

## Test your app

- Pass a mock PSR-18 client (for example `php-http/mock-client`) to the gateway constructor or to `MyanmarPayments` and queue the gateway's JSON responses.
- Replay a stored callback with `CallbackRequest::fromArray($payload, $headers)`; it is still signature-checked, so use a payload the gateway actually signed.
- To test your own fulfillment code, build a `PaymentCallback` yourself (`new PaymentCallback('ORDER_1', PaymentStatus::Successful, 'PAY_SUCCESS')`) instead of calling the gateway.

## Avoid

- Fulfilling orders from return pages or query strings; fulfill only from a verified callback or a status check.
- Treating `PaymentStatus::Pending` or `Unknown` as paid.
- Passing floats as amounts.
- Reusing a Wave `merchantReferenceId` (it must be unique per attempt), or calling Yoma `initiate()` twice for the same order (use `renewQr()`).
- Opening a KBZ Pay PWA link outside a phone with the KBZ Pay app.
