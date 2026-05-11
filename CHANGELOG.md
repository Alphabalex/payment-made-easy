# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Laravel 8.83+ and Laravel 13** support: widened **`illuminate/support`** and **`illuminate/http`** constraints; expanded **`orchestra/testbench`** and **PHPUnit** dev ranges; CI matrix covers Laravel **8–13** with appropriate PHP versions (Laravel 13 on PHP **8.3+**).
- **`webhooks.require_signing_secret`** (env **`PAYMENT_WEBHOOK_REQUIRE_SIGNING_SECRET`**, default **true**): fail closed when signature verification is on but the gateway’s signing material is empty. Gateways without HMAC signing (M-Pesa, MTN MoMo, Remita, PayPal) opt out; Monnify accepts **`secret_key`** if **`webhook_secret`** is unset; Interswitch accepts **`client_secret`** or **`webhook_secret`**.
- **`webhooks.log_unexpected_exception_trace`** (env **`PAYMENT_WEBHOOK_LOG_UNEXPECTED_TRACE`**): omit or set **false** to avoid stack traces on unexpected webhook **500** logs; **null** (default) includes traces only when **`app.debug`** is true.
- **`DefaultWebhookLogSanitizer`:** broader default redact keys (e.g. BVN, IBAN, MSISDN) and automatic redaction of keys whose names end with configured suffixes (e.g. **`_secret`**, **`_api_key`**, **`_token`**).
- **`LICENSE`** file (MIT), aligned with **`composer.json`**.
- **`CONTRIBUTING.md`** contributor guide (development setup, tests, PR expectations, Conventional Commits).
- **`SECURITY.md`** vulnerability reporting policy (private contact, scope, safe harbor).

### Removed

- Internal **`IMPLEMENTATION_PLAN.md`**, **`CODEBASE_OPTIMIZATION_FINDINGS.md`**, and the **`examples/`** scratch scripts (content is covered in **README**; those PHP files were not runnable package entrypoints).

### Changed

- **README:** expanded usage and webhook documentation (default `Payment::initializePayment`, multi-driver one-time snippets, `getPayment`, Stripe Price ID as `plan_code`, bank-account disbursements for Monnify/Squad/Remita/Budpay, full `EventServiceProvider` webhook map, `WebhookManager` manual handling, Monnify hosted checkout, `PaymentManager` constructor injection).
- **`WebhookManager`** builds handlers from **live config** on each **`getHandler()`** call (no per-gateway handler cache), so tests and config refreshes always see current secrets.
- **Outbound HTTP:** payment drivers now use Laravel’s **`Illuminate\Support\Facades\Http`** client instead of injecting **`GuzzleHttp\Client`**. Timeout and TLS verify still come from each gateway’s **`http_timeout`** / **`http_verify`** config. Advanced customization uses Laravel’s **`Http::globalOptions()`** and related APIs; the **`payment-made-easy.http.client`** container binding and **`payment-gateways.http.client`** / per-gateway **`http_client`** Guzzle resolution have been removed.
- **Composer:** direct **`guzzlehttp/guzzle`** requirement replaced with **`illuminate/http`** (Guzzle remains a transitive dependency).
