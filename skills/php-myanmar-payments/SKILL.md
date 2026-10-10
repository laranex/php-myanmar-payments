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

Use this skill when a PHP application takes payments through KBZ Pay, Wave Money, AYA Payment Gateway, Yoma MMQR or CyberSource. Start payments and verify callbacks with the package's typed API; never build gateway signatures by hand. In Laravel, use `laranex/laravel-myanmar-payments` instead; it wraps this package.

## Install

```bash
composer require laranex/php-myanmar-payments guzzlehttp/guzzle
```

Requires PHP 8.1+ and any PSR-18 HTTP client (Guzzle is one); the client is auto-discovered when you don't pass one. Each gateway lives in its own namespace: `Laranex\PhpMyanmarPayments\KbzPay`, `WaveMoney`, `AyaPay`, `YomaMmqr` and `CyberSource`.

## Configure

`MyanmarPayments::fromEnv()` reads `KBZ_PAY_*`, `WAVE_MONEY_*`, `AYA_PAY_*` (or `AYA_PGW_*`), `YOMA_MMQR_*` and `CYBER_SOURCE_*` and `MYANMAR_PAYMENTS_HTTP_TIMEOUT` (the same variables as the Go, Node and Python SDKs). There is no sandbox switch: every gateway uses its production URLs unless you set the URL overrides (`*_BASE_URL`, `KBZ_PAY_PWA_BASE_REDIRECT_URL`, `WAVE_MONEY_AUTHENTICATE_URL`) to the gateway's UAT URLs. Every other setting is required and has no default: the HTTP timeout in seconds (`MYANMAR_PAYMENTS_HTTP_TIMEOUT`, for every gateway except CyberSource), `WAVE_MONEY_MERCHANT_NAME`, `WAVE_MONEY_TIME_TO_LIVE_IN_SECONDS` and `YOMA_MMQR_API_VERSION` (e.g. `v1rc`).

```php
use Laranex\PhpMyanmarPayments\MyanmarPayments;

$payments = MyanmarPayments::fromEnv(); // create once, share across requests
$kbzPay = $payments->kbzPay(); // also waveMoney(), ayaPay(), yomaMmqr(), cyberSource()
```

- Or build one gateway: `new KbzPay(new KbzPayConfig(appId: '...', appKey: '...', merchantCode: '...', timeoutSeconds: 30))`, `KbzPay::fromEnv()` or `new KbzPay(KbzPayConfig::fromEnv())`.
- Or pass the settings directly: gateways and `new MyanmarPayments([...])` take config objects or snake_case arrays (`['kbz_pay' => ['app_id' => '...', 'app_key' => '...', 'merchant_code' => '...', 'timeout_in_seconds' => 30]]`).
- Options: a PSR-18 client (second argument of `KbzPay`, `WaveMoney`, `AyaPay`, `YomaMmqr` and `MyanmarPayments`; without one, Guzzle gets the configured `timeoutSeconds`; a client you pass keeps its own timeout). Yoma and the facade also take a PSR-16 cache for the access token (default: in-memory `ArrayCache`; pass a shared cache when you run several processes).
- A missing setting throws `ConfigurationException` (`gateway`, `key`); so does a time setting that is not a whole number greater than 0.

## Use

### Amounts

Amounts are `Amount::kyat(1000)`, `Amount::parse('1000.50')` or a whole `int`; never a float. Only KBZ Pay (up to 2 decimals) and CyberSource accept decimals; Wave, AYA and Yoma take whole kyat. Invalid data throws `InvalidPaymentDataException` with `errors()` per field, before any request is sent. Compare a gateway's amount by value with `$amount->equals($callback->amount)`.

### Start a payment

Each gateway takes a data object (`KbzPayPaymentData`, `WaveMoneyPaymentData` with `WaveMoneyItem`s, `AyaPayPaymentData` with an `AyaPayMethod`, `YomaMmqrPaymentData`, `CyberSourcePaymentData` with a `CyberSourceTransactionType`; its `currency`, `transactionType` and `locale` are required, e.g. `'MMK'`, `CyberSourceTransactionType::Sale` and `'en-us'`) and returns a typed result:

```php
use Laranex\PhpMyanmarPayments\KbzPay\KbzPayPaymentData;

$payment = $kbzPay->pwa(new KbzPayPaymentData(
    orderId: 'ORDER_1',
    amount: 1000,
    callbackUrl: 'https://shop.test/payments/kbz/callback',
));

header('Location: '.$payment->url);
```

- `RedirectPayment` (`url`) from `$kbzPay->pwa($data)` and `$waveMoney->initiate($data)`. Wave fills `$data->merchantReferenceId` when empty; store it.
- `FormPayment` from `$ayaPay->initiate($data)` and `$cyberSource->initiate($data)` (no network call): `echo $payment->toHtml()` for an auto-submitting page, or render `action`, `fields` and `enctype` yourself.
- `QrPayment` from `$kbzPay->qr($data)` (encode `qrString`) and `$yomaMmqr->initiate($data)` (`qrImageDataUri()`, `expiresAt`, `reference`). A Yoma QR lives `YomaMmqr::QR_LIFETIME_SECONDS` (120); renew it with `$yomaMmqr->renewQr($orderId)`.
- `AppPayment` from `$kbzPay->app($data)`: return `$payment->toArray()` (`orderId`, `orderInfo`, `sign`, `signType`) to your mobile app.

AYA needs a channel: `$ayaPay->services()` lists `AyaPayService` entries (`key`, `supports($method)`), then `$ayaPay->initiate(new AyaPayPaymentData('ORDER123', 1000, 'kbz_pay', AyaPayMethod::Qr))`.

### Handle the callback

Build a `CallbackRequest` from the raw request, verify it, then reply:

```php
use Laranex\PhpMyanmarPayments\Exceptions\SignatureVerificationException;
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;

try {
    $callback = $kbzPay->handleCallback(CallbackRequest::fromGlobals()); // or fromPsr7($request)
} catch (SignatureVerificationException) {
    http_response_code(400);
    exit('invalid signature');
}
if ($callback->isSuccessful()) {
    // compare $callback->amount with the order, then fulfill $callback->orderId once
}
$callback->acknowledgement->send(); // or copy its status, headers and body to your response
```

- Give the package the raw body: `fromGlobals()` reads `php://input` and `fromPsr7()` the PSR-7 body; disable CSRF protection for callback routes.
- Check AYA's browser return with `$ayaPay->verifyRedirect($request)`.
- For production, store the verified callback, acknowledge immediately, then process it once in the background.

### Check status and handle errors

- `$kbzPay->status($orderId)`, `$ayaPay->status($orderId)` and `$yomaMmqr->status($reference)` return `PaymentStatusResult` with `status` and `isSuccessful()`. Wave Money and CyberSource have no status API.
- Statuses: `PaymentStatus::Successful`, `Pending`, `Failed`, `Canceled`, `Expired`, `Unknown`; `$status->isFinal()`.
- Gateway failures throw `ApiException` (`gatewayCode`, `gatewayMessage`, `httpStatus`, `raw`; network errors are the previous exception). All errors extend `PaymentException`.

## Test your app

- Pass a mock PSR-18 client (for example `php-http/mock-client`) to the gateway or to `MyanmarPayments` and queue the gateway's JSON responses.
- Replay a stored callback with `CallbackRequest::fromArray($payload, $headers)` or `new CallbackRequest($body, $headers, $query)`; it is still signature-checked.
- To test your own fulfillment code, build `new PaymentCallback('ORDER_1', PaymentStatus::Successful, 'PAY_SUCCESS')` yourself.

## Avoid

- Fulfilling orders from return pages or query strings; fulfill only from a verified callback or a status check.
- Treating `Pending` or `Unknown` as paid.
- Passing floats or `(float) $price` as amounts; use `Amount::parse()`.
- Building the `CallbackRequest` from `$_POST` or a decoded array instead of the raw body.
- Reusing a Wave `merchantReferenceId`, or calling Yoma `initiate()` twice for one order (use `renewQr()`).
- Opening a KBZ Pay PWA link outside a phone with the KBZ Pay app.
