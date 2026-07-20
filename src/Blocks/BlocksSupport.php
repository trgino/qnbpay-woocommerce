<?php

namespace QNBPay\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use QNBPay\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce Cart/Checkout Blocks integration for QNBPay.
 *
 * @since 2.0.0
 */
final class BlocksSupport extends AbstractPaymentMethodType
{
    /** @var string */
    protected $name = 'qnbpay';

    /** @var array */
    private $gateway_settings = [];

    /**
     * Load the gateway settings.
     *
     * @return void
     */
    public function initialize()
    {
        $this->settings = get_option('woocommerce_qnbpay_settings', []);
        if (!is_array($this->settings)) {
            $this->settings = [];
        }
        $this->gateway_settings = $this->settings;
    }

    /**
     * Whether the payment method is active.
     *
     * @return bool
     */
    public function is_active()
    {
        return 'yes' === Arr::get($this->gateway_settings, 'enabled', 'no');
    }

    /**
     * Register the block script and return its handle(s).
     *
     * @return string[]
     */
    public function get_payment_method_script_handles()
    {
        $handle = 'qnbpay-blocks';
        $deps = ['wc-blocks-registry', 'wp-element', 'wp-i18n', 'wp-html-entities', 'jquery'];

        wp_register_script(
            $handle,
            QNBPAY_URL . 'assets/blocks/qnbpay-blocks.js',
            $deps,
            QNBPAY_VERSION,
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations($handle, 'qnbpay-for-woocommerce');
        }

        return [$handle];
    }

    /**
     * Data passed to the block script.
     *
     * @return array
     */
    public function get_payment_method_data()
    {
        return [
            'title' => Arr::str(Arr::get($this->gateway_settings, 'title', __('Credit Card', 'qnbpay-for-woocommerce'))),
            'description' => Arr::str(Arr::get($this->gateway_settings, 'description')),
            'testmode' => 'yes' === Arr::get($this->gateway_settings, 'testmode', 'no'),
            'installment' => 'yes' === Arr::get($this->gateway_settings, 'installment', 'yes'),
            'supports' => ['products', 'refunds'],
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('qnbpay_ajax_nonce'),
            'icon' => QNBPAY_URL . 'assets/img/qnbpay.png',
        ];
    }
}
