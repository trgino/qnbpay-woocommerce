<?php

/**
 * PHPUnit bootstrap for real WordPress + WooCommerce integration tests.
 *
 * Loads the WordPress test suite, WooCommerce and this plugin, then runs the
 * WooCommerce installer so its tables exist for the tests.
 *
 * @package QNBPay\Tests
 */

$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

$_functions = $_tests_dir . '/includes/functions.php';
if (!file_exists($_functions)) {
    echo "Could not find {$_functions}. Run bin/install-wp-tests.sh first." . PHP_EOL; // phpcs:ignore
    exit(1);
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Load WooCommerce and this plugin before WordPress finishes loading.
 */
function _qnbpay_load_plugins()
{
    $wc = WP_CONTENT_DIR . '/plugins/woocommerce/woocommerce.php';
    if (file_exists($wc)) {
        require $wc;
    }
    require dirname(__DIR__, 2) . '/index.php';
}
tests_add_filter('muplugins_loaded', '_qnbpay_load_plugins');

/**
 * Install WooCommerce tables once WordPress + WC are loaded.
 */
tests_add_filter('setup_theme', function () {
    if (!class_exists('WC_Install')) {
        echo 'WooCommerce not loaded — check the WP/WC version matrix.' . PHP_EOL; // phpcs:ignore
        return;
    }
    try {
        WC_Install::install();
        // Flush the roles/caps so WC is fully initialised.
        $GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
        wp_roles();
    } catch (\Throwable $e) {
        echo 'WooCommerce install failed: ' . $e->getMessage() . PHP_EOL; // phpcs:ignore
    }
});

require $_tests_dir . '/includes/bootstrap.php';
