<?php

namespace QNBPay\Install;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles plugin activation, database schema and deactivation.
 *
 * @since 2.0.0
 */
final class Installer
{
    const DB_VERSION_OPTION = 'qnbpay_db_version';

    /**
     * Activation callback.
     *
     * @return void
     */
    public static function activate()
    {
        try {
            self::create_tables();
            \update_option(self::DB_VERSION_OPTION, QNBPAY_VERSION);
            // Schedule the reconciliation cron.
            \QNBPay\Cron\Reconciler::ensure_scheduled();
            // Rewrite rules for the custom payment endpoints are registered on
            // 'init'; flush once here so they take effect immediately.
            \set_transient('qnbpay_flush_rewrite', 1, 60);
        } catch (\Throwable $e) {
            // Do not block activation on a schema error; log the reason.
            if (function_exists('wc_get_logger')) {
                \wc_get_logger()->error(
                    sprintf('Activation error: %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()),
                    ['source' => 'qnbpay']
                );
            } else {
                error_log('QNBPay activation error: ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
        }
    }

    /**
     * Deactivation callback.
     *
     * @return void
     */
    public static function deactivate()
    {
        \QNBPay\Cron\Reconciler::unschedule();
        \flush_rewrite_rules();
    }

    /**
     * Ensure the database schema is present and up to date.
     *
     * Runs on activation and is also safe to call on upgrades.
     *
     * @return void
     */
    public static function maybe_upgrade()
    {
        if (\get_option(self::DB_VERSION_OPTION) === QNBPAY_VERSION) {
            return;
        }

        self::create_tables();
        \update_option(self::DB_VERSION_OPTION, QNBPAY_VERSION);
    }

    /**
     * Create/upgrade custom tables via dbDelta.
     *
     * @return void
     */
    private static function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $orders = $wpdb->prefix . 'qnbpay_orders';
        $orders_ids = $wpdb->prefix . 'qnbpay_orders_ids';

        $sql = [];

        $sql[] = "CREATE TABLE {$orders} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            orderid BIGINT(20) NOT NULL,
            invoiceid VARCHAR(64) NULL,
            createdate DATETIME NOT NULL,
            action VARCHAR(50) NOT NULL,
            data LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
            details LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
            PRIMARY KEY  (id),
            KEY idx_invoiceid (invoiceid),
            KEY idx_orderid (orderid)
        ) ENGINE=InnoDB {$charset_collate};";

        $sql[] = "CREATE TABLE {$orders_ids} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            orderid BIGINT(20) NOT NULL,
            invoiceid VARCHAR(64) NOT NULL,
            createdate DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_invoiceid (invoiceid),
            KEY idx_orderid (orderid),
            KEY idx_createdate (createdate)
        ) ENGINE=InnoDB {$charset_collate};";

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        foreach ($sql as $query) {
            \dbDelta($query);
        }
    }
}
