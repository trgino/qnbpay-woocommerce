<p align="center">
  <img alt="QNBPay" src="assets/img/qnbpay.jpg" width="300">
</p>
<h1 align="center">QNBPay Payment Gateway for WooCommerce</h1>

<p align="center">
  <strong>Accept credit-card payments on your WooCommerce store via QNBPay — Turkey's QNB virtual POS (sanal POS) — with installments, 3D&nbsp;Secure and refunds.</strong>
</p>

<p align="center">
  <a href="https://github.com/trgino/qnbpay-woocommerce/blob/main/LICENSE">
    <img alt="License" src="https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg">
  </a>
  <img alt="Version" src="https://img.shields.io/badge/version-2.0.2-brightgreen.svg">
  <img alt="WordPress" src="https://img.shields.io/badge/WordPress->=5.6-blue.svg">
  <img alt="WooCommerce" src="https://img.shields.io/badge/WooCommerce->=6.0-96588a.svg">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-7.4--8.4-blueviolet.svg">
  <img alt="HPOS" src="https://img.shields.io/badge/HPOS-compatible-success.svg">
</p>

---

## About QNBPay

**QNBPay is a Turkish payment provider (QNB virtual POS / *sanal POS*).** It
processes credit-card payments in Turkey — including bank installment plans
(*taksit*) and 3D Secure — for merchants with a QNBPay account. This plugin
integrates that gateway into WooCommerce.

- Test API: `https://test.qnbpay.com.tr/`
- Production API: `https://portal.qnbpay.com.tr/`
- Provider: <https://qnbpay.com.tr/>

## Features

- 💳 Accept major credit cards, with optional **3D Secure**.
- 🧾 **Installments** (*taksit*) with global, per-product and cart-amount limits.
- ↩️ **Refunds** directly from the WooCommerce order screen.
- ⚡ **HPOS** (High-Performance Order Storage) compatible.
- 🧱 **Cart & Checkout Blocks** compatible (block-based checkout).
- 🔔 **Webhook** support with hash verification.
- 🛡️ **Background reconciliation** (Action Scheduler) that completes pending
  orders confirmed by `checkstatus` when a webhook or return is missed.
- 📜 Transaction history log in the WordPress admin.
- 🐞 Debug logging via the native WooCommerce logger (WooCommerce → Status → Logs).

## Security

- Card data (PAN/CVV) is **never stored** in the WordPress database. The 3D
  Secure redirect form lives only in a short-lived, single-use transient and is
  rendered once, then immediately deleted.
- Sensitive values are masked in all logs.
- Fail-safe by design: on any unexpected error the order is left pending, the
  reason is logged, and the reconciliation job finalizes it once the bank
  confirms — a failure never silently loses a payment.

## Requirements

| | Minimum |
|---|---|
| PHP | 7.4 (tested through 8.4) |
| WordPress | 5.6 |
| WooCommerce | 6.0 |

## Installation

1. **Download** the latest release ZIP from the [Releases](https://github.com/trgino/qnbpay-woocommerce/releases) page.
2. **Upload** via WordPress Admin → Plugins → Add New → Upload Plugin, then **Activate**.
3. **Configure** at WooCommerce → Settings → Payments → QNBPay → *Manage*:
   - Enable the gateway.
   - Enter your **Merchant Key, Merchant ID, App Key, App Secret** (provided by QNBPay).
   - Configure Test Mode, 3D Secure, installment and order-status options.
   - (Optional) Set the **Sale Webhook Key** and copy the shown **Webhook URL** into your QNBPay merchant panel.
4. **Save changes.**

## FAQ

- **Where do I find my API keys?** They are provided by QNBPay when you open a merchant account. Contact QNBPay support if you don't have them.
- **How do installments work?** Customers pick an installment plan at checkout based on their card BIN and your QNBPay/plugin limits (global, per-product or minimum cart amount).
- **What is the Webhook URL for?** It lets QNBPay notify your store of payment status changes. Paste the URL from the settings into your QNBPay panel; the Sale Webhook Key must match.
- **How can I test?** Enable *Test Mode* and use the test cards from the QNBPay documentation.
- **Where are the logs?** Transactions: WooCommerce → QNBPay Transactions. Debug logs: WooCommerce → Status → Logs.

## Development

Development tooling is dependency-managed with Composer (runtime needs **no**
`vendor/` — the plugin ships its own PSR-4 autoloader).

```bash
composer install          # dev dependencies (PHPUnit, PHPStan, stubs)
composer lint             # php -l across the codebase
composer phpstan          # static analysis (WordPress/WooCommerce stubs)
composer test             # unit tests (Brain Monkey — no WordPress needed)
```

### Continuous integration

GitHub Actions runs everything without a local WordPress/WooCommerce install:

- **`ci.yml`** — PHP lint matrix (7.4–8.4) · PHPStan · PHPUnit unit tests.
- **`plugin-check.yml`** — the official WordPress *Plugin Check*.
- **`integration.yml`** — real WordPress + WooCommerce PHPUnit tests (MySQL service + WP test suite).

## Changelog

See [`changelog.md`](changelog.md).

## Support

For plugin issues, open a GitHub issue. For QNBPay account/API-credential
questions, contact QNBPay support.

## License

GPLv2 or later. See [`LICENSE`](LICENSE).
