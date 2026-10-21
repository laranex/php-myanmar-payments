---
name: php-myanmar-payments
description: >
  Integrate Myanmar payment gateways (KBZ Pay, Wave Pay, Wave Pay Mini App, AYA Pay, Yoma MMQR, CyberSource) in plain PHP or any non-Laravel framework with laranex/php-myanmar-payments.
license: MIT
metadata:
  author: Nay Thu Khant
---

# PHP Myanmar Payments

## When to use

Use this skill when a PHP application takes payments through KBZ Pay, Wave Pay, Wave Pay Mini App (a web page running inside the WavePay app), AYA Payment Gateway, Yoma MMQR or CyberSource. Start payments and verify callbacks with the package's typed API; never build gateway signatures by hand. In Laravel, use `laranex/laravel-myanmar-payments` instead; it wraps this package.

## Install

```bash
composer require laranex/php-myanmar-payments guzzlehttp/guzzle
```

Requires PHP 8.1+ and any PSR-18 HTTP client (Guzzle is one); the client is auto-discovered when you don't pass one. Each gateway lives in its own namespace: `Laranex\PhpMyanmarPayments\KbzPay`, `WavePay`, `WavePayMiniApp`, `AyaPay`, `YomaMmqr` and `CyberSource`.

## Configure

`MyanmarPayments::fromEnv()` reads `KBZ_PAY_*`, `WAVE_PAY_*`, `WAVE_PAY_MINI_APP_*`, `AYA_PAY_*`, `YOMA_MMQR_*`, `CYBER_SOURCE_*` and `MYANMAR_PAYMENTS_HTTP_TIMEOUT` (the same variables as the Go, Node and Python SDKs). Every setting of a gateway you use is required and has no default, except the HTTP timeout in seconds (`MYANMAR_PAYMENTS_HTTP_TIMEOUT`, for every gateway except CyberSource), which defaults to 30 (`MyanmarPayments::DEFAULT_TIMEOUT_SECONDS`). That includes the URLs (`KBZ_PAY_BASE_URL`, `KBZ_PAY_PWA_BASE_REDIRECT_URL`, `WAVE_PAY_BASE_URL`, `WAVE_PAY_BASE_REDIRECT_URL`, `WAVE_PAY_MINI_APP_BASE_URL`, `AYA_PAY_BASE_URL`, `YOMA_MMQR_BASE_URL`, `CYBER_SOURCE_BASE_URL`: production's or the gateway's UAT ones; the config classes' `PRODUCTION_*` constants name production's), the time to live (`KBZ_PAY_TIME_TO_LIVE_IN_MINUTES`, 1 to 120; `WAVE_PAY_TIME_TO_LIVE_IN_SECONDS`, greater than 0; a payment's `timeToLiveMinutes` / `timeToLiveSeconds` overrides them), `YOMA_MMQR_WEBHOOK_SECRET` (every Yoma callback must carry it in `X-Webhook-Secret`), `WAVE_PAY_MERCHANT_NAME`, `YOMA_MMQR_API_VERSION` (e.g. `v1rc`) and Wave Pay Mini App's `WAVE_PAY_MINI_APP_CLIENT_ID`, `WAVE_PAY_MINI_APP_CLIENT_SECRET`, `WAVE_PAY_MINI_APP_MINI_APP_ID` and `WAVE_PAY_MINI_APP_SECRET_KEY`.

```php
use Laranex\PhpMyanmarPayments\MyanmarPayments;

$payments = MyanmarPayments::fromEnv(); // create once, share across requests
$kbzPay = $payments->kbzPay(); // also wavePay(), wavePayMiniApp(), ayaPay(), yomaMmqr(), cyberSource()
```

- Or build one gateway: `new KbzPay(new KbzPayConfig(appId: '...', appKey: '...', merchantCode: '...', apiUrl: KbzPayConfig::PRODUCTION_API_URL, pwaUrl: KbzPayConfig::PRODUCTION_PWA_URL, timeToLiveMinutes: 15))`, `KbzPay::fromEnv()` or `new KbzPay(KbzPayConfig::fromEnv())`.
- Or pass the settings directly: gateways and `new MyanmarPayments([...])` take config objects or snake_case arrays (`['kbz_pay' => ['app_id' => '...', 'app_key' => '...', 'merchant_code' => '...', 'api_url' => '...', 'pwa_url' => '...', 'time_to_live_in_minutes' => 15]]`; add `timeout_in_seconds` to change the 30-second timeout).
- Options: a PSR-18 client (second argument of `KbzPay`, `WavePay`, `WavePayMiniApp`, `AyaPay`, `YomaMmqr` and `MyanmarPayments`; without one, Guzzle gets the configured `timeoutSeconds`, 30 by default; a client you pass keeps its own timeout). Yoma, Wave Pay Mini App and the facade also take a PSR-16 cache for the access token.
- Yoma MMQR and Wave Pay Mini App need a shared PSR-16 cache in production (`new YomaMmqr($config, $client, $cache)` or `MyanmarPayments::fromEnv(cache: $cache)`). The default in-memory `ArrayCache` dies with the PHP process, so a PHP-FPM app without one fetches a new access token on every request.
- A missing setting throws `ConfigurationException` (`gateway`, `key`); so does an invalid time setting (a whole number from 1 to 2147483647; `KBZ_PAY_TIME_TO_LIVE_IN_MINUTES` from 1 to 120). Array settings may be strings or integers (`'merchant_code' => 200123`); floats and booleans count as missing.

## Use

### Amounts

Amounts are `Amount::kyat(1000)`, `Amount::parse('1000.50')` or a whole `int`; never a float. Only KBZ Pay (up to 2 decimals) and CyberSource accept decimals; Wave Pay, Wave Pay Mini App, AYA and Yoma take whole kyat. Invalid data throws `InvalidPaymentDataException` with `errors()` per field (a negative amount included), before any request is sent. Compare a gateway's amount by value with `$amount->equals($callback->amount)`.

### Start a payment

Each gateway takes a data object (`KbzPayPaymentData`, `WavePayPaymentData` with `WavePayItem`s, `AyaPayPaymentData` with an `AyaPayMethod`, `YomaMmqrPaymentData`, `CyberSourcePaymentData` with a `CyberSourceTransactionType`; its `currency`, `transactionType` and `locale` are required, e.g. `'MMK'`, `CyberSourceTransactionType::Sale` and `'en-us'`) and returns a typed result:

```php
use Laranex\PhpMyanmarPayments\KbzPay\KbzPayPaymentData;

$payment = $kbzPay->pwa(new KbzPayPaymentData(
    orderId: 'ORDER_1',
    amount: 1000,
    callbackUrl: 'https://shop.test/payments/kbz/callback',
));

header('Location: '.$payment->url);
```

- `RedirectPayment` (`url`) from `$kbzPay->pwa($data)` and `$wavePay->initiate($data)`. Wave fills `$data->merchantReferenceId` when empty; store it. Wave's `orderId` and `merchantReferenceId` may only hold letters, numbers, `_` and `-` (at most 100 characters); `callbackUrl`, `returnUrl` and `description` take at most 255. Pass `merchantName` or `timeToLiveSeconds` to `WavePayPaymentData` to override the configured `WAVE_PAY_MERCHANT_NAME` or `WAVE_PAY_TIME_TO_LIVE_IN_SECONDS` for that one payment, and `additionalField3` to `additionalField5` (at most 50 characters each) for extra information Wave echoes in the callback.
- `FormPayment` from `$ayaPay->initiate($data)` and `$cyberSource->initiate($data)` (no network call): `echo $payment->toHtml()` for an auto-submitting page (`toHtml($nonce)` adds your Content-Security-Policy nonce to its inline script), or render `action`, `fields` and `enctype` yourself.
- `QrPayment` from `$kbzPay->qr($data)` (encode `qrString`) and `$yomaMmqr->initiate($data)` (`qrImageDataUri()`, `expiresAt`, `reference`). A Yoma QR lives `YomaMmqr::QR_LIFETIME_SECONDS` (120); renew it with `$yomaMmqr->renewQr($orderId)`.
- `AppPayment` from `$kbzPay->app($data)`: return `$payment->toArray()` (`orderId`, `orderInfo`, `sign`, `signType`) to your mobile app.
- `MiniAppPayment` from `$wavePayMiniApp->payment($data)` (no network call): return `$payment->toArray()` (or `toJson()` for amounts above `PHP_INT_MAX`) to your mini app page, which passes it unchanged to `WaveJsSDK.paymentModule.makePayment(payload)`. It is exactly Wave's object: `amount`, `miniAppID`, `merchantRefID`, `orderID`, `callBackUrl`, `hashValue`, `items`.

Wave Pay Mini App runs your web page inside the WavePay app. Your server gives the page `$wavePayMiniApp->accessToken()` (cached; the page calls `WaveJsSDK.authModule.loginWithAccessToken(token)`; never request the token from the browser, it needs the client secret), then signs the payment:

```php
use Laranex\PhpMyanmarPayments\WavePayMiniApp\WavePayMiniAppItem;
use Laranex\PhpMyanmarPayments\WavePayMiniApp\WavePayMiniAppPaymentData;

$payment = $payments->wavePayMiniApp()->payment(new WavePayMiniAppPaymentData(
    orderId: 'ORDER_1',
    callbackUrl: 'https://shop.test/payments/wave-pay-mini-app/callback',
    items: [new WavePayMiniAppItem('Ticket', 5000)], // amount defaults to the items' sum
));

echo json_encode($payment); // the page calls WaveJsSDK.paymentModule.makePayment(payload)
```

`merchantReferenceId` is generated when empty (32 hex characters); store it with the order.

AYA needs a channel: `$ayaPay->services()` lists `AyaPayService` entries (`key`, `supports($method)`), then `$ayaPay->initiate(new AyaPayPaymentData('ORDER123', 1000, 'kbz_pay', AyaPayMethod::Qr))`. AYA's `orderId`, `description` and `userRefs` must not contain `:`.

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

- Give the package the raw body: `fromGlobals()` reads `php://input` and `fromPsr7()` the PSR-7 body (both read the raw query string); disable CSRF protection for callback routes.
- `SignatureVerificationException` covers both a bad signature (`... verification failed.`) and a signed callback with malformed values (`... is malformed.`); answer both with a 400.
- Check AYA's browser return with `$ayaPay->verifyRedirect($request)`.
- Wave Pay Mini App callbacks carry neither the mini app id nor the callback URL, so pass the callback URL the payment was signed with: `$wavePayMiniApp->handleCallback(CallbackRequest::fromGlobals(), 'https://shop.test/payments/wave-pay-mini-app/callback')`. `successful` is `Successful`, `processing` is `Pending`, `failed` is `Failed`; `merchantRefId` is in `$callback->raw`. Wave expects an empty 200.
- Yoma MMQR callbacks sign only `orderNumber` and `status` and carry no amount (`$callback->amount` is null): match `orderNumber` to your stored order (its QR was generated for the stored amount) and, to confirm, call `$yomaMmqr->status($reference)` with that order's QR reference (`refLabel`). The status result has no amount either, so trust your stored amount.
- For production, store the verified callback, acknowledge immediately, then process it once in the background.

### Check status and handle errors

- `$kbzPay->status($orderId)`, `$ayaPay->status($orderId)` and `$yomaMmqr->status($reference)` return `PaymentStatusResult` with `status` and `isSuccessful()`. Wave Pay, Wave Pay Mini App and CyberSource have no status API.
- Statuses: `PaymentStatus::Successful`, `Pending`, `Failed`, `Canceled`, `Expired`, `Unknown`; `$status->isFinal()`.
- Redirects are never followed: a 3xx response is a failure like any other non-2xx one.
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
- Requesting the Wave Pay Mini App access token or computing its hash in the browser (both need secrets).
- Reusing a Wave `merchantReferenceId`, or calling Yoma `initiate()` twice for one order (use `renewQr()`).
- Opening a KBZ Pay PWA link outside a phone with the KBZ Pay app.
