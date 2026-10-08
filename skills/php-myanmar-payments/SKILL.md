---
name: php-myanmar-payments
description: >
  Integrate Myanmar payment gateways (KBZ Pay, Wave Money, AYA Pay, Yoma MMQR, CyberSource) in plain PHP or any non-Laravel framework with laranex/php-myanmar-payments.
license: MIT
metadata:
  author: Nay Thu Khant
---

# PHP Myanmar Payments

Use this skill when a PHP application that is not Laravel (plain PHP, Symfony, Slim, WordPress, ...) takes payments through KBZ Pay, Wave Money, AYA Payment Gateway, Yoma MMQR or CyberSource. In Laravel, use `laranex/laravel-myanmar-payments` instead; it wraps this package.

## Primary Goal

- start payments and verify callbacks with the package's typed API, never with hand-built signatures

## Workflow

### 1. Install and build the gateway

- `composer require laranex/php-myanmar-payments` (v4: PHP 8.1+) plus any PSR-18 client, e.g. `guzzlehttp/guzzle`; the client is auto-discovered when none is passed
- build one gateway from its config: `new KbzPay(new KbzPayConfig(appId:, appKey:, merchantCode:, sandbox: true), $psr18Client)`; likewise `WaveMoney`/`WaveMoneyConfig`, `AyaPay`/`AyaPayConfig`, `YomaMmqr`/`YomaMmqrConfig` (third argument: a PSR-16 cache for its access token, default in-memory `ArrayCache`), `CyberSource`/`CyberSourceConfig` (no HTTP client)
- or build all of them from one snake_case array: `new MyanmarPayments(['kbz_pay' => ['app_id' => ..., 'app_key' => ..., 'merchant_code' => ..., 'sandbox' => true], ...], $client, $cache)` then `->kbzPay()`, `->waveMoney()`, `->ayaPay()`, `->yomaMmqr()`, `->cyberSource()`; a missing key throws `ConfigurationException`
- `sandbox: false` for production

### 2. Start a payment

- build the gateway's data object: `KbzPayPaymentData`, `WaveMoneyPaymentData` (with `WaveMoneyItem`s), `AyaPayPaymentData` (with `AyaPayMethod`), `YomaMmqrPaymentData`, `CyberSourcePaymentData` (with `CyberSourceTransactionType`); bad input throws `InvalidPaymentDataException` with `errors()`
- amounts are `int` (whole kyat) or `Amount` (`Amount::kyat(1000)`, `Amount::parse('1000.50')` from a string), never floats; only KBZ Pay (≤2 decimals) and CyberSource accept decimals, Wave, AYA and Yoma take whole kyat
- act on the typed result:
  - `RedirectPayment` (`KbzPay::pwa()`, `WaveMoney::initiate()`): `header('Location: '.$payment->url)`
  - `FormPayment` (`AyaPay::initiate()`, `CyberSource::initiate()`): `echo $payment->toHtml()` (auto-submitting page) or render `action` and `fields` yourself
  - `QrPayment`: KBZ (`qr()`) gives `qrString` to encode; Yoma (`initiate()`) gives `qrImage` (base64, `qrImageDataUri()`), `expiresAt` and `reference`
  - `AppPayment` (`KbzPay::app()`): return `toArray()` to the mobile app
- store the order id; for Wave also store `$data->merchantReferenceId`, for Yoma the QR `reference`

### 3. Handle the callback

- wrap the request: `CallbackRequest::fromGlobals()` in plain PHP, `CallbackRequest::fromPsr7($request)` in PSR-7 frameworks; disable CSRF for the route
- `$callback = $gateway->handleCallback($request)` verifies the signature and returns a `PaymentCallback`, or throws `SignatureVerificationException`; AYA's browser return is checked with `$ayaPay->verifyRedirect($request)`
- check `$callback->status` (`PaymentStatus`) or `isSuccessful()`, compare `$callback->amount` with the order, make fulfillment idempotent (gateways retry)
- reply with `$callback->acknowledgement()->send()` (or copy its `status`, `headers`, `body` to your PSR-7 response)

### 4. Check status and handle errors

- `KbzPay::status($orderId)`, `AyaPay::status($orderId)`, `YomaMmqr::status($reference)` return `PaymentStatusResult`
- gateway failures throw `ApiException` (`gatewayCode`, `gatewayMessage`, `httpStatus`, `raw`); catch `PaymentException` for all package errors

## Gateway Gotchas

- Wave: `merchantReferenceId` must be unique per attempt (default random); the callback URL must be HTTPS on port 443
- KBZ Pay PWA: works only on a phone with the KBZ Pay app, and the Referer must match the URL registered with KBZ; the acknowledgement is a plain `success`
- AYA: pick `channel` from `$ayaPay->services()` (`AyaPayService::$key`, check `supports($method)`); `orderId` is 6 to 40 characters
- Yoma: a QR lives 120 seconds (`YomaMmqr::QR_LIFETIME_SECONDS`); call `initiate()` once per order, then `renewQr($orderId)` after expiry

## Examples

- KBZ Pay PWA: `$kbzPay->pwa(new KbzPayPaymentData('ORDER_1', Amount::parse('1000.50'), 'https://shop.test/kbz/callback'))` then redirect to `$payment->url`
- AYA checkout: `$ayaPay->initiate(new AyaPayPaymentData('ORDER123', 1000, 'kbz_pay', AyaPayMethod::Qr))` then `echo $payment->toHtml()`

## Anti-patterns

- do not fulfill from return pages or query strings; fulfill from the verified callback or a status check
- do not pass floats or treat `PaymentStatus::Pending` / `Unknown` as paid
- do not reuse a Wave `merchantReferenceId` or re-run Yoma `initiate()` for the same order
