=== QNBPay Payment Gateway for WooCommerce ===
Contributors: trgino
Tags: woocommerce, payment gateway, qnbpay, turkey, installment
Requires at least: 5.6
Tested up to: 6.9
Requires PHP: 7.4
WC requires at least: 6.0
WC tested up to: 9.4
Stable tag: 2.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept credit card payments via QNBPay, Turkey's QNB virtual POS (sanal POS). Installments, 3D Secure, refunds, HPOS and Checkout Blocks compatible.

== Description ==

This plugin lets you accept credit card payments on your WooCommerce store via QNBPay, a Turkish payment provider (QNB virtual POS / sanal POS). It supports Turkish bank installment plans (taksit) and 3D Secure for merchants with a QNBPay account. Payments are processed in Turkey through the QNBPay API (test.qnbpay.com.tr / portal.qnbpay.com.tr).

**Features:**

*   Accept major credit cards with optional 3D Secure.
*   Installment payments with global, per-product and cart-amount limits.
*   Refunds directly from the WooCommerce order screen.
*   High-Performance Order Storage (HPOS) compatible.
*   Cart & Checkout Blocks compatible (block-based checkout).
*   Webhook support with hash verification.
*   Transaction history log in the WordPress admin.
*   Test mode and debug logging (via the WooCommerce logger).
*   Compatible with PHP 7.4 through 8.4.

**Security:** Card data (PAN/CVV) is never stored in the database. The 3D Secure
redirect form is held only in a short-lived, single-use transient and rendered
once, then immediately deleted.

== Installation ==

1.  Upload the `qnbpay-woocommerce` folder to `/wp-content/plugins/`, or upload the ZIP via Plugins > Add New > Upload Plugin.
2.  Activate the plugin.
3.  Go to WooCommerce > Settings > Payments > QNBPay > Manage.
4.  Enter your Merchant Key, Merchant ID, App Key and App Secret.
5.  Configure Test Mode, 3D Secure and installment options.
6.  (Optional) Set the Sale Webhook Key and copy the Webhook URL into your QNBPay panel.
7.  Save changes.

== Frequently Asked Questions ==

= Where do I find my API keys? =

They are provided by QNBPay when you open a merchant account. Contact QNBPay support if you do not have them.

= Is it compatible with the new block-based checkout? =

Yes. The gateway registers a Checkout Blocks integration and also declares HPOS compatibility.

= Where can I see the transaction logs? =

WooCommerce > QNBPay Transactions. Debug logs are under WooCommerce > Status > Logs.

== External services ==

This plugin connects to the QNBPay payment API to authorize credit-card
payments, verify their status and process refunds. It is required for the
gateway to function.

Data sent, and when:

* When a customer pays with QNBPay: card holder name, card number, expiry, CVV, amount, currency, installment count, order line items, billing name/email/phone and the customer IP address.
* When verifying a payment or running the background reconciliation: the invoice id and merchant key.
* When issuing a refund: the invoice id, merchant key and amount.

Where the data is sent:

* Test: https://test.qnbpay.com.tr/
* Production: https://portal.qnbpay.com.tr/

Card numbers and CVV are never stored in your WordPress database; they are only
forwarded to QNBPay to complete the transaction. Use of QNBPay is subject to
their terms and privacy policy: https://qnbpay.com.tr/

== Changelog ==

= 2.0.1 =
* Renamed the plugin slug and text domain to "qnbpay-for-woocommerce" for WordPress.org naming compliance.
* WordPress Plugin Check fixes: translators comments, text-domain consistency, input sanitization and output escaping.
* Removed unused card-brand images and dead CSS; fixed the BIN-lookup AJAX race (stale requests are now aborted).
* Tooling: version derived from the plugin header + CI consistency guard; Plugin Check runs against the built distribution.

= 2.0.0 =
* Full rewrite: PSR-4 namespaced architecture with a dependency-free autoloader.
* Removed the rappasoft/laravel-helpers dependency (no more global data_get()).
* HPOS (custom order tables) compatibility.
* WooCommerce Cart/Checkout Blocks payment method integration.
* PCI improvement: card data is no longer stored in order/post meta.
* Added refund support (WooCommerce refund API).
* Secure logging via the WooCommerce logger (protected log directory).
* Guarded loading so the plugin never fatals when WooCommerce is inactive.
* Full try/catch coverage on all API/payment flows and many bug fixes.
* PHP 7.4 - 8.4 compatibility hardening.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 2.0.0 =
Major rewrite. Existing settings are preserved. Review your QNBPay settings after updating.
