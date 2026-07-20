<?php

namespace QNBPay\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin page listing QNBPay transaction log rows.
 *
 * The listing is rendered with the WordPress core WP_List_Table (see
 * {@see TransactionsTable}); only the single-entry detail view is custom.
 *
 * @since 2.0.0
 */
class TransactionHistory
{
    /**
     * Register hooks.
     *
     * @return void
     */
    public function register()
    {
        add_action('admin_menu', [$this, 'add_page']);
    }

    /**
     * Register the submenu page.
     *
     * @return void
     */
    public function add_page()
    {
        add_submenu_page(
            'woocommerce',
            __('QNBPay Transaction History', 'qnbpay-woocommerce'),
            __('QNBPay Transactions', 'qnbpay-woocommerce'),
            'manage_woocommerce',
            'qnbpay-transaction-history',
            [$this, 'render']
        );
    }

    /**
     * Render the page (list or details view).
     *
     * @return void
     */
    public function render()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Permission denied.', 'qnbpay-woocommerce'));
        }

        try {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        // phpcs:enable

        if ('view' === $action && $id) {
            if (!wp_verify_nonce($nonce, 'qnbpay_transaction_action')) {
                wp_die(esc_html__('Security check failed', 'qnbpay-woocommerce'));
            }
            $this->render_details($id);

            return;
        }

        $this->render_list();
        } catch (\Throwable $e) {
            \QNBPay\Plugin::instance()->logger()->exception('transaction_history', $e);
            echo '<div class="notice notice-error"><p>'
                . esc_html__('Could not load transactions. Check WooCommerce > Status > Logs.', 'qnbpay-woocommerce')
                . '</p></div>';
        }
    }

    /**
     * Render the list using the core WP_List_Table.
     *
     * @return void
     */
    private function render_list()
    {
        $table = new TransactionsTable();
        $table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('QNBPay Transaction History', 'qnbpay-woocommerce') . '</h1>';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="qnbpay-transaction-history" />';
        $table->display();
        echo '</form>';
        echo '</div>';
    }

    /**
     * HPOS-safe order edit URL.
     *
     * @param int $order_id
     * @return string
     */
    private function order_edit_url($order_id)
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
            return \Automattic\WooCommerce\Utilities\OrderUtil::get_order_admin_edit_url($order_id);
        }

        return admin_url('post.php?post=' . $order_id . '&action=edit');
    }

    /**
     * Render a single log entry.
     *
     * @param int $id
     * @return void
     */
    private function render_details($id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'qnbpay_orders';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        // phpcs:enable

        if (!$row) {
            wp_die(esc_html__('Transaction not found', 'qnbpay-woocommerce'));
        }

        $date_format = get_option('date_format') . ' ' . get_option('time_format');

        echo '<div class="wrap"><h1>' . esc_html__('Transaction Details', 'qnbpay-woocommerce')
            . ' (Log ID: ' . esc_html($row->id) . ' / Order ID: ' . esc_html($row->orderid) . ')</h1>';
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=qnbpay-transaction-history')) . '" class="button">'
            . esc_html__('Back to Transaction History', 'qnbpay-woocommerce') . '</a> ';
        if ($row->orderid > 0) {
            echo '<a href="' . esc_url($this->order_edit_url((int) $row->orderid)) . '" class="button" target="_blank">'
                . esc_html__('View Order', 'qnbpay-woocommerce') . ' (' . esc_html($row->orderid) . ')</a>';
        }
        echo '</p>';

        echo '<table class="wp-list-table widefat fixed striped"><tbody>';
        $this->detail_row(__('Log ID', 'qnbpay-woocommerce'), $row->id);
        $this->detail_row(__('Order ID', 'qnbpay-woocommerce'), $row->orderid);
        $this->detail_row(__('Invoice ID', 'qnbpay-woocommerce'), $row->invoiceid);
        $this->detail_row(__('Action', 'qnbpay-woocommerce'), $row->action);
        $this->detail_row(__('Date', 'qnbpay-woocommerce'), date_i18n($date_format, strtotime($row->createdate)));

        echo '<tr><td valign="top"><strong>' . esc_html__('Data', 'qnbpay-woocommerce') . '</strong></td><td>';
        $this->print_json($row->data);
        echo '</td></tr>';
        echo '<tr><td valign="top"><strong>' . esc_html__('Details', 'qnbpay-woocommerce') . '</strong></td><td>';
        $this->print_json($row->details);
        echo '</td></tr>';

        echo '</tbody></table></div>';
    }

    /**
     * Output a simple field/value row.
     *
     * @param string $label
     * @param mixed  $value
     * @return void
     */
    private function detail_row($label, $value)
    {
        echo '<tr><td><strong>' . esc_html($label) . '</strong></td><td>' . esc_html($value) . '</td></tr>';
    }

    /**
     * Decode & render a JSON blob as a nested list.
     *
     * @param string $json
     * @return void
     */
    private function print_json($json)
    {
        $data = json_decode((string) $json, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            echo '<pre>' . esc_html((string) $json) . '</pre>';

            return;
        }
        if (!$data) {
            echo '<em>-</em>';

            return;
        }
        $this->print_list($data);
    }

    /**
     * Recursively render an array as an unordered list.
     *
     * @param array $array
     * @return void
     */
    private function print_list($array)
    {
        if (!is_array($array) || !$array) {
            echo '-';

            return;
        }
        echo '<ul style="margin:0;padding-left:15px;">';
        foreach ($array as $key => $value) {
            echo '<li style="margin-bottom:5px;"><strong>' . esc_html((string) $key) . ':</strong> ';
            if (is_array($value)) {
                $this->print_list($value);
            } else {
                echo esc_html((string) $value);
            }
            echo '</li>';
        }
        echo '</ul>';
    }
}
