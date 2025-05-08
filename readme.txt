=== QNBPay Payment Gateway for WooCommerce ===
Contributors: trgino
Tags: woocommerce, payment gateway, qnbpay, credit card, installment, 3d secure, payment
Requires at least: 5.6
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integrate QNBPay payment gateway with your WooCommerce store. Accept credit card payments with installment options and 3D Secure support.

== Description ==

This plugin allows you to accept credit card payments directly on your WooCommerce store via QNBPay.

**Features:**

*   Accept major credit cards.
*   Support for installment payments.
*   Optional 3D Secure integration for enhanced security.
*   Test mode for development and testing.
*   Customizable order prefix for reporting.
*   Option to limit installments globally, per product, or by cart amount.
*   Webhook support for payment status updates.
*   Transaction history log within the WordPress admin area.
*   Debug mode for troubleshooting.

== Installation ==

1.  Upload the `qnbpay-woocommerce` folder to the `/wp-content/plugins/` directory via FTP, or upload the ZIP file directly through the WordPress admin panel (Plugins > Add New > Upload Plugin).
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Go to WooCommerce > Settings > Payments.
4.  Find "QNBPay" and click "Manage".
5.  Configure the settings:
    *   Enable the gateway.
    *   Enter your Merchant Key, Merchant ID, App Key, and App Secret provided by QNBPay.
    *   Configure Test Mode, 3D Secure, and Installment options as needed.
    *   Set your desired Order Prefix and successful Order Status.
    *   Enter your Sale Webhook Key (if using webhooks) and copy the provided Webhook URL into your QNBPay merchant panel.
6.  Save changes.

== Frequently Asked Questions ==

= Where do I find my API keys (Merchant Key, Merchant ID, App Key, App Secret)? =

These credentials are provided by QNBPay when you open a merchant account. Please contact QNBPay support if you don't have them.

= How do installments work? =

If enabled, customers can choose an installment plan during checkout based on their credit card BIN and the options configured in your QNBPay account and plugin settings. You can set global limits, product-specific limits, or limits based on the minimum cart amount.

= What is the Webhook URL for? =

The Webhook URL allows QNBPay to send notifications directly to your store about payment status changes (e.g., successful payment confirmation). You need to copy the URL shown in the plugin settings and paste it into the corresponding field in your QNBPay merchant panel. The Sale Webhook Key must also match the key set in the panel.

= How can I test the gateway? =

Enable "Test Mode" in the plugin settings. You can then use the test card numbers provided in the QNBPay documentation to simulate transactions without actual charges.

= Where can I see the transaction logs? =

Go to WooCommerce > QNBPay Transactions in your WordPress admin area.

== Screenshots ==

1.  Settings Page - General configuration.
2.  Settings Page - Installment options.
3.  Checkout Form - How the payment fields appear to the customer.
4.  Transaction History - Admin view of logged transactions.
5.  Product Edit Screen - Installment limit meta box (if enabled).

== Changelog ==

= 1.0.0 =
* Initial release.
* Added support for credit card payments via QNBPay.
* Implemented installment options.
* Added 3D Secure support.
* Included Test Mode.
* Added Webhook handling.
* Implemented Transaction History log page.
* Added Debug Mode and logging features.
* Included security enhancements like nonce checks and data masking.
* Added DocBlocks and code comments.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

== Support ==

For issues or questions regarding the plugin, please refer to the GitHub repository or contact the author via trgino.com. For issues related to your QNBPay account or API credentials, please contact QNBPay Support.