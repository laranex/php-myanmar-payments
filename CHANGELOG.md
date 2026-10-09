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
- Callback verification rejects nested (non-scalar) values in signed fields instead of casting them, and CyberSource rejects a post whose `decision` or `req_reference_number` is not listed in `signed_field_names`, reads `transaction_id` and the amount only when signed, and keeps only the signed fields plus `signature` in `raw`.
- AYA Pay reads a base64 `payload` whose `+` arrived as a space as `+` (the checksum is still verified).
- `sandbox` accepts `true`/`1`/`t`/`yes`/`on` and `false`/`0`/`f`/`no`/`off` in any case; `Amount::parse()` drops leading zeros of the whole part (`007.50` becomes `7.50`).
- Exact `Amount` values (`Amount::kyat(1000)`, `Amount::parse('1000.50')`) instead of floats; decimals are allowed only where the gateway's official documentation allows them.
- HTTP through any PSR-18 client (auto-discovered via `php-http/discovery`) and Yoma MMQR tokens cached through any PSR-16 cache, with an in-memory `ArrayCache` as the default.
- Exceptions: `PaymentException` as the base, with `ApiException`, `ConfigurationException`, `InvalidPaymentDataException` and `SignatureVerificationException`.
- Requires PHP 8.1 or higher; tested on PHP 8.1 through 8.5 with Pest, PHPStan (level 7) and Pint.
- Agent skill in `skills/php-myanmar-payments` so coding agents (Claude Code, Codex, Cursor and others) integrate the SDK correctly; install it with `npx skills add laranex/php-myanmar-payments`.

### Changed since the pre-releases

- Behavior is aligned with `go-myanmar-payments`, `node-myanmar-payments` and `python-myanmar-payments`, checked by the shared vectors in `tests/Fixtures/parity/vectors.json`:
  - `PaymentCallback::$acknowledgement` is a public property; the `acknowledgement()` method is removed.
  - Booleans in CyberSource and Yoma MMQR callbacks sign as `true` / `false`, and a KBZ field set to `false` is signed as `false` instead of being left out. A nested value in a signed Yoma `status` is rejected.
  - AYA Pay's base64 `payload` must use the standard alphabet with correct padding or none; partial padding, line breaks and payloads that are not UTF-8 are rejected.
  - Yoma MMQR caches its token under `myanmar-payments.yoma-mmqr.token.<sha256 of base URL|client id>` (`YomaMmqr::TOKEN_CACHE_PREFIX`), the same key in every SDK, and reads `expires_in` from its leading digits.
  - Callback bodies are decoded as JSON only when they hold a JSON object; urlencoded bodies keep the first value of a repeated field and names exactly as sent (no `[]` or `.` handling).
  - `FormPayment::toHtml()` escapes `"` as `&#34;` and `'` as `&#39;`, the same page as the other SDKs.
  - Messages: `Amount::parse()` says `The amount field must be a number such as 1000 or 1000.50, got "<input>".`; decimal errors read `<Gateway> does not accept decimal amounts; the amount field must be a whole number.` and `<Gateway> accepts at most 2 decimal places; the amount field has 3.`; AYA's name in them is `AYA Payment Gateway`; `InvalidPaymentDataException`'s message is `Invalid payment data: ` plus every error sorted by field; gateway errors no longer end with a space.
  - Config constructors throw `ConfigurationException` for blank credentials, and blank URL, API version and webhook secret overrides mean unset. `ConfigurationException` has public `gateway` and `key`. `time_to_live_in_seconds` must be an integer or integer text.
  - Wave Money item errors are keyed `items.<i>.name` / `items.<i>.amount` and raised by `WaveMoneyPaymentData`; Wave's error message lists fields in sorted order.
  - AYA's status falls back to the requested order id, Yoma's status to the requested reference and an AYA service's name to its key when the gateway sends an empty value. CyberSource's blank `currency` and `locale` mean `MMK` and `en-us`.
  - Without an HTTP client, Guzzle is used with a 30 second timeout when it is installed (the other SDKs default to 30 seconds); otherwise the discovered PSR-18 client.
- Added `fromEnv()` on every config class, every gateway and `MyanmarPayments`, reading the same variables as the other SDKs (`KBZ_PAY_*`, `WAVE_MONEY_*`, `AYA_PAY_*`, `YOMA_MMQR_*`, `CYBER_SOURCE_*`).
- Gateways accept a config object or its `fromArray()` array, and `MyanmarPayments` entries accept either.
- Added `Amount::equals()` (by value, ignoring leading and trailing zeros) and JSON serialization of `Amount` as a string; `PaymentStatus::resolve()` (replaces the internal `StatusMap`); `Acknowledgement::default()`; `CallbackRequest::queryInput()`; `FormPayment::field()` and `values()`; a public `validate()` on every payment data class; a public `KbzPay::$signer` and `KbzPaySigner`.
- `PaymentStatus::Cancelled` is renamed to `PaymentStatus::Canceled` and its value from `'cancelled'` to `'canceled'` (American English), with no alias. Code or stored statuses from `v4.0.0-alpha.1` need the new name; gateway status literals such as Wave Money's `PAYMENT_REQUEST_CANCELLED` are unchanged.
