# Changelog

All notable changes to `php-myanmar-payments` will be documented in this file.

## v4.0.0 - Unreleased

Initial release. The version starts at v4.0.0 so that it lines up with the other Laranex packages (`laravel-myanmar-payments` and `go-myanmar-payments`), which share the same gateway behaviour and release cycle.

### Added

- Framework-agnostic SDK for KBZ Pay (PWA, QR, In-App), Wave Money, AYA Payment Gateway, Yoma MMQR and CyberSource Secure Acceptance: `KbzPay`, `WaveMoney`, `AyaPay`, `YomaMmqr` and `CyberSource`, each implementing the `PaymentGateway` contract.
- `MyanmarPayments` builds every gateway from one configuration array, creating each gateway on first use.
- One typed configuration class per gateway (`KbzPayConfig`, `WaveMoneyConfig`, `AyaPayConfig`, `YomaMmqrConfig`, `CyberSourceConfig`) and one typed request class per gateway (`KbzPayPaymentData`, `WaveMoneyPaymentData` with `WaveMoneyItem`, `AyaPayPaymentData` with `AyaPayMethod`, `YomaMmqrPaymentData`, `CyberSourcePaymentData` with `CyberSourceTransactionType`).
- One result class per flow: `RedirectPayment`, `FormPayment`, `QrPayment` and `AppPayment`, all extending `PaymentResult` and tagged with a `PaymentFlow`.
- Verified callbacks: `handleCallback()` accepts a `CallbackRequest` (`CallbackRequest::fromGlobals()` or any PSR-7 server request) and returns a `PaymentCallback` with a gateway-independent `PaymentStatus` and the `Acknowledgement` each gateway expects.
- Status checks for KBZ Pay, AYA Payment Gateway and Yoma MMQR return `PaymentStatusResult`.
- Exact `Amount` values (`Amount::kyat(1000)`, `Amount::parse('1000.50')`) instead of floats; decimals are allowed only where the gateway's official documentation allows them.
- HTTP through any PSR-18 client (auto-discovered via `php-http/discovery`) and Yoma MMQR tokens cached through any PSR-16 cache, with an in-memory `ArrayCache` as the default.
- Exceptions: `PaymentException` as the base, with `ApiException`, `ConfigurationException`, `InvalidPaymentDataException` and `SignatureVerificationException`.
- Requires PHP 8.1 or higher; tested on PHP 8.1 through 8.5 with Pest, PHPStan (level 7) and Pint.
- Agent skill in `skills/php-myanmar-payments` so coding agents (Claude Code, Codex, Cursor and others) integrate the SDK correctly; install it with `npx skills add laranex/php-myanmar-payments`.
