<p align="center">
  <img alt="QNBPay.com" src="assets/img/qnbpay.png" width="300">
</p>
<h1 align="center">QNBPay Payment Gateway for WooCommerce</h1>

<p align="center">
  <strong>Integrate QNBPay payment gateway with your WooCommerce store. Accept credit card payments with installment options and 3D Secure support.</strong>
</p>
<p align="center">
  <a href="https://github.com/trgino/qnbpay-woocommerce/blob/main/LICENSE">
    <img alt="License" src="https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg">
  </a>
  <a href="#">
    <img alt="Version" src="https://img.shields.io/badge/version-1.0.1-brightgreen.svg">
  </a>
  <a href="#">
    <img alt="WordPress" src="https://img.shields.io/badge/WordPress->=5.6-blue.svg">
  </a>
  <a href="#">
    <img alt="PHP" src="https://img.shields.io/badge/PHP->=7.4-blueviolet.svg">
  </a>
</p>

---

## Description

This plugin allows you to accept credit card payments directly on your WooCommerce store via QNBPay.

### Features:

*   Accept major credit cards.
*   Support for installment payments.
*   Optional 3D Secure integration for enhanced security.
*   Test mode for development and testing.
*   Customizable order prefix for reporting.
*   Option to limit installments globally, per product, or by cart amount.
*   Webhook support for payment status updates.
*   Transaction history log within the WordPress admin area.
*   Debug mode for troubleshooting.

## Installation

1.  **Download:** Download the latest release ZIP file from the [Releases](https://github.com/trgino/qnbpay-woocommerce/releases) page (or clone the repository).
2.  **Upload:** Go to your WordPress Admin > Plugins > Add New > Upload Plugin. Choose the downloaded ZIP file and click "Install Now".
3.  **Activate:** Activate the plugin through the 'Plugins' menu in WordPress.
4.  **Configure:**
    *   Go to WooCommerce > Settings > Payments.
    *   Find "QNBPay" and click "Manage".
    *   Enable the gateway.
    *   Enter your Merchant Key, Merchant ID, App Key, and App Secret provided by QNBPay.
    *   Configure Test Mode, 3D Secure, and Installment options as needed.
    *   Set your desired Order Prefix and successful Order Status.
    *   Enter your Sale Webhook Key (if using webhooks) and copy the provided Webhook URL into your QNBPay merchant panel.
5.  **Save:** Save changes.

## Frequently Asked Questions (FAQ)

*   **Where do I find my API keys (Merchant Key, Merchant ID, App Key, App Secret)?**
    These credentials are provided by QNBPay when you open a merchant account. Please contact QNBPay support if you don't have them.
*   **How do installments work?**
    If enabled, customers can choose an installment plan during checkout based on their credit card BIN and the options configured in your QNBPay account and plugin settings. You can set global limits, product-specific limits, or limits based on the minimum cart amount.
*   **What is the Webhook URL for?**
    The Webhook URL allows QNBPay to send notifications directly to your store about payment status changes. You need to copy the URL shown in the plugin settings and paste it into the corresponding field in your QNBPay merchant panel. The Sale Webhook Key must also match the key set in the panel.
*   **How can I test the gateway?**
    Enable "Test Mode" in the plugin settings. Use the test card numbers provided in the QNBPay documentation.
*   **Where can I see the transaction logs?**
    Go to WooCommerce > QNBPay Transactions in your WordPress admin area.

## Changelog

Please see the `changelog.md` file for more details.

## Support

For issues or questions regarding the plugin, please open an issue on the GitHub repository. For issues related to your QNBPay account or API credentials, please contact QNBPay Support.

## License

This plugin is licensed under the GPLv2 or later. See the `LICENSE` file for details.
