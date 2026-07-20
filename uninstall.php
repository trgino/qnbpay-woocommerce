<?php

/**
 * QNBPay uninstall routine.
 *
 * Removes plugin options, transients and custom tables. Order metadata is left
 * intact so historical orders remain readable.
 *
 * @package QNBPay
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Options.
$qnbpay_options = [
    'woocommerce_qnbpay_settings',
    'qnbpay_db_version',
    'qnbpay_webhook_hash',
    // Legacy (< 2.0.0) options.
    'woocommerce_qnbpay_debugfile',
    'woocommerce_qnbpay_version',
    'qnbpay_delta',
];
foreach ($qnbpay_options as $qnbpay_option) {
    delete_option($qnbpay_option);
}

// Scheduled events (Action Scheduler + any legacy WP-Cron event).
if (function_exists('as_unschedule_all_actions')) {
    as_unschedule_all_actions('qnbpay_reconcile_pending', [], 'qnbpay');
}
wp_clear_scheduled_hook('qnbpay_reconcile_pending');

// Transients.
delete_transient('qnbpay_api_token');
delete_transient('qnbpay_flush_rewrite');
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_qnbpay\_%' OR option_name LIKE '\_transient\_timeout\_qnbpay\_%'");

// Custom tables.
// phpcs:disable WordPress.DB.DirectDatabaseQuery
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}qnbpay_orders");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}qnbpay_orders_ids");
// phpcs:enable
