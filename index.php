<?php

/*
 * Plugin Name: QNBPay Payment Gateway for WooCommerce
 * Plugin URI: https://github.com/trgino/qnbpay-woocommerce
 * Description: QNBPay Payment gateway for WooCommerce. Credit card, installments, 3D Secure, refunds, HPOS and Checkout Blocks compatible.
 * Version: 2.0.2
 * Author: Cüneyt Çil
 * Author URI: https://trgino.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: qnbpay-for-woocommerce
 * Domain Path: /languages/
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 9.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// -----------------------------------------------------------------------------
// Plugin constants
// -----------------------------------------------------------------------------
define('QNBPAY_FILE', __FILE__);
define('QNBPAY_BASENAME', plugin_basename(QNBPAY_FILE));

// Single source of truth for the version: the "Version:" plugin header above.
// The constant is derived from it so the number is never duplicated in code.
if (!function_exists('get_file_data')) {
    require_once ABSPATH . WPINC . '/functions.php';
}
$qnbpay_headers = get_file_data(QNBPAY_FILE, ['Version' => 'Version']);
define('QNBPAY_VERSION', '' !== $qnbpay_headers['Version'] ? $qnbpay_headers['Version'] : '0.0.0');
unset($qnbpay_headers);

define('QNBPAY_DIR', plugin_dir_path(QNBPAY_FILE));
define('QNBPAY_URL', plugin_dir_url(QNBPAY_FILE));
define('QNBPAY_DOMAIN', 'qnbpay-for-woocommerce');

// Minimum requirements.
define('QNBPAY_MIN_PHP', '7.4');
define('QNBPAY_MIN_WP', '5.6');
define('QNBPAY_MIN_WC', '6.0');

// -----------------------------------------------------------------------------
// Lightweight PSR-4 autoloader (no Composer runtime dependency).
//
// Maps the "QNBPay\" namespace to the /src directory. This removes the previous
// dependency on rappasoft/laravel-helpers (which registered a GLOBAL data_get()
// function that could collide with other plugins bundling the same helper).
// -----------------------------------------------------------------------------
require_once QNBPAY_DIR . 'src/Autoloader.php';
\QNBPay\Autoloader::register(QNBPAY_DIR . 'src/');

// -----------------------------------------------------------------------------
// Activation / deactivation hooks.
//
// Registered unconditionally (WordPress requirement); the handlers themselves
// guard against a missing or inactive WooCommerce.
// -----------------------------------------------------------------------------
register_activation_hook(__FILE__, ['\QNBPay\Install\Installer', 'activate']);
register_deactivation_hook(__FILE__, ['\QNBPay\Install\Installer', 'deactivate']);

// -----------------------------------------------------------------------------
// Boot the plugin once all plugins are loaded so WooCommerce can be reliably
// detected, avoiding a fatal error when it is not active.
// -----------------------------------------------------------------------------
add_action('plugins_loaded', static function () {
    \QNBPay\Plugin::instance()->boot();
});
