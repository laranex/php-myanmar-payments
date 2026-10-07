# PHP Myanmar Payments

Framework-agnostic PHP SDK for Myanmar payment gateways: KBZ Pay (PWA, QR, In-App), Wave Money, AYA Payment Gateway, Yoma MMQR and CyberSource Secure Acceptance.

Each gateway takes a typed request object and returns a typed result, so your IDE shows exactly what to pass and what comes back.

Using Laravel? Install [`laranex/laravel-myanmar-payments`](https://github.com/laranex/laravel-myanmar-payments), which wraps this package.

**Documentation:** [laranex.vercel.app](https://laranex.vercel.app/laravel-myanmar-payments)

```bash
composer require laranex/php-myanmar-payments
```

Requires PHP 8.1+ and any PSR-18 HTTP client (for example `guzzlehttp/guzzle`).

```php
use Laranex\PhpMyanmarPayments\Amount;
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPay;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPayConfig;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPayPaymentData;

$kbzPay = new KbzPay(new KbzPayConfig(appId: '...', appKey: '...', merchantCode: '...', sandbox: true));

// Start a payment: a typed result per flow
$payment = $kbzPay->pwa(new KbzPayPaymentData(orderId: 'ORDER_1', amount: Amount::parse('1000.50'), callbackUrl: 'https://shop.test/kbz/callback'));
header('Location: '.$payment->url);

// Handle the callback: verified, with a gateway-independent status
$callback = $kbzPay->handleCallback(CallbackRequest::fromGlobals());

if ($callback->isSuccessful()) {
    // compare $callback->amount with your order, then fulfil $callback->orderId
}

$callback->acknowledgement()->send();
```

## Amounts

Amounts are exact `Amount` values, never floats:

```php
Amount::kyat(1000);        // whole amount (a plain int works too)
Amount::parse('1000.50');  // decimal amount, plain digits only
```

Each gateway accepts what its official docs allow: KBZ Pay up to 2 decimal places, CyberSource any decimals in any currency, and Wave Money, AYA and Yoma MMQR whole kyat only.
