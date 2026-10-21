# PHP Myanmar Payments

[![Latest Version on Packagist](https://img.shields.io/packagist/v/laranex/php-myanmar-payments.svg?style=flat-square)](https://packagist.org/packages/laranex/php-myanmar-payments)
[![Tests](https://github.com/laranex/php-myanmar-payments/actions/workflows/tests.yml/badge.svg)](https://github.com/laranex/php-myanmar-payments/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/laranex/php-myanmar-payments.svg?style=flat-square)](https://packagist.org/packages/laranex/php-myanmar-payments)
[![License](https://img.shields.io/packagist/l/laranex/php-myanmar-payments.svg?style=flat-square)](LICENSE.md)

PHP SDK for Myanmar payment gateways: KBZ Pay, Wave Pay, Wave Pay Mini App, AYA Pay, Yoma MMQR and CyberSource. Typed requests and results, exact amounts, works with any PSR-18 HTTP client. Built for humans and AI agents.

Every Myanmar payment gateway signs its requests and callbacks its own way, reports its own status codes and has its own rules for amounts. PHP Myanmar Payments handles all of that behind one typed API for plain PHP and any framework: build the payment data, start the payment, then verify the callback the gateway sends back.

## Why PHP Myanmar Payments

- **Five gateways, one pattern.** KBZ Pay (PWA, QR and in-app), Wave Pay, AYA Pay, Yoma MMQR and CyberSource, each with its own class and the same three steps.
- **One typed result per flow.** `RedirectPayment`, `FormPayment`, `QrPayment` or `AppPayment` holds exactly what the next step needs: a URL to redirect to, an auto-submitting form, a QR code or the signed order for the KBZ Pay mobile SDK. See [Payment Flows](https://laranex.vercel.app/php-myanmar-payments/payment-flows).
- **Exact amounts, checked against each gateway's rules.** Amounts are kept as decimal text and never pass through a float. Decimals, currency and limits follow each gateway's official documentation and are checked before any request is sent. See [Amounts](https://laranex.vercel.app/php-myanmar-payments/amounts).
- **Verified callbacks.** `handleCallback()` checks the gateway's signature without a network call, maps its status onto one `PaymentStatus` enum and gives you the acknowledgement the gateway expects. When a callback is late, ask KBZ Pay, AYA Pay or Yoma MMQR with `status()`. See [Callbacks & Status](https://laranex.vercel.app/php-myanmar-payments/callbacks).
- **Errors that say what went wrong.** A missing setting, an invalid field, a gateway error and a bad signature each throw their own exception, with a message that names the setting, the field or the gateway's code.
- **Any PSR-18 client, any PSR-16 cache.** The HTTP client is discovered automatically (Guzzle, Symfony HttpClient, …) or passed in, and Yoma MMQR tokens go in the cache you choose. Use it in plain PHP, Symfony, Slim or any PSR-15 framework; Laravel apps use [Laravel Myanmar Payments](https://laranex.vercel.app/laravel-myanmar-payments/introduction), which wraps this package.
- **Same behavior in every language.** The PHP, Go, Node.js and Python SDKs pass one shared set of test vectors for signing and callback verification, and read the same environment variables, so one `.env` file and one Yoma MMQR token cache serve them all.
- **Nothing stored for you.** The package keeps no tables and no webhook log. [Handling Webhooks](https://laranex.vercel.app/php-myanmar-payments/webhooks) shows the recommended flow as code you copy: verify, store, acknowledge, then process each call once.
- **Easy to test.** Every gateway call goes through the client you pass, so your tests fake the responses, replay signed callbacks and never touch the network. See [Testing](https://laranex.vercel.app/php-myanmar-payments/testing).

## Installation

> **Requires** PHP 8.1+ and any [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client, which sends the gateway requests.

```bash
composer require laranex/php-myanmar-payments guzzlehttp/guzzle
```

## Documentation

The full documentation lives at **[laranex.vercel.app/php-myanmar-payments](https://laranex.vercel.app/php-myanmar-payments)**:

- [Introduction](https://laranex.vercel.app/php-myanmar-payments/introduction): why the package exists and what it covers
- [Installation](https://laranex.vercel.app/php-myanmar-payments/installation): requirements, install command and a quick start
- [AI Agent Skill](https://laranex.vercel.app/php-myanmar-payments/ai-agent-skill): the skill that teaches coding agents to use the package
- [Configuration](https://laranex.vercel.app/php-myanmar-payments/configuration): credentials, environment variables, endpoints, UAT, HTTP client and token cache
- [Amounts](https://laranex.vercel.app/php-myanmar-payments/amounts): exact amounts and each gateway's amount rules
- [Payment Flows](https://laranex.vercel.app/php-myanmar-payments/payment-flows): redirect, form, QR and in-app payments
- [Callbacks & Status](https://laranex.vercel.app/php-myanmar-payments/callbacks): verified callbacks, payment statuses and status checks
- [Handling Webhooks](https://laranex.vercel.app/php-myanmar-payments/webhooks): the recommended verify, store, acknowledge, process-once flow
- [Framework Integration](https://laranex.vercel.app/php-myanmar-payments/framework-integration): Symfony, Slim and any PSR-7 / PSR-15 framework
- [Testing](https://laranex.vercel.app/php-myanmar-payments/testing): faking gateway calls and replaying signed callbacks in your tests
- [KBZ Pay](https://laranex.vercel.app/php-myanmar-payments/drivers/kbz-pay): PWA redirect, QR and in-app payments, status checks and callbacks
- [Wave Pay](https://laranex.vercel.app/php-myanmar-payments/drivers/wave-pay): redirect payments with line items and verified callbacks
- [Wave Pay Mini App](https://laranex.vercel.app/php-myanmar-payments/drivers/wave-pay-mini-app): access tokens, signed payment payloads for Wave's JavaScript SDK and verified callbacks
- [AYA Pay](https://laranex.vercel.app/php-myanmar-payments/drivers/aya-pay): hosted checkout for AYA Pay, other wallets and cards, with status checks
- [Yoma MMQR](https://laranex.vercel.app/php-myanmar-payments/drivers/yoma-mmqr): QR images, QR renewal, status checks and verified callbacks
- [CyberSource](https://laranex.vercel.app/php-myanmar-payments/drivers/cyber-source): Secure Acceptance card payments through a signed hosted checkout form
- [Results](https://laranex.vercel.app/php-myanmar-payments/references/results): every property of the payment result types
- [PaymentCallback & Status](https://laranex.vercel.app/php-myanmar-payments/references/payment-callback): callbacks, status results, acknowledgements and the PaymentStatus values
- [Errors](https://laranex.vercel.app/php-myanmar-payments/references/errors): validation, API, signature and configuration errors

## License

MIT. See [LICENSE](LICENSE.md).
