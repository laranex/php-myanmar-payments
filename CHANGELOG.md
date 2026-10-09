# Changelog

All notable changes to `php-myanmar-payments` will be documented in this file.

## v4.0.0 - Unreleased

Initial release. The version starts at v4.0.0 so that it lines up with the other Laranex packages (`laravel-myanmar-payments` and `go-myanmar-payments`), which share the same gateway behavior and release cycle.

### Added

- Framework-agnostic SDK for KBZ Pay (PWA, QR, In-App), Wave Money, AYA Payment Gateway, Yoma MMQR and CyberSource Secure Acceptance: `KbzPay`, `WaveMoney`, `AyaPay`, `YomaMmqr` and `CyberSource`, each implementing the `PaymentGateway` contract.
- `MyanmarPayments` builds every gateway from one configuration array, creating each gateway on first use.
- One typed configuration class per gateway (`KbzPayConfig`, `WaveMoneyConfig`, `AyaPayConfig`, `YomaMmqrConfig`, `CyberSourceConfig`) and one typed request class per gateway (`KbzPayPaymentData`, `WaveMoneyPaymentData` with `WaveMoneyItem`, `AyaPayPaymentData` with `AyaPayMethod`, `YomaMmqrPaymentData`, `CyberSourcePaymentData` with `CyberSourceTransactionType`).
- One result class per flow: `RedirectPayment`, `FormPayment`, `QrPayment` and `AppPayment`, all implementing `PaymentResult` and tagged with a `PaymentFlow`.
- Verified callbacks: `handleCallback()` accepts a `CallbackRequest` (`CallbackRequest::fromGlobals()` or any PSR-7 server request) and returns a `PaymentCallback` with a gateway-independent `PaymentStatus` and the `Acknowledgement` each gateway expects.
- Status checks for KBZ Pay, AYA Payment Gateway and Yoma MMQR return `PaymentStatusResult`.
- Callback, return and cancel URLs only need to be valid absolute http or https URLs; there is no HTTPS-only or port-443 rule (gateways may still require HTTPS in production).
- Wave Money's sandbox is `https://preprodpayments.wavemoney.io:8107` (`WaveMoneyConfig::SANDBOX_URL`), with checkout at `https://preprodpayments.wavemoney.io/authenticate` (`SANDBOX_AUTHENTICATE_URL`).
- Wave Money callbacks fall back to `merchantReferenceId` for the order id when `orderId` is missing, null or empty (same rule as `go-myanmar-payments`).
- Callback and response JSON is decoded with numbers kept as their exact text (strings), so a gateway sending `1000.50` or `1000.0` as a JSON number still verifies; booleans sign as `true` / `false`.
- Callback verification rejects nested (non-scalar) values in signed fields instead of casting them, and CyberSource reads `decision`, `req_reference_number`, `transaction_id` and the amount only from fields listed in `signed_field_names` (unsigned extras are dropped from `raw`).
- Exact `Amount` values (`Amount::kyat(1000)`, `Amount::parse('1000.50')`) instead of floats; decimals are allowed only where the gateway's official documentation allows them.
- HTTP through any PSR-18 client (auto-discovered via `php-http/discovery`) and Yoma MMQR tokens cached through any PSR-16 cache, with an in-memory `ArrayCache` as the default.
- Exceptions: `PaymentException` as the base, with `ApiException`, `ConfigurationException`, `InvalidPaymentDataException` and `SignatureVerificationException`.
- Requires PHP 8.1 or higher; tested on PHP 8.1 through 8.5 with Pest, PHPStan (level 7) and Pint.
- Agent skill in `skills/php-myanmar-payments` so coding agents (Claude Code, Codex, Cursor and others) integrate the SDK correctly; install it with `npx skills add laranex/php-myanmar-payments`.
