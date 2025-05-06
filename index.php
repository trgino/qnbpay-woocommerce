<?php

/*
 * Plugin Name: QNBPay Payment Gateway for WooCommerce
 * Plugin URI: https://github.com/trgino/qnbpay-woocommerce
 * Description: QNBPay Payment gateway for woocommerce
 * Version: 1.0.2
 * Author: Cüneyt Çil
 * Author URI: https://trgino.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path: /languages/
 * Text Domain: qnbpay-woocommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

define('QNBPAY_VERSION', '1.0.2');
define('QNBPAY_FILE', __FILE__);
define('QNBPAY_BASENAME', plugin_basename(QNBPAY_FILE));
define('QNBPAY_DIR', plugin_dir_path(QNBPAY_FILE));
define('QNBPAY_URL', plugin_dir_url(QNBPAY_FILE));
define('QNBPAY_DOMAIN', 'qnbpay-woocommerce');

/**
 * Auto load QNBPay
 * Includes the Composer autoloader and loads the plugin text domain.
 *
 * @since 1.0.0
 * @return void
 */
function loadQNBPay()
{
    require __DIR__ . '/vendor/autoload.php';
    $path = dirname(plugin_basename(QNBPAY_FILE)) . '/languages';
    load_plugin_textdomain(QNBPAY_DOMAIN, false, $path);
}

/**
 * Generate QNBPay Transaction Table For Transaction Logs
 * Creates or updates the custom database table for logging QNBPay transactions.
 * Runs on plugin activation.
 *
 * @return void
 */
function onQNBPayActivation()
{
    global $wpdb;

    // Define table names with WordPress prefix
    $tableNames = [
        'qnbpay_orders' => $wpdb->prefix . 'qnbpay_orders',
        'qnbpay_orders_ids' => $wpdb->prefix . 'qnbpay_orders_ids',
    ];

    // Get the correct character set and collation for the database
    $charsetCollate = $wpdb->get_charset_collate();

    $createTableQuery = [];
    // SQL query to create the main log table
    $createTableQuery['qnbpay_orders'] = 'CREATE TABLE ' . $tableNames['qnbpay_orders'] . ' (
    id BIGINT(20) NOT NULL AUTO_INCREMENT,
    orderid BIGINT(20) NOT NULL,
    createdate DATETIME NOT NULL,
    action VARCHAR(50) NOT NULL,
    data LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    details LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
    PRIMARY KEY (id),
    KEY idx_orderid (orderid)
    ) ENGINE=InnoDB ' . $charsetCollate . ';';

    // SQL query to create the table for mapping custom order IDs
    $createTableQuery['qnbpay_orders_ids'] = 'CREATE TABLE ' . $tableNames['qnbpay_orders_ids'] . ' (
    id BIGINT(20) NOT NULL AUTO_INCREMENT,
    orderid BIGINT(20) NOT NULL,
    customorderid BIGINT(20) NOT NULL UNIQUE,
    invoiceid VARCHAR(255) NOT NULL,
    createdate DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_customorderid (customorderid),
    KEY idx_invoiceid (invoiceid),
    KEY idx_orderid (orderid),
    KEY idx_createdate (createdate)
    ) ENGINE=InnoDB ' . $charsetCollate . ';';

    // Check if the database schema is already up-to-date for the current plugin version
    $qnbpay_delta = get_options('qnbpay_delta', ['tables' => [], 'version' => 0]);

    if (isset($qnbpay_delta['version']) && $qnbpay_delta['version'] === QNBPAY_VERSION) {
        return;
    }

    // Determine which tables still need to be created/updated
    $qnbpay_tablesql = isset($qnbpay_delta['tables']) ? $qnbpay_delta['tables'] : [];
    foreach ($createTableQuery as $perKey => $perQuery) {
        if (in_array($perKey, $qnbpay_tablesql)) {
            unset($createTableQuery[$perKey]);
        }
    }

    // If all tables are up-to-date, exit
    if (!$createTableQuery) {
        return;
    }

    // Include WordPress upgrade functions if not already loaded
    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    // Execute the necessary SQL queries using dbDelta
    foreach ($createTableQuery as $perQuery) {
        dbDelta($perQuery);
    }

    // Update the option storing the current database schema version
    update_option('qnbpay_delta', ['version' => QNBPAY_VERSION, 'tables' => array_keys($tableNames)]);
    // Flush rewrite rules to ensure custom endpoints works
    add_action('init', function () {
        flush_rewrite_rules();
    }, PHP_INT_MAX);
}

// Register the activation hook
register_activation_hook(__FILE__, 'onQNBPayActivation');

/**
 * Initialize the plugin after all other plugins are loaded.
 * Loads dependencies and instantiates the main plugin classes.
 *
 * @since 1.0.0
 */
add_action('plugins_loaded', function () {
    loadQNBPay();
    $GLOBALS['qnbpaycore'] = QNBPay_Core::get_instance();
    $GLOBALS['qnbpayajax'] = QNBPay_Ajax::get_instance();
    QNBPay_Init::get_instance();
    QNBPay_Transaction_History::get_instance();
}, PHP_INT_MAX);
