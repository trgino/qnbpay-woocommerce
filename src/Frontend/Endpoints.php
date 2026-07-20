<?php

namespace QNBPay\Frontend;

use QNBPay\Gateway\Gateway;
use QNBPay\Gateway\OrderStore;
use QNBPay\Plugin;
use QNBPay\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom front-end endpoints for the 3D Secure redirect flow.
 *
 *  - /qnbpayform/{order_id}/   renders the (transient) auto-submit bank form.
 *  - /qnbpayresult/{order_id}/ receives the bank's POST and finalizes the order.
 *
 * @since 2.0.0
 */
class Endpoints
{
    /** @var Plugin */
    private $plugin;

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register()
    {
        add_action('init', [$this, 'rewrites']);
        add_filter('query_vars', [$this, 'query_vars']);
        add_action('template_redirect', [$this, 'handle']);
    }

    /**
     * Register rewrite rules.
     *
     * @return void
     */
    public function rewrites()
    {
        add_rewrite_rule('^qnbpayform/([0-9]+)/?', 'index.php?qnbpayform=$matches[1]', 'top');
        add_rewrite_rule('^qnbpayresult/([0-9]+)/?', 'index.php?qnbpayresult=$matches[1]', 'top');
    }

    /**
     * Register query vars.
     *
     * @param array $vars
     * @return array
     */
    public function query_vars($vars)
    {
        $vars[] = 'qnbpayform';
        $vars[] = 'qnbpayresult';

        return $vars;
    }

    /**
     * Route the request.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $form_order = (int) get_query_var('qnbpayform');
            if ($form_order > 0) {
                $this->render_form($form_order);

                return;
            }

            $result_order = (int) get_query_var('qnbpayresult');
            if ($result_order > 0) {
                $this->handle_result($result_order);
            }
        } catch (\Throwable $e) {
            $this->plugin->logger()->exception('endpoint', $e);
            // Fall back to a safe page; the order stays pending and the cron
            // reconciler will finalize it once the bank confirms.
            if (!headers_sent()) {
                wp_safe_redirect(wc_get_checkout_url());
            }
            exit;
        }
    }

    /**
     * Render the auto-submit bank form (from a single-use transient).
     *
     * @param int $order_id
     * @return void
     */
    private function render_form($order_id)
    {
        $this->no_cache();

        $order = wc_get_order($order_id);
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        $token = isset($_GET['t']) ? sanitize_text_field(wp_unslash($_GET['t'])) : '';
        // phpcs:enable

        if (!$order || !$key || !hash_equals($order->get_order_key(), $key) || '' === $token) {
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }

        $stored = get_transient('qnbpay_pay_' . $token);
        // Single-use: delete regardless of outcome.
        delete_transient('qnbpay_pay_' . $token);

        if (!is_array($stored) || (int) Arr::get($stored, 'order_id') !== $order_id || empty($stored['form'])) {
            wp_safe_redirect($order->get_checkout_payment_url());
            exit;
        }

        $this->set_404_false();
        header('Content-Type: text/html; charset=utf-8');
        // The form is generated internally and its inputs are escaped in the gateway.
        echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>QNBPay</title></head><body>'
            . $stored['form'] // phpcs:ignore WordPress.Security.EscapeOutput
            . '</body></html>';
        exit;
    }

    /**
     * Handle the bank's payment result POST.
     *
     * @param int $order_id
     * @return void
     */
    private function handle_result($order_id)
    {
        $this->no_cache();

        $order = wc_get_order($order_id);
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        // phpcs:enable

        if (!$order || !$key || !hash_equals($order->get_order_key(), $key)) {
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }

        $this->set_404_false();

        $orders = $this->plugin->orders();
        $gateway = new Gateway();

        // If already paid, go straight to the thank-you page.
        if ($order->is_paid() || $order->has_status(wc_get_is_paid_statuses())) {
            wp_safe_redirect($order->get_checkout_order_received_url());
            exit;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $post = ('POST' === (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '')) ? map_deep(wp_unslash($_POST), 'wc_clean') : [];
        // phpcs:enable
        $payment_status = Arr::get($post, 'payment_status', Arr::get($post, 'qnbpay_status', 0));
        $invoice_id = Arr::str(Arr::get($post, 'invoice_id', $orders->get_meta($order, OrderStore::META_INVOICE)));

        $orders->log($order_id, $invoice_id, 'qnbReply', $post);

        // Explicit rejection.
        if ((string) $payment_status === '0' || '' === $invoice_id) {
            $reason = Arr::str(Arr::get($post, 'status_description', __('Payment for your order has been rejected by the payment broker.', 'qnbpay-woocommerce')));
            $orders->set_meta($order, OrderStore::META_ERROR, $reason);
            wp_safe_redirect($this->pay_error_url($order));
            exit;
        }

        $orders->set_meta($order, OrderStore::META_INVOICE, $invoice_id);

        // Verify with checkstatus (source of truth).
        $status = $this->plugin->client()->check_status($invoice_id);
        if (!$status['status']) {
            $orders->set_meta($order, OrderStore::META_RECHECK, __('Your payment is being processed. Please wait...', 'qnbpay-woocommerce'));
            wp_safe_redirect(add_query_arg(['qnbpayrecheck' => 1, 'key' => $order->get_order_key()], wc_get_endpoint_url('order-pay', (string) $order_id, wc_get_checkout_url())));
            exit;
        }

        if ($gateway->finalize_from_checkstatus($order, $invoice_id, 'result')) {
            wp_safe_redirect(add_query_arg(['qnbpaysuccess' => 1], $order->get_checkout_order_received_url()));
            exit;
        }

        $reason = Arr::str(Arr::get($status['body'], 'status_description', __('Payment has not been confirmed.', 'qnbpay-woocommerce')));
        $orders->set_meta($order, OrderStore::META_ERROR, $reason);
        wp_safe_redirect($this->pay_error_url($order));
        exit;
    }

    /**
     * Build the order-pay URL with an error flag.
     *
     * @param \WC_Order $order
     * @return string
     */
    private function pay_error_url(\WC_Order $order)
    {
        return add_query_arg(
            ['qnbpayerror' => 1, 'pay_for_order' => true, 'key' => $order->get_order_key()],
            wc_get_endpoint_url('order-pay', (string) $order->get_id(), wc_get_checkout_url())
        );
    }

    /**
     * Prevent caching of these dynamic endpoints.
     *
     * @return void
     */
    private function no_cache()
    {
        if (!defined('LSCACHE_NO_CACHE')) {
            define('LSCACHE_NO_CACHE', true);
        }
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();
    }

    /**
     * Suppress the 404 state for our virtual endpoints.
     *
     * @return void
     */
    private function set_404_false()
    {
        global $wp_query;
        if ($wp_query instanceof \WP_Query) {
            $wp_query->is_404 = false;
            status_header(200);
        }
    }
}
