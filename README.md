# PHP Myanmar Payments

[![Latest Version on Packagist](https://img.shields.io/packagist/v/laranex/php-myanmar-payments.svg?style=flat-square)](https://packagist.org/packages/laranex/php-myanmar-payments)
[![Tests](https://img.shields.io/github/actions/workflow/status/laranex/php-myanmar-payments/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/laranex/php-myanmar-payments/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/laranex/php-myanmar-payments.svg?style=flat-square)](https://packagist.org/packages/laranex/php-myanmar-payments)
[![License](https://img.shields.io/packagist/l/laranex/php-myanmar-payments.svg?style=flat-square)](LICENSE.md)

A framework-agnostic PHP SDK for Myanmar payment gateways: KBZ Pay (PWA, QR, In-App), Wave Money, AYA Payment Gateway, Yoma MMQR and CyberSource Secure Acceptance. Each gateway takes a typed request object and returns a typed result, callbacks are verified for you, and amounts are exact `Amount` values instead of floats. It is for any PHP application with a PSR-18 HTTP client; Laravel applications can use [`laranex/laravel-myanmar-payments`](https://github.com/laranex/laravel-myanmar-payments), which wraps this package.

## Documentation

Full documentation lives at **[laranex.vercel.app/php-myanmar-payments](https://laranex.vercel.app/php-myanmar-payments)**.

## Requirements

- PHP 8.1 or higher
- Any PSR-18 HTTP client (for example `guzzlehttp/guzzle`)

## Installation

```bash
composer require laranex/php-myanmar-payments
```

The HTTP client and PSR-17 factories are found through `php-http/discovery`. Its Composer plugin is optional: if Composer asks, you may allow it (`composer config allow-plugins.php-http/discovery true`) so it installs a client when none is present, or decline and install one yourself, for example `composer require guzzlehttp/guzzle`. Laravel applications already allow the plugin and ship Guzzle.

## Usage

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
    // compare $callback->amount with your order, then fulfill $callback->orderId
}

$callback->acknowledgement()->send();
```

Amounts are exact `Amount` values (`Amount::kyat(1000)`, `Amount::parse('1000.50')`), and each gateway accepts only what its official docs allow. See the [documentation](https://laranex.vercel.app/php-myanmar-payments) for the other gateways, status checks and configuration.

## Built for humans and AI agents

The documentation is written for developers, and the package ships an agent skill so AI coding agents use it the way it's meant to be used.

- Install it with `npx skills add laranex/php-myanmar-payments` (Claude Code, Codex, Cursor and others), or copy `skills/php-myanmar-payments` into your project's `.claude/skills` or `.agents/skills`.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Nay Thu Khant](https://github.com/NayThuKhant)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
