<?php

namespace QNBPay\Gateway;

use QNBPay\Support\Arr;
use QNBPay\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * HPOS-safe order data access layer.
 *
 * All order metadata is read/written through the WC_Order object (get_meta /
 * update_meta_data / save) so the plugin works identically under legacy post
 * storage and High-Performance Order Storage (HPOS). Also owns invoice-id
 * generation/resolution and the transaction log table.
 *
 * @since 2.0.0
 */
class OrderStore
{
    const META_INVOICE = '_qnbpay_invoice_id';
    const META_ORDER_HASH = '_qnbpay_order_hash';
    const META_QNB_ORDER_ID = '_qnbpay_order_id';
    const META_TRANSACTION_ID = '_qnbpay_transaction_id';
    const META_ERROR = '_qnbpay_error';
    const META_RECHECK = '_qnbpay_recheck';

    /** @var Logger */
    private $logger;

    /**
     * @param Logger $logger
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Read a meta value from an order (HPOS-safe).
     *
     * @param \WC_Order $order
     * @param string    $key
     * @param mixed     $default
     * @return mixed
     */
    public function get_meta(\WC_Order $order, $key, $default = '')
    {
        $value = $order->get_meta($key, true);

        return ('' === $value || null === $value) ? $default : $value;
    }

    /**
     * Resolve the QNBPay invoice id for an order, with backward-compatible
     * fallbacks to the legacy (< 2.0.0) meta keys (_uuid, qnbpay_invoice_id).
     *
     * @param \WC_Order $order
     * @return string
     */
    public function get_invoice_id(\WC_Order $order)
    {
        foreach ([self::META_INVOICE, '_uuid', 'qnbpay_invoice_id'] as $key) {
            $value = $order->get_meta($key, true);
            if ('' !== $value && null !== $value) {
                return (string) $value;
            }
        }

        return '';
    }

    /**
     * Write a meta value on an order and persist immediately (HPOS-safe).
     *
     * @param \WC_Order $order
     * @param string    $key
     * @param mixed     $value
     * @return void
     */
    public function set_meta(\WC_Order $order, $key, $value)
    {
        $order->update_meta_data($key, $value);
        $order->save();
    }

    /**
     * Delete a meta value from an order (HPOS-safe).
     *
     * @param \WC_Order $order
     * @param string    $key
     * @return void
     */
    public function delete_meta(\WC_Order $order, $key)
    {
        $order->delete_meta_data($key);
        $order->save();
    }

    /**
     * Generate and persist a unique, resolvable invoice id for an order.
     *
     * Format: {prefix}_{orderId}_{unique}. The order id is embedded so webhook
     * payloads remain resolvable, and the mapping is also stored in the lookup
     * table as a secondary resolution path.
     *
     * @param \WC_Order $order
     * @param string    $prefix
     * @return string
     */
    public function create_invoice_id(\WC_Order $order, $prefix = 'WC')
    {
        global $wpdb;

        $order_id = $order->get_id();
        $prefix = preg_replace('/[^A-Za-z0-9]/', '', (string) $prefix);
        if ('' === $prefix) {
            $prefix = 'WC';
        }

        $table = $wpdb->prefix . 'qnbpay_orders_ids';

        // Try a handful of times to avoid an (extremely unlikely) collision.
        for ($i = 0; $i < 5; $i++) {
            $unique = strtoupper(substr(md5(uniqid((string) wp_rand(), true)), 0, 12));
            $invoice_id = $prefix . '_' . $order_id . '_' . $unique;

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $inserted = $wpdb->insert(
                $table,
                [
                    'orderid' => $order_id,
                    'invoiceid' => $invoice_id,
                    'createdate' => gmdate('Y-m-d H:i:s'),
                ],
                ['%d', '%s', '%s']
            );

            if (false !== $inserted) {
                return $invoice_id;
            }
        }

        // Fallback: deterministic but still unique enough.
        return $prefix . '_' . $order_id . '_' . strtoupper(substr(md5((string) microtime(true)), 0, 12));
    }

    /**
     * Resolve a WooCommerce order id from a QNBPay invoice id.
     *
     * Primary path: the lookup table. Fallback: parse the embedded order id.
     *
     * @param string $invoice_id
     * @return int 0 when it cannot be resolved.
     */
    public function resolve_order_id($invoice_id)
    {
        global $wpdb;

        $invoice_id = (string) $invoice_id;
        if ('' === $invoice_id) {
            return 0;
        }

        $table = $wpdb->prefix . 'qnbpay_orders_ids';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
        $found = $wpdb->get_var(
            $wpdb->prepare("SELECT orderid FROM {$table} WHERE invoiceid = %s ORDER BY createdate DESC LIMIT 1", $invoice_id)
        );
        if ($found) {
            return (int) $found;
        }

        // Fallback: {prefix}_{orderId}_{unique}
        $parts = explode('_', $invoice_id);
        if (count($parts) >= 2 && ctype_digit((string) $parts[1])) {
            return (int) $parts[1];
        }

        return 0;
    }

    /**
     * Persist a transaction log row (payloads are masked).
     *
     * @param int    $order_id
     * @param string $invoice_id
     * @param string $action
     * @param mixed  $data
     * @param mixed  $details
     * @return void
     */
    public function log($order_id, $invoice_id, $action, $data, $details = [])
    {
        global $wpdb;

        try {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->insert(
                $wpdb->prefix . 'qnbpay_orders',
                [
                    'orderid' => (int) $order_id,
                    'invoiceid' => (string) $invoice_id,
                    'createdate' => gmdate('Y-m-d H:i:s'),
                    'action' => (string) $action,
                    'data' => wp_json_encode(Logger::mask($data)),
                    'details' => wp_json_encode(Logger::mask($details)),
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s']
            );
        } catch (\Throwable $e) {
            $this->logger->exception('order-log', $e);
        }
    }
}
