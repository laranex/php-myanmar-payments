# Changelog

All notable changes to `php-myanmar-payments` will be documented in this file.

## 1.0.0 - Unreleased

- Framework-agnostic SDK for KBZ Pay (PWA, QR, In-App), Wave Money, AYA Payment Gateway, Yoma MMQR and CyberSource Secure Acceptance
- One typed request class per gateway and one result class per flow: `RedirectPayment`, `FormPayment`, `QrPayment`, `AppPayment`
- Verified callbacks return `PaymentCallback` with a gateway-independent `PaymentStatus` and the acknowledgement each gateway expects
- Status checks for KBZ Pay, AYA and Yoma MMQR return `PaymentStatusResult`
- Exact `Amount` values (`Amount::kyat(1000)`, `Amount::parse('1000.50')`) instead of floats; decimals only where the gateway's docs allow them
- HTTP through any PSR-18 client, Yoma tokens cached through any PSR-16 cache
