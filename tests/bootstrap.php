<?php

/**
 * PHPUnit bootstrap for WordPress-free unit tests.
 *
 * WordPress/WooCommerce are NOT installed. Core WP functions are mocked per-test
 * with Brain\Monkey; only the minimal constants the plugin classes need at load
 * time are defined here.
 *
 * @package QNBPay\Tests
 */

// Guard constant so plugin class files (which `exit` without it) will load.
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
if (!defined('QNBPAY_VERSION')) {
    define('QNBPAY_VERSION', '2.0.0');
}
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Ensure the plugin's own PSR-4 autoloader is active even if Composer's is not.
require_once dirname(__DIR__) . '/src/Autoloader.php';
\QNBPay\Autoloader::register(dirname(__DIR__) . '/src/');
