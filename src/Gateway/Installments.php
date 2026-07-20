<?php

namespace QNBPay\Gateway;

use QNBPay\Api\Client;
use QNBPay\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Installment calculation and rendering.
 *
 * Shared by the gateway (server-side installment clamping) and the AJAX BIN
 * lookup used to render the installment table on the checkout form.
 *
 * @since 2.0.0
 */
class Installments
{
    /** @var Client */
    private $client;

    /** @var bool */
    private $enabled;

    /** @var bool */
    private $limitByProduct;

    /** @var bool */
    private $limitByCart;

    /** @var float */
    private $limitByCartAmount;

    /** @var int */
    private $globalLimit;

    /**
     * @param array  $settings
     * @param Client $client
     */
    public function __construct(array $settings, Client $client)
    {
        $this->client = $client;
        $this->enabled = 'yes' === Arr::get($settings, 'installment', 'no');
        $this->limitByProduct = 'yes' === Arr::get($settings, 'limitInstallmentByProduct', 'no');
        $this->limitByCart = 'yes' === Arr::get($settings, 'limitInstallmentByCart', 'no');
        $this->limitByCartAmount = (float) Arr::get($settings, 'limitInstallmentByCartAmount', 0);
        $this->globalLimit = (int) Arr::get($settings, 'limitInstallment', 12);
    }

    /**
     * Calculate the maximum allowed installment for the current context.
     *
     * @param string         $state 'cart' or 'order'
     * @param \WC_Order|null  $order
     * @return int
     */
    public function max_installment($state = 'cart', $order = null)
    {
        if (!$this->enabled) {
            return 1;
        }

        $limit = $this->globalLimit > 0 ? $this->globalLimit : 12;

        if (!$this->limitByProduct) {
            return $limit;
        }

        $items = [];
        if ('order' === $state && $order instanceof \WC_Order) {
            $items = $order->get_items();
        } elseif (function_exists('WC') && WC()->cart) {
            $items = WC()->cart->get_cart();
        }

        foreach ($items as $item) {
            $product_id = 0;
            if ($item instanceof \WC_Order_Item_Product) {
                $product_id = $item->get_product_id();
            } elseif (is_array($item) && isset($item['product_id'])) {
                $product_id = (int) $item['product_id'];
            }
            if ($product_id) {
                $product_limit = (int) get_post_meta($product_id, '_limitInstallment', true);
                if ($product_limit > 0 && $product_limit < $limit) {
                    $limit = $product_limit;
                }
            }
        }

        // Minimum-amount rule.
        if ($this->limitByCart && $this->limitByCartAmount > 0) {
            $total = 0.0;
            if ('order' === $state && $order instanceof \WC_Order) {
                $total = (float) $order->get_total();
            } elseif (function_exists('WC') && WC()->cart) {
                $total = (float) WC()->cart->get_cart_contents_total();
            }
            if ($total < $this->limitByCartAmount) {
                $limit = 1;
            }
        }

        return $limit;
    }

    /**
     * Validate a BIN and return installment options + rendered HTML.
     *
     * @param array $args {binNumber, state, order, ordertotal, currency}
     * @return array {status, message, cardInformation, maxInstallment, html}
     */
    public function validate_bin(array $args)
    {
        $result = [
            'status' => false,
            'message' => '',
            'cardInformation' => [],
            'maxInstallment' => 1,
            'html' => '',
        ];

        $bin = substr(preg_replace('/\D+/', '', Arr::str(Arr::get($args, 'binNumber'))), 0, 8);
        if (strlen($bin) !== 8) {
            $result['message'] = __('Credit card number is required.', 'qnbpay-woocommerce');

            return $result;
        }

        $state = Arr::get($args, 'state', 'cart');
        $order = Arr::get($args, 'order');
        $max = $this->max_installment($state, $order instanceof \WC_Order ? $order : null);

        $total = Arr::get($args, 'ordertotal');
        if (!$total) {
            $total = $this->context_total($state, $order instanceof \WC_Order ? $order : null);
        }
        $currency = Arr::get($args, 'currency');
        if (!$currency) {
            $currency = $this->context_currency($state, $order instanceof \WC_Order ? $order : null);
        }

        $response = $this->client->get_pos([
            'credit_card' => $bin,
            'amount' => $total,
            'currency_code' => $currency,
        ]);

        $installments = $response['status'] ? $response['body'] : [];

        $result['status'] = true;
        $result['message'] = $response['status'] ? __('Success', 'qnbpay-woocommerce') : $response['message'];
        $result['cardInformation'] = is_array($installments) ? $installments : [];
        $result['maxInstallment'] = $max;
        $result['html'] = $this->render($result['cardInformation'], $max, $state);

        return $result;
    }

    /**
     * Clamp a chosen installment number to what the card/context allows.
     *
     * @param int       $installment
     * @param \WC_Order  $order
     * @param string    $bin
     * @return int
     */
    public function clamp($installment, \WC_Order $order, $bin)
    {
        $check = $this->validate_bin([
            'binNumber' => $bin,
            'state' => 'order',
            'order' => $order,
            'ordertotal' => $order->get_total(),
            'currency' => $order->get_currency(),
        ]);

        $allowed = 1;
        if (!empty($check['cardInformation']) && is_array($check['cardInformation'])) {
            foreach ($check['cardInformation'] as $card) {
                $no = (int) Arr::get($card, 'installments_number', 0);
                if ($no === (int) $installment) {
                    $allowed = $no;
                    break;
                }
            }
        }

        $installment = $allowed > 0 ? $allowed : (int) $installment;

        $max = (int) $check['maxInstallment'];
        if ($max > 0 && $installment > $max) {
            $installment = $max;
        }

        return $installment > 0 ? $installment : 1;
    }

    /**
     * Render the installment selection HTML.
     *
     * @param array  $installments
     * @param int    $max
     * @param string $state
     * @return string
     */
    public function render($installments, $max, $state = 'cart')
    {
        $html = '<input type="hidden" name="qnbpay-order-state" value="' . esc_attr($state) . '">';

        if (!$installments || (int) $max === 1) {
            return $html . '<input type="hidden" name="qnbpay-installment" value="1">';
        }

        $html .= '<div class="qnbpay-installments">';
        $html .= '<div class="qnbpay-installments-title">' . esc_html__('Installment Selection', 'qnbpay-woocommerce') . '</div>';

        foreach ($installments as $row) {
            $no = (int) Arr::get($row, 'installments_number', 0);
            if ($no < 1 || $no > $max) {
                continue;
            }
            $label = 1 === $no
                ? esc_html__('Payment In Advance', 'qnbpay-woocommerce')
                : sprintf(esc_html__('%d installments', 'qnbpay-woocommerce'), $no);
            $checked = 1 === $no ? ' checked' : '';
            $html .= '<div class="qnbpay-installments-row">'
                . '<input' . $checked . ' id="installment-pick' . $no . '" type="radio" class="input-radio w-w-50" name="qnbpay-installment" value="' . $no . '">'
                . '<label for="installment-pick' . $no . '"> ' . $label . '</label>'
                . '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @param string        $state
     * @param \WC_Order|null $order
     * @return float
     */
    private function context_total($state, $order)
    {
        if ('order' === $state && $order instanceof \WC_Order) {
            return (float) $order->get_total();
        }
        if (function_exists('WC') && WC()->cart) {
            return (float) WC()->cart->get_total('edit');
        }

        return 0.0;
    }

    /**
     * @param string        $state
     * @param \WC_Order|null $order
     * @return string
     */
    private function context_currency($state, $order)
    {
        if ('order' === $state && $order instanceof \WC_Order) {
            return $order->get_currency();
        }

        return get_woocommerce_currency();
    }
}
