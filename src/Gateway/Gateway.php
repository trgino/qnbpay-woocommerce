<?php

namespace QNBPay\Gateway;

use QNBPay\Api\Client;
use QNBPay\Plugin;
use QNBPay\Support\Arr;
use QNBPay\Support\Util;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * QNBPay WooCommerce payment gateway.
 *
 * @since 2.0.0
 */
class Gateway extends \WC_Payment_Gateway
{
    /** @var bool */
    public $testmode;

    /** @var bool */
    public $installment;

    /** @var bool */
    public $enable_3d;

    /** @var string */
    public $merchant_key;

    /** @var string */
    public $merchant_id;

    /** @var string */
    public $app_key;

    /** @var string */
    public $app_secret;

    /** @var string */
    public $order_status;

    /** @var string */
    public $order_prefix;

    /** @var string */
    public $sale_web_hook_key;

    /** @var Installments|null */
    private $installments_service = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->id = 'qnbpay';
        $this->icon = '';
        $this->has_fields = true;
        $this->method_title = 'QNBPay';
        $this->method_description = __('QNBPay WooCommerce Gateway', 'qnbpay-for-woocommerce');
        $this->supports = ['products', 'refunds'];

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = 'yes' === $this->get_option('testmode', 'no');
        $this->installment = 'yes' === $this->get_option('installment', 'yes');
        $this->enable_3d = 'yes' === $this->get_option('enable_3d', 'yes');
        $this->merchant_key = $this->get_option('merchant_key');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->app_key = $this->get_option('app_key');
        $this->app_secret = $this->get_option('app_secret');
        $this->order_status = $this->get_option('order_status', 'wc-completed');
        $this->order_prefix = $this->get_option('order_prefix', 'WC');
        $this->sale_web_hook_key = $this->get_option('sale_web_hook_key');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, function () {
            $this->process_admin_options();
        });
        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
        add_filter('woocommerce_credit_card_form_fields', [$this, 'payment_form_fields'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_filter('woocommerce_generate_qnbhr_html', [$this, 'generate_qnbhr_html']);
        add_action('woocommerce_api_wc_gateway_' . $this->id, [$this, 'handle_webhook']);
        add_action('woocommerce_thankyou_' . $this->id, [$this, 'clear_cart_after_order']);
        add_action('before_woocommerce_pay', [$this, 'order_pay_notices']);
    }

    // ---------------------------------------------------------------------
    // Service accessors
    // ---------------------------------------------------------------------

    /** @return Client */
    private function client()
    {
        // Use gateway-scoped settings so freshly-saved options are respected.
        return new Client($this->settings, Plugin::instance()->logger());
    }

    /** @return OrderStore */
    private function orders()
    {
        return Plugin::instance()->orders();
    }

    /** @return Installments */
    private function installments()
    {
        if (null === $this->installments_service) {
            $this->installments_service = new Installments($this->settings, $this->client());
        }

        return $this->installments_service;
    }

    // ---------------------------------------------------------------------
    // Settings
    // ---------------------------------------------------------------------

    /**
     * Horizontal-rule pseudo field.
     *
     * @return string
     */
    public function generate_qnbhr_html()
    {
        return '<tr valign="top"><td colspan="2"><hr /></td></tr>';
    }

    /**
     * Webhook secret hash (URL key).
     *
     * @return string
     */
    public function get_webhook_hash()
    {
        $hash = get_option($this->id . '_webhook_hash', false);
        if ($hash) {
            return $hash;
        }
        $hash = wp_generate_password(20, false);
        update_option($this->id . '_webhook_hash', $hash);

        return $hash;
    }

    /**
     * Define admin settings fields.
     *
     * @return void
     */
    public function init_form_fields()
    {
        $installment_options = [];
        for ($i = 1; $i <= 12; $i++) {
            /* translators: %s: number of installments */
            $installment_options[$i] = sprintf(__('%s Installment', 'qnbpay-for-woocommerce'), $i);
        }

        $webhook_url = add_query_arg(
            ['key' => $this->get_webhook_hash()],
            home_url('/wc-api/WC_Gateway_' . $this->id . '/')
        );

        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'qnbpay-for-woocommerce'),
                'label' => __('Enable QNBPay Gateway?', 'qnbpay-for-woocommerce'),
                'type' => 'checkbox',
                'default' => 'no',
            ],
            'title' => [
                'title' => __('Title', 'qnbpay-for-woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'qnbpay-for-woocommerce'),
                'default' => __('Credit Card', 'qnbpay-for-woocommerce'),
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Description', 'qnbpay-for-woocommerce'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'qnbpay-for-woocommerce'),
                'default' => __('Pay securely with your credit card.', 'qnbpay-for-woocommerce'),
            ],
            'qnbhr1' => ['type' => 'qnbhr'],
            'merchant_key' => ['title' => __('Merchant Key', 'qnbpay-for-woocommerce'), 'type' => 'text'],
            'merchant_id' => ['title' => __('Merchant ID', 'qnbpay-for-woocommerce'), 'type' => 'text'],
            'app_key' => ['title' => __('App Key', 'qnbpay-for-woocommerce'), 'type' => 'text'],
            'app_secret' => ['title' => __('App Secret', 'qnbpay-for-woocommerce'), 'type' => 'password'],
            'order_prefix' => [
                'title' => __('Order Prefix', 'qnbpay-for-woocommerce'),
                'type' => 'text',
                'description' => __('Prefix added to the invoice id sent to QNBPay (letters and numbers only).', 'qnbpay-for-woocommerce'),
                'default' => 'WC',
                'desc_tip' => true,
            ],
            'qnbhr2' => ['type' => 'qnbhr'],
            'sale_web_hook_key' => [
                'title' => __('Sale Webhook Key', 'qnbpay-for-woocommerce'),
                'type' => 'text',
                'description' => __('Enter the key you defined in the QNBPay merchant panel for webhook notifications. Webhook URL: ', 'qnbpay-for-woocommerce') . '<code>' . esc_url($webhook_url) . '</code>',
            ],
            'qnbhr_webhook' => ['type' => 'qnbhr'],
            'testmode' => [
                'title' => 'Test ' . __('Enable/Disable', 'qnbpay-for-woocommerce'),
                'label' => __('Enable Test Mode?', 'qnbpay-for-woocommerce'),
                'type' => 'checkbox',
                'description' => __('Place the payment gateway in test mode using test API keys.', 'qnbpay-for-woocommerce'),
                'default' => 'yes',
                'desc_tip' => true,
            ],
            'qnbhr3' => ['type' => 'qnbhr'],
            'enable_3d' => [
                'title' => __('Enable 3D', 'qnbpay-for-woocommerce'),
                'label' => __('Enable 3D Secure Payment?', 'qnbpay-for-woocommerce'),
                'type' => 'checkbox',
                'default' => 'yes',
            ],
            'qnbhr4' => ['type' => 'qnbhr'],
            'installment' => [
                'title' => __('Installment', 'qnbpay-for-woocommerce'),
                'label' => __('Enable/Disable Installment?', 'qnbpay-for-woocommerce'),
                'type' => 'checkbox',
                'default' => 'yes',
            ],
            'limitInstallment' => [
                'title' => __('Limit Installment', 'qnbpay-for-woocommerce'),
                'type' => 'select',
                'options' => $installment_options,
                'default' => '12',
            ],
            'limitInstallmentByProduct' => [
                'title' => __('Limit Installment By Product', 'qnbpay-for-woocommerce'),
                'label' => __('Enable/Disable Installment by Product?', 'qnbpay-for-woocommerce'),
                'type' => 'checkbox',
                'default' => 'no',
            ],
            'limitInstallmentByCart' => [
                'title' => __('Limit Installment By Cart Amount', 'qnbpay-for-woocommerce'),
                'label' => __('Enable/Disable Installment by Cart Amount?', 'qnbpay-for-woocommerce'),
                'type' => 'checkbox',
                'default' => 'no',
            ],
            'limitInstallmentByCartAmount' => [
                'title' => __('Installment Minimum Cart Amount', 'qnbpay-for-woocommerce'),
                'label' => __('Orders below this amount are charged in advance.', 'qnbpay-for-woocommerce'),
                'type' => 'number',
                'default' => 0,
            ],
            'qnbhr5' => ['type' => 'qnbhr'],
            'order_status' => [
                'title' => __('Order Status', 'qnbpay-for-woocommerce'),
                'type' => 'select',
                'description' => __('Order status to set when a payment is successfully completed.', 'qnbpay-for-woocommerce'),
                'options' => wc_get_order_statuses(),
                'default' => 'wc-completed',
            ],
            'qnbhr6' => ['type' => 'qnbhr'],
            'debugMode' => [
                'title' => __('Enable/Disable Debug Mode', 'qnbpay-for-woocommerce'),
                'label' => __('Log requests to WooCommerce logs (WooCommerce > Status > Logs).', 'qnbpay-for-woocommerce'),
                'type' => 'checkbox',
                'default' => 'no',
            ],
        ];
    }

    /**
     * Validate & save admin options.
     *
     * @return bool
     */
    public function process_admin_options()
    {
        $post_data = $this->get_post_data();
        $key = Arr::str(Arr::get($post_data, 'woocommerce_qnbpay_sale_web_hook_key'));

        if ('' !== $key && !preg_match('/^[a-zA-Z0-9]+$/', $key)) {
            \WC_Admin_Settings::add_error(__('Sale webhook key must contain only letters and numbers.', 'qnbpay-for-woocommerce'));

            return false;
        }

        return parent::process_admin_options();
    }

    /**
     * Enqueue admin assets on the gateway settings screen.
     *
     * @return void
     */
    public function admin_assets()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $section = isset($_GET['section']) ? sanitize_text_field(wp_unslash($_GET['section'])) : '';
        if ('qnbpay' !== $section) {
            return;
        }

        wp_enqueue_style('qnbpay-admin', QNBPAY_URL . 'assets/qnbpay-admin.css', [], QNBPAY_VERSION);
        wp_enqueue_script('qnbpay-admin', QNBPAY_URL . 'assets/qnbpay-admin.js', ['jquery'], QNBPAY_VERSION, true);
        wp_localize_script('qnbpay-admin', 'qnbpay_ajax', $this->script_data());
    }

    /**
     * Render the admin options page.
     *
     * @return void
     */
    public function admin_options()
    {
        $return_url = admin_url('admin.php?page=wc-settings&tab=checkout');
        ?>
        <div class="qnbpay-admin-interface">
            <div class="left">
                <img src="<?php echo esc_url(QNBPAY_URL . 'assets/img/qnbpay.png'); ?>" alt="QNBPay" />
                <h2><?php esc_html_e('QNBPay Settings', 'qnbpay-for-woocommerce');
                    wc_back_link(__('Return to payments', 'qnbpay-for-woocommerce'), $return_url); ?></h2>
                <table class="form-table"><?php $this->generate_settings_html(); ?></table>
                <div class="qnbpay-admin-test-details">
                    <button type="button" class="qnbpay-admin-dotest"><?php esc_html_e('Test Informations', 'qnbpay-for-woocommerce'); ?></button>
                </div>
                <div class="qnbpay-admin-test-results"></div>
            </div>
            <div class="right">
                <div class="qnbpay">
                    <h3><?php esc_html_e('QNBPay', 'qnbpay-for-woocommerce'); ?></h3>
                    <p><?php esc_html_e('QNBPay Payment Gateway for WooCommerce', 'qnbpay-for-woocommerce'); ?></p>
                    <p><a href="https://qnbpay.com.tr/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Visit Website', 'qnbpay-for-woocommerce'); ?></a></p>
                </div>
            </div>
        </div>
        <?php
    }

    // ---------------------------------------------------------------------
    // Checkout form
    // ---------------------------------------------------------------------

    /**
     * Localized script data shared by admin and frontend.
     *
     * @return array
     */
    public function script_data()
    {
        return [
            'ajax_url' => admin_url('admin-ajax.php'),
            'version' => QNBPAY_VERSION,
            'nonce' => wp_create_nonce('qnbpay_ajax_nonce'),
            'installment_test' => __('Installment Rate Test', 'qnbpay-for-woocommerce'),
            'bin_test' => __('Bank Identification Test', 'qnbpay-for-woocommerce'),
            'remote_test' => __('Remote Connection Test', 'qnbpay-for-woocommerce'),
            'success' => __('Success', 'qnbpay-for-woocommerce'),
            'failed' => __('Failed', 'qnbpay-for-woocommerce'),
            'view_logs' => __('View logs', 'qnbpay-for-woocommerce'),
            'logs_url' => admin_url('admin.php?page=wc-status&tab=logs'),
        ];
    }

    /**
     * Custom credit card form fields.
     *
     * @param array  $cc_fields
     * @param string $payment_id
     * @return array
     */
    public function payment_form_fields($cc_fields, $payment_id)
    {
        if ($payment_id !== $this->id || 'yes' !== $this->enabled) {
            return $cc_fields;
        }

        $referer = is_wc_endpoint_url('order-pay') ? 'order-pay' : 'checkout';
        $state = get_query_var('order-pay') ? 'order' : 'cart';

        $cc_fields = [
            'current-step-of-payment' => '<p class="form-row form-row-wide">'
                . '<input id="' . $payment_id . '-current-step-of-payment" class="current-step-of-payment" type="hidden" value="' . esc_attr($referer) . '" name="' . $payment_id . '-current-step-of-payment" />'
                . '<input id="' . $payment_id . '-current-order-state" class="current-order-state" type="hidden" value="' . esc_attr($state) . '" name="' . $payment_id . '-current-order-state" /></p>',
            'name-on-card' => '<p class="form-row form-row-wide"><label for="' . $payment_id . '-card-holder">' . esc_html__('Name On Card', 'qnbpay-for-woocommerce') . ' <span class="required">*</span></label>'
                . '<input id="' . $payment_id . '-card-holder" class="input-text wc-credit-card-form-card-holder" type="text" autocomplete="off" placeholder="' . esc_attr__('Name On Card', 'qnbpay-for-woocommerce') . '" name="' . $payment_id . '-name-oncard" /></p>',
            'card-number-field' => '<p class="form-row form-row-wide"><label for="' . $payment_id . '-card-number">' . esc_html__('Card Number', 'qnbpay-for-woocommerce') . ' <span class="required">*</span></label>'
                . '<input id="' . $payment_id . '-card-number" class="input-text wc-credit-card-form-card-number" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" inputmode="numeric" type="tel" maxlength="24" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" name="' . $payment_id . '-card-number" /></p>',
            'card-expiry-field' => '<p class="form-row form-row-first"><label for="' . $payment_id . '-card-expiry">' . esc_html__('Expiry (MM/YY)', 'qnbpay-for-woocommerce') . ' <span class="required">*</span></label>'
                . '<input id="' . $payment_id . '-card-expiry" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="' . esc_attr__('MM / YY', 'qnbpay-for-woocommerce') . '" name="' . $payment_id . '-card-expiry" /></p>',
            'card-cvc-field' => '<p class="form-row form-row-last"><label for="' . $payment_id . '-card-cvc">' . esc_html__('Card Code', 'qnbpay-for-woocommerce') . ' <span class="required">*</span></label>'
                . '<input id="' . $payment_id . '-card-cvc" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="4" placeholder="' . esc_attr__('CVC', 'qnbpay-for-woocommerce') . '" name="' . $payment_id . '-card-cvc" /></p>',
        ];

        return $cc_fields;
    }

    /**
     * Output payment fields (classic checkout).
     *
     * @return void
     */
    public function payment_fields()
    {
        if ('yes' !== $this->enabled) {
            return;
        }
        try {
            do_action('woocommerce_credit_card_form_start', $this->id); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        $description = $this->description;
        if ($this->testmode) {
            $description .= '<br>' . __('TEST MODE ENABLED. Use the test card numbers from the QNBPay documentation.', 'qnbpay-for-woocommerce');
        }
        if (!empty($description)) {
            echo wpautop(wp_kses_post($description)); // phpcs:ignore WordPress.Security.EscapeOutput
        }

        $cc_form = new \WC_Payment_Gateway_CC();
        $cc_form->id = $this->id;
        $cc_form->supports = $this->supports;
        $cc_form->form();
        echo '<div id="qnbpay-installment-table"></div>';

            do_action('woocommerce_credit_card_form_end', $this->id); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        } catch (\Throwable $e) {
            Plugin::instance()->logger()->exception('payment_fields', $e);
        }
    }

    /**
     * Enqueue frontend scripts.
     *
     * @return void
     */
    public function payment_scripts()
    {
        if ('yes' !== $this->enabled) {
            return;
        }
        if (!is_checkout() && !is_wc_endpoint_url('order-pay')) {
            return;
        }
        wp_enqueue_style('qnbpay-card', QNBPAY_URL . 'assets/qnbpay.css', [], QNBPAY_VERSION);
        wp_enqueue_script('qnbpay-corejs', QNBPAY_URL . 'assets/qnbpay.js', ['jquery'], QNBPAY_VERSION, true);
        wp_localize_script('qnbpay-corejs', 'qnbpay_ajax', $this->script_data());
    }

    /**
     * Validate submitted card fields.
     *
     * @return bool
     */
    public function validate_fields()
    {
        try {
            return $this->do_validate_fields();
        } catch (\Throwable $e) {
            Plugin::instance()->logger()->exception('validate_fields', $e);
            // Fail closed: block checkout rather than submit unvalidated data.
            wc_add_notice(__('Could not validate the payment details. Please try again.', 'qnbpay-for-woocommerce'), 'error');

            return false;
        }
    }

    /**
     * Validate submitted card fields (inner implementation).
     *
     * @return bool
     */
    private function do_validate_fields()
    {
        $posted = map_deep($_POST, 'wc_clean'); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $valid = true;

        if (empty(Arr::get($posted, $this->id . '-name-oncard'))) {
            wc_add_notice(__('Card holder is required.', 'qnbpay-for-woocommerce'), 'error');
            $valid = false;
        }
        $number = preg_replace('/\D+/', '', Arr::str(Arr::get($posted, $this->id . '-card-number')));
        if (strlen($number) < 12) {
            wc_add_notice(__('A valid card number is required.', 'qnbpay-for-woocommerce'), 'error');
            $valid = false;
        }
        $expiry = Arr::str(Arr::get($posted, $this->id . '-card-expiry'));
        if (strpos($expiry, '/') === false) {
            wc_add_notice(__('A valid card expiry (MM/YY) is required.', 'qnbpay-for-woocommerce'), 'error');
            $valid = false;
        }
        if (empty(Arr::get($posted, $this->id . '-card-cvc'))) {
            wc_add_notice(__('Card CVC is required.', 'qnbpay-for-woocommerce'), 'error');
            $valid = false;
        }
        if ($this->installment && empty(Arr::get($posted, $this->id . '-installment')) && empty(Arr::get($posted, 'qnbpay-installment'))) {
            wc_add_notice(__('Please select an installment option.', 'qnbpay-for-woocommerce'), 'error');
            $valid = false;
        }

        return $valid;
    }

    // ---------------------------------------------------------------------
    // Payment processing
    // ---------------------------------------------------------------------

    /**
     * Process the payment.
     *
     * Card data is NEVER written to order/post meta. The auto-submit 3D form is
     * stored in a short-lived, single-use transient and rendered by the
     * /qnbpayform/ endpoint, then immediately deleted.
     *
     * @param int $order_id
     * @return array|void
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            wc_add_notice(__('Order not found.', 'qnbpay-for-woocommerce'), 'error');

            return;
        }

        try {
            $posted = map_deep($_POST, 'wc_clean'); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $request = $this->build_request($order, $posted);

            $this->orders()->log($order_id, $request['invoice_id'], 'formatOrder', $request);

            $token = $this->client()->token();
            if (!$token) {
                wc_add_notice(__('Could not start payment process. Please try again.', 'qnbpay-for-woocommerce'), 'error');
                $this->orders()->log($order_id, $request['invoice_id'], 'tokenFailed', []);

                return;
            }

            $method = $this->enable_3d ? 'paySmart3D' : 'paySmart2D';
            $action_url = $this->client()->host() . $method;

            // Build the auto-submit form (contains card data — kept out of the DB).
            $form_id = 'qnb_' . $request['invoice_id'];
            $form = ['<form id="' . esc_attr($form_id) . '" action="' . esc_url($action_url) . '" method="POST" accept-charset="UTF-8">'];
            foreach ($request as $field => $value) {
                if ('items' === $field) {
                    $value = $this->format_items($value);
                } elseif (is_array($value)) {
                    $value = wp_json_encode($value);
                }
                $form[] = '<input type="hidden" name="' . esc_attr($field) . '" value="' . esc_attr((string) $value) . '">';
            }
            $form[] = '</form><script>document.getElementById(' . wp_json_encode($form_id) . ').submit();</script>';
            $form_html = implode('', $form);

            // Store transiently (single-use, short TTL) — NOT in order meta.
            $pay_token = wp_generate_password(32, false);
            set_transient('qnbpay_pay_' . $pay_token, [
                'order_id' => $order_id,
                'form' => $form_html,
            ], 10 * MINUTE_IN_SECONDS);

            // Persist only non-sensitive references on the order.
            $order->update_meta_data(OrderStore::META_INVOICE, $request['invoice_id']);
            $order->update_meta_data(OrderStore::META_ORDER_HASH, $request['hash_key']);
            $order->save();

            do_action('qnbpay_woocommerce_transaction_start', $order_id, $request['invoice_id']);

            return [
                'result' => 'success',
                'redirect' => add_query_arg(
                    ['key' => $order->get_order_key(), 't' => $pay_token],
                    site_url('/qnbpayform/' . $order_id . '/')
                ),
            ];
        } catch (\Throwable $e) {
            Plugin::instance()->logger()->exception('process_payment', $e);
            wc_add_notice(__('An unexpected error occurred while starting the payment.', 'qnbpay-for-woocommerce'), 'error');

            return;
        }
    }

    /**
     * Build the QNBPay payment request payload.
     *
     * @param \WC_Order $order
     * @param array     $posted
     * @return array
     */
    private function build_request(\WC_Order $order, array $posted)
    {
        $order_id = $order->get_id();

        $installment = (int) Arr::get($posted, $this->id . '-installment', Arr::get($posted, 'qnbpay-installment', 1));
        if ($installment < 1) {
            $installment = 1;
        }
        $card_number = preg_replace('/\D+/', '', Arr::str(Arr::get($posted, $this->id . '-card-number')));
        $card_cvc = preg_replace('/\D+/', '', Arr::str(Arr::get($posted, $this->id . '-card-cvc')));
        $card_holder = Arr::str(Arr::get($posted, $this->id . '-name-oncard'));

        $expiry_raw = Arr::str(Arr::get($posted, $this->id . '-card-expiry'));
        $expiry_parts = array_map('trim', explode('/', $expiry_raw));
        $expiry_month = isset($expiry_parts[0]) ? preg_replace('/\D+/', '', $expiry_parts[0]) : '';
        $expiry_year = isset($expiry_parts[1]) ? preg_replace('/\D+/', '', $expiry_parts[1]) : '';
        if (strlen($expiry_year) === 2) {
            $expiry_year = '20' . $expiry_year;
        }

        $total = $order->get_total();
        $currency = $order->get_currency();

        // Clamp installment to what the card / rules permit.
        $installment = $this->installments()->clamp($installment, $order, $card_number);

        $invoice_id = $this->orders()->create_invoice_id($order, $this->order_prefix);

        $items = [];
        foreach ($order->get_items(['line_item', 'shipping']) as $item) {
            $data = $item->get_data();
            $quantity = isset($data['quantity']) && $data['quantity'] > 0 ? (int) $data['quantity'] : 1;
            $line_total = (float) $order->get_line_total($item, true);
            $items[] = [
                'name' => Arr::str(Arr::get($data, 'name')),
                'price' => Util::number_format($line_total / $quantity),
                'quantity' => $quantity,
                'description' => Arr::str(Arr::get($data, 'name')),
            ];
        }

        $return_url = add_query_arg(['key' => $order->get_order_key()], site_url('/qnbpayresult/' . $order_id . '/'));

        // Payment hash order per docs: total|installment|currency|merchant_key|invoice_id
        $hash_key = $this->client()->hash([$total, $installment, $currency, $this->merchant_key, $invoice_id]);

        $params = [
            'cc_holder_name' => $card_holder,
            'cc_no' => $card_number,
            'expiry_month' => $expiry_month,
            'expiry_year' => $expiry_year,
            'cvv' => $card_cvc,
            'currency_code' => $currency,
            'installments_number' => $installment,
            'invoice_id' => $invoice_id,
            'invoice_description' => sprintf(
                /* translators: 1: order id, 2: invoice id */
                __('Order Payment OrderId:%1$s - InvoiceId:%2$s', 'qnbpay-for-woocommerce'),
                $order_id,
                $invoice_id
            ),
            'total' => $total,
            'merchant_key' => $this->merchant_key,
            'items' => $items,
            'name' => $order->get_billing_first_name(),
            'surname' => $order->get_billing_last_name(),
            'cancel_url' => $return_url,
            'return_url' => $return_url,
            'hash_key' => $hash_key,
            'transaction_type' => 'Auth',
            'is_comission_from_user' => 0,
            'response_method' => 'POST',
            'bill_email' => $order->get_billing_email(),
            'bill_phone' => $order->get_billing_phone(),
            'ip' => Util::client_ip(),
        ];

        if (!empty($this->sale_web_hook_key)) {
            $params['sale_web_hook_key'] = $this->sale_web_hook_key;
        }
        if ($this->enable_3d) {
            $params['is_3d'] = 'yes';
        }

        return $params;
    }

    /**
     * JSON-encode order items for the API.
     *
     * @param array $items
     * @return string
     */
    private function format_items($items)
    {
        return wp_json_encode($items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Process a refund via the QNBPay refund API.
     *
     * @param int        $order_id
     * @param float|null $amount
     * @param string     $reason
     * @return bool|\WP_Error
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return new \WP_Error('qnbpay_refund_error', __('Order not found.', 'qnbpay-for-woocommerce'));
        }

        $invoice_id = $this->orders()->get_invoice_id($order);
        if (empty($invoice_id)) {
            return new \WP_Error('qnbpay_refund_error', __('QNBPay invoice id is missing for this order.', 'qnbpay-for-woocommerce'));
        }

        try {
            $result = $this->client()->refund($invoice_id, $amount);
            $this->orders()->log($order_id, $invoice_id, 'refund', ['amount' => $amount], $result);

            if (!$result['status']) {
                return new \WP_Error(
                    'qnbpay_refund_error',
                    $result['message'] ? $result['message'] : __('Refund could not be completed by QNBPay.', 'qnbpay-for-woocommerce')
                );
            }

            $order->add_order_note(sprintf(
                /* translators: %s: refund amount */
                __('QNBPay refund completed. Amount: %s', 'qnbpay-for-woocommerce'),
                is_null($amount) ? __('full', 'qnbpay-for-woocommerce') : wc_price($amount)
            ));

            return true;
        } catch (\Throwable $e) {
            Plugin::instance()->logger()->exception('process_refund', $e);

            return new \WP_Error('qnbpay_refund_error', __('An unexpected error occurred during refund.', 'qnbpay-for-woocommerce'));
        }
    }

    // ---------------------------------------------------------------------
    // Notices / cart
    // ---------------------------------------------------------------------

    /**
     * Show pay-page notices (errors / recheck).
     *
     * @return void
     */
    public function order_pay_notices()
    {
        if ('yes' !== $this->enabled || !is_wc_endpoint_url('order-pay')) {
            return;
        }

        try {
            $order_id = absint(get_query_var('order-pay'));
        if (!$order_id) {
            return;
        }
        $order = wc_get_order($order_id);
        if (!$order || 'pending' !== $order->get_status()) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $show_error = isset($_GET['qnbpayerror']) && '1' === sanitize_text_field(wp_unslash($_GET['qnbpayerror']));
        $show_recheck = isset($_GET['qnbpayrecheck']) && '1' === sanitize_text_field(wp_unslash($_GET['qnbpayrecheck']));
        // phpcs:enable

        if ($show_error) {
            $message = $this->orders()->get_meta($order, OrderStore::META_ERROR);
            if ($message) {
                wc_print_notice(esc_html($message), 'error');
                $this->orders()->delete_meta($order, OrderStore::META_ERROR);
                do_action('qnbpay_woocommerce_transaction_error', $order_id, $message, $this->orders()->get_meta($order, OrderStore::META_INVOICE));
            }
        }

        if ($show_recheck) {
            $message = $this->orders()->get_meta($order, OrderStore::META_RECHECK);
            if ($message) {
                wc_print_notice(esc_html($message), 'notice', ['qnbpayrecheck' => 1, 'orderid' => $order_id]);
                $this->orders()->delete_meta($order, OrderStore::META_RECHECK);
            }
            }
        } catch (\Throwable $e) {
            Plugin::instance()->logger()->exception('order_pay_notices', $e);
        }
    }

    /**
     * Empty the cart on the thank-you page.
     *
     * @param int $order_id
     * @return void
     */
    public function clear_cart_after_order($order_id)
    {
        do_action('qnbpay_woocommerce_transaction_success', $order_id);
        if (function_exists('WC') && WC()->cart) {
            WC()->cart->empty_cart();
        }
    }

    // ---------------------------------------------------------------------
    // Webhook
    // ---------------------------------------------------------------------

    /**
     * Handle QNBPay sale/refund webhook notifications.
     *
     * @return void
     */
    public function handle_webhook()
    {
        $logger = Plugin::instance()->logger();

        try {
            $orders = $this->orders();

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $get_key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        // phpcs:enable
        if (!$get_key || !hash_equals($this->get_webhook_hash(), $get_key)) {
            status_header(403);
            exit('Invalid key');
        }

        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        if (JSON_ERROR_NONE === json_last_error() && is_array($decoded)) {
            $payload = map_deep($decoded, 'wc_clean');
        } else {
            $payload = map_deep(wp_unslash($_POST), 'wc_clean'); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }

        $logger->debug('webhook', ['payload' => $payload]);

        $received_hash = Arr::str(Arr::get($payload, 'hash_key'));
        if ('' === $received_hash) {
            status_header(400);
            exit('Missing hash_key');
        }

        $verified = $this->client()->verify_hash($received_hash);
        if (!$verified) {
            status_header(403);
            exit('Invalid hash_key');
        }

        $invoice_id = Arr::str(Arr::get($payload, 'invoice_id', Arr::get($verified, 'invoiceId')));
        if ('' === $invoice_id) {
            status_header(400);
            exit('Missing invoice_id');
        }

        $order_id = $orders->resolve_order_id($invoice_id);
        if (!$order_id) {
            status_header(200);
            exit('Order not found');
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            status_header(200);
            exit('Order not found');
        }

        if ($order->has_status(wc_get_is_paid_statuses())) {
            status_header(200);
            exit('Already processed');
        }

        // Independently confirm via checkstatus.
        $this->finalize_from_checkstatus($order, $invoice_id, 'webhook');

            status_header(200);
            exit('OK');
        } catch (\Throwable $e) {
            $logger->exception('webhook', $e);
            status_header(500);
            exit('Error');
        }
    }

    /**
     * Confirm and finalize an order using the checkstatus API.
     *
     * Shared by the result endpoint, the recheck AJAX and the webhook.
     *
     * @param \WC_Order $order
     * @param string    $invoice_id
     * @param string    $context
     * @return bool True when the order is marked paid.
     */
    public function finalize_from_checkstatus(\WC_Order $order, $invoice_id, $context = '', $mark_failed = true)
    {
        try {
            $orders = $this->orders();
            $status = $this->client()->check_status($invoice_id);
        $orders->log($order->get_id(), $invoice_id, 'checkstatus:' . $context, $status);

        if (!$status['status']) {
            return false;
        }

        $status_code = (int) Arr::get($status['body'], 'status_code', 0);
        $md_status = (int) Arr::get($status['body'], 'md_status', 1);

        if (100 === $status_code && $md_status === 1) {
            $order->payment_complete(Arr::str(Arr::get($status['body'], 'transaction_id')));
            $order->update_meta_data(OrderStore::META_QNB_ORDER_ID, Arr::str(Arr::get($status['body'], 'order_id')));
            $order->update_meta_data(OrderStore::META_TRANSACTION_ID, Arr::str(Arr::get($status['body'], 'transaction_id')));
            $order->save();

            $target_status = $this->order_status ? $this->order_status : 'wc-completed';
            $normalized = ('wc-' === substr($target_status, 0, 3)) ? substr($target_status, 3) : $target_status;
            // payment_complete() may set 'processing'; force the configured status if it differs.
            if ($normalized && $order->get_status() !== $normalized) {
                $order->update_status($normalized, __('Payment completed via QNBPay.', 'qnbpay-for-woocommerce'));
            } else {
                $order->add_order_note(__('Payment completed via QNBPay.', 'qnbpay-for-woocommerce'));
            }

            return true;
        }

        // Not confirmed. Only mark failed when the caller allows it; the cron
        // reconciler passes false so a payment still pending at the bank is not
        // failed prematurely (it will be retried on the next run).
        if ($mark_failed) {
            $reason = Arr::str(Arr::get($status['body'], 'status_description', __('Payment has not been confirmed.', 'qnbpay-for-woocommerce')));
            if ('failed' !== $order->get_status()) {
                $order->update_status('failed', $reason);
            }
        }

            return false;
        } catch (\Throwable $e) {
            Plugin::instance()->logger()->exception('finalize:' . $context, $e);

            return false;
        }
    }
}
