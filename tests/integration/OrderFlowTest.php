<?php

namespace QNBPay\Tests\Integration;

use QNBPay\Gateway\Gateway;
use QNBPay\Gateway\OrderStore;
use QNBPay\Install\Installer;

/**
 * Exercises the checkstatus-driven order completion against a real WC_Order,
 * with the QNBPay HTTP calls stubbed via the pre_http_request filter.
 *
 * @group integration
 */
class OrderFlowTest extends \WP_UnitTestCase
{
    public function set_up(): void
    {
        parent::set_up();

        update_option('woocommerce_qnbpay_settings', [
            'enabled' => 'yes',
            'testmode' => 'yes',
            'merchant_key' => 'test-merchant-key',
            'app_key' => 'test-app-key',
            'app_secret' => 'test-app-secret',
            'order_status' => 'wc-completed',
            'enable_3d' => 'yes',
            'installment' => 'no',
        ]);

        // Ensure the plugin's custom tables exist.
        delete_option(Installer::DB_VERSION_OPTION);
        Installer::maybe_upgrade();
    }

    /**
     * Stub QNBPay token + checkstatus HTTP responses.
     *
     * @param mixed  $pre
     * @param array  $args
     * @param string $url
     * @return array|mixed
     */
    public function mock_http($pre, $args, $url)
    {
        if (strpos($url, '/token') !== false) {
            return [
                'response' => ['code' => 200],
                'body' => wp_json_encode(['data' => ['token' => 'stub-token']]),
            ];
        }
        if (strpos($url, '/checkstatus') !== false) {
            return [
                'response' => ['code' => 200],
                'body' => wp_json_encode([
                    'status_code' => 100,
                    'md_status' => 1,
                    'transaction_id' => 'TXN-1',
                    'order_id' => 'ORD-1',
                ]),
            ];
        }

        return $pre;
    }

    public function test_successful_checkstatus_completes_the_order(): void
    {
        add_filter('pre_http_request', [$this, 'mock_http'], 10, 3);

        $order = wc_create_order();
        $fee = new \WC_Order_Item_Fee();
        $fee->set_name('Test');
        $fee->set_total('20');
        $order->add_item($fee);
        $order->set_payment_method('qnbpay');
        $order->calculate_totals();
        $order->update_status('pending');
        $order->save();

        $invoice_id = 'WC_' . $order->get_id() . '_ABC123';
        $order->update_meta_data(OrderStore::META_INVOICE, $invoice_id);
        $order->save();

        $gateway = new Gateway();
        $result = $gateway->finalize_from_checkstatus($order, $invoice_id, 'test');

        $this->assertTrue($result);

        $fresh = wc_get_order($order->get_id());
        $this->assertTrue($fresh->is_paid(), 'Order should be marked paid after a successful checkstatus.');
        $this->assertSame('TXN-1', $fresh->get_meta(OrderStore::META_TRANSACTION_ID));
    }

    public function test_invoice_id_resolves_back_to_order(): void
    {
        $order = wc_create_order();
        $order->set_payment_method('qnbpay');
        $order->save();

        $store = new OrderStore(\QNBPay\Plugin::instance()->logger());
        $invoice_id = $store->create_invoice_id($order, 'WC');

        $this->assertSame($order->get_id(), $store->resolve_order_id($invoice_id));
    }

    public function test_process_refund_executes_refund_api(): void
    {
        add_filter('pre_http_request', function ($pre, $args, $url) {
            if (strpos($url, '/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => wp_json_encode(['data' => ['token' => 'stub-token']]),
                ];
            }
            if (strpos($url, '/refund') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => wp_json_encode([
                        'status_code' => 100,
                        'status_description' => 'Refund successful',
                    ]),
                ];
            }

            return $pre;
        }, 10, 3);

        $order = wc_create_order();
        $order->set_payment_method('qnbpay');
        $order->save();

        $invoice_id = 'WC_' . $order->get_id() . '_REFUND123';
        $order->update_meta_data(OrderStore::META_INVOICE, $invoice_id);
        $order->save();

        $gateway = new Gateway();
        $refund_result = $gateway->process_refund($order->get_id(), 10.00, 'Customer requested refund');

        $this->assertTrue($refund_result);
    }

    public function test_failed_checkstatus_marks_order_failed(): void
    {
        add_filter('pre_http_request', function ($pre, $args, $url) {
            if (strpos($url, '/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => wp_json_encode(['data' => ['token' => 'stub-token']]),
                ];
            }
            if (strpos($url, '/checkstatus') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => wp_json_encode([
                        'status_code' => 102,
                        'status_description' => 'Payment declined by bank',
                    ]),
                ];
            }

            return $pre;
        }, 10, 3);

        $order = wc_create_order();
        $order->set_payment_method('qnbpay');
        $order->update_status('pending');
        $order->save();

        $invoice_id = 'WC_' . $order->get_id() . '_FAIL123';
        $order->update_meta_data(OrderStore::META_INVOICE, $invoice_id);
        $order->save();

        $gateway = new Gateway();
        $result = $gateway->finalize_from_checkstatus($order, $invoice_id, 'test_fail', true);

        $this->assertFalse($result);

        $fresh = wc_get_order($order->get_id());
        $this->assertSame('failed', $fresh->get_status());
    }
}
