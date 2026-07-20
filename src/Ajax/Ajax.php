<?php

namespace QNBPay\Ajax;

use QNBPay\Gateway\Gateway;
use QNBPay\Gateway\Installments;
use QNBPay\Plugin;
use QNBPay\Support\Arr;
use QNBPay\Support\Util;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX request router for the QNBPay gateway.
 *
 * @since 2.0.0
 */
class Ajax
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
        add_action('wp_ajax_qnbpay_ajax', [$this, 'dispatch']);
        add_action('wp_ajax_nopriv_qnbpay_ajax', [$this, 'dispatch']);
    }

    /**
     * Route the AJAX request based on the "method" parameter.
     *
     * @return void
     */
    public function dispatch()
    {
        $posted = map_deep(wp_unslash($_POST), 'wc_clean'); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $method = Arr::str(Arr::get($posted, 'method'));

        if (!wp_verify_nonce(Arr::str(Arr::get($posted, 'nonce')), 'qnbpay_ajax_nonce')) {
            wp_send_json([
                'status' => false,
                'retry' => true,
                'message' => __('Invalid nonce', 'qnbpay-for-woocommerce'),
            ]);
        }

        try {
            switch ($method) {
                case 'validate_bin':
                    $this->validate_bin($posted);
                    break;
                case 'recheckpayment':
                    $this->recheck_payment($posted);
                    break;
                case 'qnbpay_test':
                    $this->test($posted);
                    break;
                default:
                    wp_send_json(['status' => false, 'message' => __('Unknown request.', 'qnbpay-for-woocommerce')]);
            }
        } catch (\Throwable $e) {
            $this->plugin->logger()->exception('ajax:' . $method, $e);
            wp_send_json(['status' => false, 'message' => __('An unexpected error occurred.', 'qnbpay-for-woocommerce')]);
        }
    }

    /**
     * BIN lookup + installment table rendering.
     *
     * @param array $posted
     * @return void
     */
    private function validate_bin(array $posted)
    {
        $service = new Installments($this->plugin->settings(), $this->plugin->client());

        $order = null;
        if ('order' === Arr::get($posted, 'state') && get_query_var('order-pay')) {
            $order = wc_get_order((int) get_query_var('order-pay'));
        }

        $result = $service->validate_bin([
            'binNumber' => Arr::get($posted, 'binNumber'),
            'state' => Arr::get($posted, 'state', 'cart'),
            'order' => $order,
        ]);

        wp_send_json($result);
    }

    /**
     * Recheck a pending payment via checkstatus.
     *
     * @param array $posted
     * @return void
     */
    private function recheck_payment(array $posted)
    {
        $order_id = absint(Arr::get($posted, 'orderid', 0));
        $result = ['status' => false, 'retry' => false, 'url' => false, 'message' => ''];

        if (!$order_id) {
            $result['message'] = __('Order ID is required.', 'qnbpay-for-woocommerce');
            wp_send_json($result);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            $result['message'] = __('Order not found.', 'qnbpay-for-woocommerce');
            wp_send_json($result);
        }

        $invoice_id = $this->plugin->orders()->get_invoice_id($order);
        if (empty($invoice_id)) {
            $result['message'] = __('Invoice reference missing.', 'qnbpay-for-woocommerce');
            wp_send_json($result);
        }

        try {
            $gateway = new Gateway();
            $status = $this->plugin->client()->check_status($invoice_id);

            if (!$status['status']) {
                $result['retry'] = true;
                $result['message'] = __('Check result not found. Trying again.', 'qnbpay-for-woocommerce');
                wp_send_json($result);
            }

            if ($gateway->finalize_from_checkstatus($order, $invoice_id, 'recheck')) {
                $result['status'] = true;
                $result['message'] = __('Your order has been paid successfully.', 'qnbpay-for-woocommerce');
                $result['url'] = add_query_arg(['qnbpaysuccess' => 1], $order->get_checkout_order_received_url());
                wp_send_json($result);
            }

            $result['message'] = __('Payment has not been confirmed.', 'qnbpay-for-woocommerce');
            $result['url'] = add_query_arg(
                ['qnbpayerror' => 1, 'pay_for_order' => true, 'key' => $order->get_order_key()],
                wc_get_endpoint_url('order-pay', (string) $order_id, wc_get_checkout_url())
            );
            wp_send_json($result);
        } catch (\Throwable $e) {
            $this->plugin->logger()->exception('recheck', $e);
            $result['retry'] = true;
            $result['message'] = __('An error occurred. Trying again.', 'qnbpay-for-woocommerce');
            wp_send_json($result);
        }
    }

    /**
     * Admin connection / credentials test.
     *
     * @param array $posted
     * @return void
     */
    private function test(array $posted)
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json(['status' => false, 'message' => __('Permission denied.', 'qnbpay-for-woocommerce')]);
        }

        $result = [
            'status' => false,
            'remote' => true,
            'commissioncheck' => false,
            'bincheck' => false,
            'message' => '',
        ];

        $settings = $this->plugin->settings();
        if (
            !Arr::get($settings, 'merchant_id') || !Arr::get($settings, 'merchant_key') ||
            !Arr::get($settings, 'app_key') || !Arr::get($settings, 'app_secret')
        ) {
            $result['message'] = __('The test function can be performed after saving the merchant information.', 'qnbpay-for-woocommerce');
            wp_send_json($result);
        }

        $client = $this->plugin->client();
        $commission = $client->get_commissions();
        $result['commissioncheck'] = (bool) $commission['status'];

        $cards = Util::test_cards();
        $card = $cards[array_rand($cards)];
        $bin = $client->get_pos(['credit_card' => substr($card, 0, 8)]);
        $result['bincheck'] = (bool) $bin['status'];
        $result['status'] = true;

        wp_send_json($result);
    }
}
