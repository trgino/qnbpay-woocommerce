<?php

namespace QNBPay\Admin;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * QNBPay transaction log list table.
 *
 * Uses the WordPress core WP_List_Table so the listing inherits core styling,
 * pagination, screen options and accessibility instead of a hand-rolled table.
 *
 * @since 2.0.0
 */
class TransactionsTable extends \WP_List_Table
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct([
            'singular' => 'qnbpay_transaction',
            'plural' => 'qnbpay_transactions',
            'ajax' => false,
        ]);
    }

    /**
     * Define the columns.
     *
     * @return array
     */
    public function get_columns()
    {
        return [
            'id' => __('ID', 'qnbpay-for-woocommerce'),
            'orderid' => __('Order ID', 'qnbpay-for-woocommerce'),
            'action' => __('Action', 'qnbpay-for-woocommerce'),
            'invoiceid' => __('Invoice ID', 'qnbpay-for-woocommerce'),
            'createdate' => __('Date', 'qnbpay-for-woocommerce'),
        ];
    }

    /**
     * Query and prepare the items with pagination.
     *
     * @return void
     */
    public function prepare_items()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'qnbpay_orders';
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        // Table name is built from $wpdb->prefix (no user input); safe to interpolate.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total_items = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$table}");
        $items = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY createdate DESC LIMIT %d OFFSET %d", $per_page, $offset),
            ARRAY_A
        );
        // phpcs:enable

        $this->items = is_array($items) ? $items : [];
        $this->_column_headers = [$this->get_columns(), [], []];
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => (int) ceil($total_items / $per_page),
        ]);
    }

    /**
     * Default column renderer.
     *
     * @param array  $item
     * @param string $column_name
     * @return string
     */
    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'action':
            case 'invoiceid':
                return esc_html((string) $item[$column_name]);
            case 'createdate':
                $format = get_option('date_format') . ' ' . get_option('time_format');

                return esc_html(date_i18n($format, strtotime($item['createdate'])));
            default:
                return isset($item[$column_name]) ? esc_html((string) $item[$column_name]) : '';
        }
    }

    /**
     * ID column with a "View" row action.
     *
     * @param array $item
     * @return string
     */
    public function column_id($item)
    {
        $view_url = wp_nonce_url(
            admin_url('admin.php?page=qnbpay-transaction-history&action=view&id=' . (int) $item['id']),
            'qnbpay_transaction_action'
        );

        $actions = [
            'view' => '<a href="' . esc_url($view_url) . '">' . esc_html__('View', 'qnbpay-for-woocommerce') . '</a>',
        ];

        return sprintf('%1$s %2$s', esc_html((string) $item['id']), $this->row_actions($actions));
    }

    /**
     * Order ID column, linked to the (HPOS-safe) order edit screen.
     *
     * @param array $item
     * @return string
     */
    public function column_orderid($item)
    {
        $order_id = (int) $item['orderid'];
        if ($order_id <= 0) {
            return '&mdash;';
        }

        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
            $url = \Automattic\WooCommerce\Utilities\OrderUtil::get_order_admin_edit_url($order_id);
        } else {
            $url = admin_url('post.php?post=' . $order_id . '&action=edit');
        }

        return '<a href="' . esc_url($url) . '" target="_blank">' . esc_html((string) $order_id) . '</a>';
    }

    /**
     * Message shown when there are no rows.
     *
     * @return void
     */
    public function no_items()
    {
        esc_html_e('No transactions found.', 'qnbpay-for-woocommerce');
    }
}
