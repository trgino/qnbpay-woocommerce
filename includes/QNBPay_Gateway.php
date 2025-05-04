<?php

/**
 * QNBPay Payment Gateway Class for WooCommerce.
 *
 * Handles payment processing, settings, and interactions with the QNBPay API.
 *
 * @since   1.0.0
 */
class QNBPay_Gateway extends WC_Payment_Gateway
{
    /**
     * Whether the gateway is in test mode.
     *
     * @var bool
     */
    public $testmode;

    /**
     * Whether installments are enabled.
     *
     * @var bool
     */
    public $installment;

    /**
     * Whether 3D Secure is enabled.
     *
     * @var bool
     */
    public $enable_3d;

    /** @var string Merchant Key from QNBPay. */
    public $merchant_key;

    /** @var string Merchant ID from QNBPay. */
    public $merchant_id;

    /** @var string App Key from QNBPay. */
    public $app_key;

    /** @var string App Secret from QNBPay. */
    public $app_secret;

    /** @var string Prefix for order IDs sent to QNBPay. */
    public $order_prefix;

    /** @var string WooCommerce order status after successful payment. */
    public $order_status;

    /** @var int Maximum number of installments allowed globally. */
    public $limitInstallment;

    /** @var bool Whether to limit installments based on product settings. */
    public $limitInstallmentByProduct;

    /** @var bool Whether to limit installments based on cart amount. */
    public $limitInstallmentByCart;

    /** @var float Minimum cart amount required for installments. */
    public $limitInstallmentByCartAmount;

    /** @var bool Whether debug mode is enabled. */
    public $debugMode;

    /** @var array Range of possible installment numbers. */
    public $maxInstallment;

    /** @var array Information about the current logged-in user. */
    public $userInformation;

    /** @var string Webhook Key for sale notifications from QNBPay. */
    public $sale_web_hook_key;

    /**
     * Constructor for the gateway.
     *
     * @return void
     */
    public function __construct()
    {

        $this->id = 'qnbpay';
        $this->icon = '';
        $this->has_fields = true;
        $this->method_title = 'QNBPay';
        $this->method_description = __('QNBPay WooCommerce Gateway', 'qnbpay-woocommerce');
        $this->supports = [
            'products',
        ];

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = $this->get_option('testmode', 'no') === 'yes';
        $this->installment = $this->get_option('installment', 'yes') === 'yes';
        $this->enable_3d = $this->get_option('enable_3d', 'yes') === 'yes';
        $this->merchant_key = $this->get_option('merchant_key');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->app_key = $this->get_option('app_key');
        $this->app_secret = $this->get_option('app_secret');
        $this->order_prefix = $this->get_option('order_prefix');
        $this->order_status = $this->get_option('order_status');
        $this->limitInstallment = $this->get_option('limitInstallment', 12);
        $this->limitInstallmentByProduct = $this->get_option('limitInstallmentByProduct', 'no') === 'yes';
        $this->limitInstallmentByCart = $this->get_option('limitInstallmentByCart', 'no') === 'yes';
        $this->limitInstallmentByCartAmount = $this->get_option('limitInstallmentByCartAmount', 0);
        $this->debugMode = $this->get_option('debugMode', 'no') === 'yes';
        $this->maxInstallment = range(1, 12);
        // Initialize the new setting
        $this->sale_web_hook_key = $this->get_option('sale_web_hook_key');
        $this->userInformation = self::getUserInformationData();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
        add_filter('woocommerce_credit_card_form_fields', [$this, 'payment_form_fields'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'admin_css']);

        add_filter('woocommerce_generate_qnbhr_html', [$this, 'qnbhr_html']);

        add_action('woocommerce_api_wc_gateway_' . $this->id, [$this, 'handle_webhook']);

        add_action('woocommerce_thankyou', [$this, 'clear_cart_after_order']);
        add_action('before_woocommerce_pay', [$this, 'order_pay_notices']);

        add_action('woocommerce_credit_card_form_start', [$this, 'test_mode_notice']);
        add_action('woocommerce_credit_card_form_end', [$this, 'installment_table_holder']);
    }

    /**
     * Get or generate a unique hash for webhook URLs.
     *
     * @since 1.0.0
     * @return string The webhook hash.
     */
    public function get_webhook_hash()
    {
        $webhook_hash = get_option($this->id . '_webhook_hash', false);
        if ($webhook_hash) {
            return $webhook_hash;
        }
        $webhook_hash = wp_generate_password(12, false);
        update_option($this->id . '_webhook_hash', $webhook_hash);

        return $webhook_hash;
    }

    /**
     * Display notices on the order pay page (e.g., payment errors).
     *
     * @return void
     */
    public function order_pay_notices()
    {
        if (is_wc_endpoint_url('order-pay')) {
            $order_id = intval(get_query_var('order-pay', 0));
            if ($order_id > 0) {
                $order = wc_get_order($order_id);
                if (!$order) {
                    return;
                }
                // Check for payment error notice flag in URL
                $qnbpayerror = $_GET['qnbpayerror'] ?? 0;
                $qnbpayerror = $qnbpayerror == 1 ? get_post_meta($order_id, 'qnbpayerror', true) : false;

                // Check for payment recheck notice flag in URL
                $qnbpayrecheck = $_GET['qnbpayrecheck'] ?? 0;
                $qnbpayrecheck = $qnbpayrecheck == 1 ? get_post_meta($order_id, 'qnbpayrecheck', true) : false;

                // Display notices only if the order is pending
                if ($order && $order->get_status() === 'pending') {
                    if ($qnbpayerror) {
                        wc_print_notice($qnbpayerror, 'error');
                        delete_post_meta($order_id, 'qnbpayerror');
                    }
                    if ($qnbpayrecheck) {
                        wc_print_notice($qnbpayerror, 'notice', [
                            'qnbpayrecheck' => 1,
                            'orderid' => $order_id,
                        ]);
                        delete_post_meta($order_id, 'qnbpayrecheck');
                    }

                    return;
                }
            }

        }
    }

    /**
     * Clear the cart after a successful order placement on the thank you page.
     *
     * @return void
     */
    public function clear_cart_after_order()
    {
        if (WC()->cart) {
            WC()->cart->empty_cart();
        }
    }

    /**
     * Generates HTML for a horizontal rule in the admin settings table.
     *
     * @param  string  $key  Field key.
     * @param  array  $data  Field data.
     * @return string HTML for the horizontal rule.
     */
    public function qnbhr_html()
    {
        return '<tr valign="top"><td colspan="2"><hr /></td></tr>';
    }

    /**
     * Initialise Gateway Settings Form Fields.
     * Defines the settings fields shown in the WooCommerce admin area.
     *
     * @return void
     */
    public function init_form_fields()
    {

        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'qnbpay-woocommerce'),
                'label' => __('Enable QNBPay Gateway?', 'qnbpay-woocommerce'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'yes',
            ],
            'title' => [
                'title' => __('Title', 'qnbpay-woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'qnbpay-woocommerce'),
                'default' => __('Credit Card', 'qnbpay-woocommerce'),
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Description', 'qnbpay-woocommerce'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'qnbpay-woocommerce'),
                'default' => __('Pay with your credit card via our super-cool payment gateway.', 'qnbpay-woocommerce'),
            ],
            'qnbhr1' => [
                'type' => 'qnbhr',
            ],
            'merchant_key' => [
                'title' => __('Merchant Key', 'qnbpay-woocommerce'),
                'type' => 'text',
            ],
            'merchant_id' => [
                'title' => __('Merchant ID', 'qnbpay-woocommerce'),
                'type' => 'text',
            ],
            'app_key' => [
                'title' => __('App Key', 'qnbpay-woocommerce'),
                'type' => 'text',
            ],
            'app_secret' => [
                'title' => __('App Secret', 'qnbpay-woocommerce'),
                'type' => 'text',
            ],
            'qnbhr2' => [
                'type' => 'qnbhr',
            ],
            'sale_web_hook_key' => [
                'title' => __('Sale Webhook Key', 'qnbpay-woocommerce'),
                'type' => 'text',
                'description' => __('Enter the key you defined in the QNBPay merchant panel for webhook notifications. This is required to receive webhook updates. The webhook URL to enter in the panel is: ', 'qnbpay-woocommerce') . '<code>' . add_query_arg(['key' => $this->get_webhook_hash()], home_url('/wc-api/WC_Gateway_' . $this->id . '/')) . '</code>',
            ],
            'qnbhr_webhook' => [ // Renamed for clarity
                'type' => 'qnbhr',
            ],
            'testmode' => [
                'title' => 'Test ' . __('Enable/Disable', 'qnbpay-woocommerce'),
                'label' => __('Enable Test Mode?', 'qnbpay-woocommerce'),
                'type' => 'checkbox',
                'description' => __('Place the payment gateway in test mode using test API keys.', 'qnbpay-woocommerce'),
                'default' => 'yes',
                'desc_tip' => true,
            ],
            'qnbhr3' => [
                'type' => 'qnbhr',
            ],
            'enable_3d' => [
                'title' => __('Enable 3D', 'qnbpay-woocommerce'),
                'label' => __('Enable 3d Payment?', 'qnbpay-woocommerce'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'yes',
            ],
            'qnbhr4' => [
                'type' => 'qnbhr',
            ],
            'installment' => [
                'title' => __('Installement', 'qnbpay-woocommerce'),
                'label' => __('Enable/Disable Installement ?', 'qnbpay-woocommerce'),
                'type' => 'checkbox',
                'default' => 'yes',
            ],
            'limitInstallment' => [
                'title' => __('Limit Installement', 'qnbpay-woocommerce'),
                'label' => __('Limit Installement', 'qnbpay-woocommerce'),
                'type' => 'select',
                'options' => [
                    1 => sprintf(__('%s Installement', 'qnbpay-woocommerce'), 1),
                    2 => sprintf(__('%s Installement', 'qnbpay-woocommerce'), 2),
                    3 => sprintf(__('%s Installement', 'qnbpay-woocommerce'), 3),
                    4 => sprintf(__('%s Installement', 'qnbpay-woocommerce'), 4),
                    5 => sprintf(__('%s Installement', 'qnbpay-woocommerce'), 5),
                    6 => sprintf(__('%s Installement', 'qnbpay-woocommerce'), 6),
                    7 => sprintf(__('%s Installement', 'qnbpay-woocommerce'), 7),
                    8 => sprintf(__('%s Installement', 'qnbpay-woocommerce'), 8),
                    9 => sprintf(__('%s Installement', 'qnbpay-woocommerce'), 9),
                    10 => sprintf(__('%s Installement', 'qnbpay-woocommerce'), 10),
                    11 => sprintf(__('%s Installement', 'qnbpay-woocommerce'), 11),
                    12 => sprintf(__('%s Installement', 'qnbpay-woocommerce'), 12),
                ],
                'description' => '',
                'default' => '12',
            ],
            'limitInstallmentByProduct' => [
                'title' => __('Limit Installement By Product', 'qnbpay-woocommerce'),
                'label' => __('Enable/Disable Installement by Product ?', 'qnbpay-woocommerce'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no',
            ],
            'limitInstallmentByCart' => [
                'title' => __('Limit Installement By Cart Amount', 'qnbpay-woocommerce'),
                'label' => __('Enable/Disable Installement by Cart Amount ?', 'qnbpay-woocommerce'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no',
            ],
            'limitInstallmentByCartAmount' => [
                'title' => __('Installement Minumum Cart Amount', 'qnbpay-woocommerce'),
                'label' => __('Those less than this basket amount will be collected in advance.', 'qnbpay-woocommerce'),
                'type' => 'number',
            ],
            'qnbhr5' => [
                'type' => 'qnbhr',
            ],
            'order_prefix' => [
                'title' => __('Order Prefix', 'qnbpay-woocommerce'),
                'type' => 'text',
                'description' => __('This field provides convenience for the separation of orders during reporting for the QNBPay module used in more than one site. (Optional)', 'qnbpay-woocommerce'),
                'default' => self::generateDefaultOrderPrefix(),
            ],
            'order_status' => [
                'title' => __('Order Status', 'qnbpay-woocommerce'),
                'type' => 'select',
                'description' => __('You can choose what the status of the order will be when your payments are successfully completed.', 'qnbpay-woocommerce'),
                'options' => self::getOrderStatuses(),
                'default' => 'wc-completed',
            ],
            'qnbhr6' => [
                'type' => 'qnbhr',
            ],
            'debugMode' => [
                'title' => __('Enable/Disable Debug Mode', 'qnbpay-woocommerce'),
                'label' => __('Enable/Disable Debug Mode', 'qnbpay-woocommerce'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no',
            ],
        ];
    }

    /**
     * Process and validate admin options.
     * Adds custom validation for order_prefix and sale_web_hook_key.
     *
     * @since 1.0.0
     * @return bool Whether the options were saved successfully.
     */
    public function process_admin_options()
    {
        $post_data = $this->get_post_data();

        if (!isset($post_data['woocommerce_qnbpay_order_prefix']) || empty($post_data['woocommerce_qnbpay_order_prefix'])) {
            WC_Admin_Settings::add_error(__('Order prefix is required.', 'qnbpay-woocommerce'));
            return false;
        }

        // Validate order prefix format (uppercase letters and numbers only)
        if (!preg_match('/^[A-Z0-9]+$/', $post_data['woocommerce_qnbpay_order_prefix'])) {
            WC_Admin_Settings::add_error(__('Order prefix must contain only uppercase letters and numbers.', 'qnbpay-woocommerce'));
            return false;
        }

        // Validate sale webhook key format (letters and numbers only) if provided
        if (isset($post_data['woocommerce_qnbpay_sale_web_hook_key']) && !empty($post_data['woocommerce_qnbpay_sale_web_hook_key']) && !preg_match('/^[a-zA-Z0-9]+$/', $post_data['woocommerce_qnbpay_sale_web_hook_key'])) {
            WC_Admin_Settings::add_error(__('Sale webhook key must contain only letters and numbers.', 'qnbpay-woocommerce'));
            return false;
        }

        return parent::process_admin_options();
    }

    /**
     * Enqueue admin-specific CSS and JavaScript.
     *
     * @return void
     */
    public function admin_css()
    {
        global $pagenow;

        if ($pagenow == 'admin.php' && isset($_GET['tab']) && isset($_GET['section']) && $_GET['section'] == 'qnbpay') {
            wp_register_style('qnbpay-admin', QNBPAY_URL . 'assets/qnbpay-admin.css', false, QNBPAY_VERSION);
            wp_enqueue_style('qnbpay-admin');
        }

        wp_enqueue_script('qnbpay-corejs', QNBPAY_URL . 'assets/qnbpay-admin.js', false, QNBPAY_VERSION);
        wp_localize_script('qnbpay-corejs', 'qnbpay_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'success_redirection' => __('Your transaction has been completed successfully. Within 2 seconds the page will be refreshed', 'qnbpay-woocommerce'),
            'update_comission' => __('When you do this, all of the instalment data you have entered is deleted and the current ones from QNBPay servers are overwritten. The process cannot be reversed. To continue, please enter confirmation in the field below and continue the process. Otherwise, your transaction will not continue.', 'qnbpay-woocommerce'),
            'version' => QNBPAY_VERSION,
            'installment_test' => __('Installment Rate Test', 'qnbpay-woocommerce'),
            'bin_test' => __('Bank Identification Test', 'qnbpay-woocommerce'),
            'remote_test' => __('Remote Connection Test', 'qnbpay-woocommerce'),
            'success' => __('Success', 'qnbpay-woocommerce'),
            'failed' => __('Failed', 'qnbpay-woocommerce'),
            'download_debug' => __('Download debug file', 'qnbpay-woocommerce'),
            'clear_debug' => __('Clear debug file', 'qnbpay-woocommerce'),
            'debug_notfound' => __('Cant find debug file', 'qnbpay-woocommerce'),
        ]);
    }

    /**
     * Output the admin options table.
     *
     * @return void
     */
    public function admin_options()
    {
        $return_url = admin_url('admin.php?page=wc-settings&tab=checkout');

        ?>
<div class="qnbpay-admin-interface">
<div class="left">
    <img src="<?php echo QNBPAY_URL . 'assets/img/qnbpay.png'; ?>" />
    <h2><?php _e('QNBPay Settings', 'qnbpay-woocommerce');
        wc_back_link(__('Return to payments', 'woocommerce'), $return_url)
        ?></h2>

    <table class="form-table">
        <?php $this->generate_settings_html(); ?>
    </table>
    <div class="qnbpay-admin-test-details">
        <button type="button" class="qnbpay-admin-dotest">
            <?php _e('Test Informations', 'qnbpay-woocommerce'); ?>
        </button>
    </div>
    <div class="qnbpay-admin-test-results"></div>
</div>
<div class="right">
    <div class="qnbpay">
        <h3><?php _e('QNBPay', 'qnbpay-woocommerce'); ?></h3>
        <p><?php _e('QNBPay Payment Gateway for WooCommerce', 'qnbpay-woocommerce'); ?></p>
        <p><a href="https://qnbpay.com.tr/" target="_blank"><?php _e('Visit Website', 'qnbpay-woocommerce'); ?></a></p>
    </div>
</div>
</div>
<?php
}

    /**
     * Custom credit card form fields.
     *
     * @param  array  $cc_fields  Default credit card fields.
     * @param  string  $payment_id  Payment gateway ID.
     * @return array Modified credit card fields.
     */
    public function payment_form_fields($cc_fields, $payment_id)
    {

        $referer = is_wc_endpoint_url('order-pay') ? 'order-pay' : 'checkout';
        $state = 'cart';

        if (get_query_var('order-pay')) {
            $state = 'order';
        }

        $cc_fields = [
            'current-step-of-payment' => '
                <p class="form-row form-row-wide">
                    <input
                        id="' . $payment_id . '-current-step-of-payment"
                        class="current-step-of-payment"
                        type="hidden"
                        value="' . $referer . '"
                        name="' . $payment_id . '-current-step-of-payment"
                    />
                    <input
                        id="' . $payment_id . '-current-order-state"
                        class="current-order-state"
                        type="hidden"
                        value="' . $state . '"
                        name="' . $payment_id . '-current-order-state"
                    />
                </p>',
            'name-on-card' => '
                <p class="form-row form-row-wide">
                    <label for="' . $payment_id . '-card-holder">' . __('Name On Card', 'qnbpay-woocommerce') . ' <span class="required">*</span></label>

                    <input
                        id="' . $payment_id . '-card-holder"
                        class="input-text wc-credit-card-form-card-holder"
                        type="text"
                        autocomplete="off"
                        placeholder="' . __('Name On Card', 'qnbpay-woocommerce') . '"
                        name="' . $payment_id . '-name-oncard"
                    />
                </p>',
            'card-number-field' => '
                <p class="form-row form-row-wide">
                    <label for="' . $payment_id . '-card-number">' . __('Card Number', 'qnbpay-woocommerce') . ' <span class="required">*</span></label>

                    <input
                        id="' . $payment_id . '-card-number"
                        class="input-text wc-credit-card-form-card-number"
                        autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no"
                        inputmode="numeric"
                        type="tel"
                        maxlength="20"
                        placeholder="•••• •••• •••• ••••"
                        name="' . $payment_id . '-card-number"
                    />
                </p>',
            'card-expiry-field' => '
                <p class="form-row form-row-first">
                    <label for="' . $payment_id . '-card-expiry">' . __('Expiry (MM/YY)', 'woocommerce') . ' <span class="required">*</span></label>

                    <input
                        id="' . $payment_id . '-card-expiry"
                        class="input-text wc-credit-card-form-card-expiry"
                        inputmode="numeric"
                        autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no"
                        type="tel"
                        placeholder="' . __('MM / YYYYY', 'woocommerce') . '"
                        name="' . $payment_id . '-card-expiry"
                    />
            </p>',
            'card-cvc-field' => '
                <p class="form-row form-row-last">
                    <label for="' . $payment_id . '-card-cvc">' . __('Card Code', 'qnbpay-woocommerce') . ' <span class="required">*</span></label>

                    <input
                        id="' . $payment_id . '-card-cvc"
                        class="input-text wc-credit-card-form-card-cvc"
                        inputmode="numeric"
                        autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no"
                        type="tel"
                        maxlength="4"
                        placeholder="' . __('CVC', 'woocommerce') . '"
                        name="' . $payment_id . '-card-cvc"
                    />
            </p>',
        ];

        return $cc_fields;
    }

    /**
     * Display a notice on the checkout form if test mode is enabled.
     * Hooks into woocommerce_credit_card_form_start.
     *
     * @since 1.0.0
     * @param string $gatewayid The ID of the current payment gateway.
     */
    public function test_mode_notice($gatewayid)
    {
        if ($gatewayid == $this->id) {
            if ($this->description) {
                if ($this->testmode) {
                    $this->description .= __('TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="https://qnbpay.com.tr/">documentation</a>', 'qnbpay-woocommerce');
                    $this->description = trim($this->description);
                }
                echo wpautop(wp_kses_post($this->description));
            }
        }
    }

    /**
     * Output a placeholder div for the installment table on the checkout form.
     * Hooks into woocommerce_credit_card_form_end.
     *
     * @since 1.0.0
     * @param string $gatewayid The ID of the current payment gateway.
     */
    public function installment_table_holder($gatewayid)
    {
        if ($gatewayid == $this->id) {
            echo '<div id="qnbpay-installment-table"></div>';
        }
    }

    /**
     * Output the payment fields on the checkout page.
     *
     * @return void
     */
    public function payment_fields()
    {
        do_action('woocommerce_credit_card_form_start', $this->id);

        $cc_form = new WC_Payment_Gateway_CC();
        $cc_form->id = $this->id;
        $cc_form->supports = $this->supports;
        $cc_form->form();

        do_action('woocommerce_credit_card_form_end', $this->id);
    }

    /**
     * Enqueue scripts for the payment form on the frontend.
     *
     * @return void
     */
    public function payment_scripts()
    {
        wp_enqueue_script('qnbpay-corejs', QNBPAY_URL . 'assets/qnbpay.js', ['jquery'], QNBPAY_VERSION, true);
        wp_localize_script('qnbpay-corejs', 'qnbpay_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'version' => QNBPAY_VERSION,
            'nonce' => wp_create_nonce('qnbpay_ajax_nonce'),
            'installment_test' => __('Installment Rate Test', 'qnbpay-woocommerce'),
            'bin_test' => __('Bank Identification Test', 'qnbpay-woocommerce'),
            'remote_test' => __('Remote Connection Test', 'qnbpay-woocommerce'),
            'success' => __('Success', 'qnbpay-woocommerce'),
            'failed' => __('Failed', 'qnbpay-woocommerce'),
            'download_debug' => __('Download debug file', 'qnbpay-woocommerce'),
            'clear_debug' => __('Clear debug file', 'qnbpay-woocommerce'),
            'debug_notfound' => __('Cant find debug file', 'qnbpay-woocommerce'),
        ]);
    }

    /**
     * Validate frontend fields.
     *
     * @return bool True if the fields are valid, false otherwise.
     */
    public function validate_fields()
    {

        $postedData = map_deep($_POST, 'wc_clean');
        $is_valid = true;

        // Basic validation for required fields
        if (empty(data_get($postedData, $this->id . '-name-oncard'))) {
            wc_add_notice(__('<strong>Card holder</strong> is required.', 'qnbpay-woocommerce'), 'error');
            $is_valid = false;
        }

        if (empty(data_get($postedData, $this->id . '-card-number'))) {
            wc_add_notice(__('<strong>Card Number</strong> is required.', 'qnbpay-woocommerce'), 'error');
            $is_valid = false;
        }

        if (empty(data_get($postedData, $this->id . '-card-expiry'))) {
            wc_add_notice(__('<strong>Card Expiry</strong> is required.', 'qnbpay-woocommerce'), 'error');
            $is_valid = false;
        }
        if (empty(data_get($postedData, $this->id . '-card-cvc'))) {
            wc_add_notice(__('<strong>Card CVC</strong> is required.', 'qnbpay-woocommerce'), 'error');
            $is_valid = false;
        }

        // Validate installment selection if installments are enabled
        if ($this->installment) {
            if (empty(data_get($postedData, $this->id . '-installment'))) {
                wc_add_notice(__('<strong>Installment</strong> is required.', 'qnbpay-woocommerce'), 'error');
                $is_valid = false;
            }
        }

        return $is_valid;
    }

    /**
     * Process the payment and return the result.
     *
     * @param  int  $orderId  Order ID.
     * @return array|void Result of payment processing.
     */
    public function process_payment($orderId)
    {
        global $qnbpaycore;

        $order = wc_get_order($orderId);
        if (!$order) {
            return;
        }
        $postedData = map_deep($_POST, 'wc_clean');

        // Format order data for the API request
        $orderDetails = self::formatOrder($order, $postedData);

        // Log the formatted order details if debug mode is on
        $qnbpaycore->saveOrderLog($orderId, 'formatOrder', $orderDetails, $postedData);

        $token = $qnbpaycore->getToken();

        // Handle token retrieval failure
        if (!$token) {
            wc_add_notice(__('Could not start payment process.', 'qnbpay-woocommerce'), 'error');
            $qnbpaycore->saveOrderLog($orderId, 'tokenFailed', $orderDetails, $token);

            return;
        }

        // Determine the payment method based on 3D Secure setting
        $method = $this->enable_3d ? 'paySmart3D' : 'paySmart2D';

        $qnbpaycore->save_log(__METHOD__ . ' orderDetails', $orderDetails);

        // Prepare the auto-submitting form for redirection to QNBPay
        $formId = 'qnb_' . $orderDetails['invoice_id'];
        $form = [
            // Form tag with target API endpoint
            '<form id="' . $formId . '" action="' . $qnbpaycore->apiHost($this->settings) . $method . '" method="POST">',
        ];
        foreach ($orderDetails as $detailKey => $detailValue) {
            if ($detailKey == 'items') {
                $detailValue = $this->formatOrderItems($detailValue);
            }
            $form[] = sprintf('<input type="hidden" name="%s" value=\'%s\'>', $detailKey, (is_array($detailValue) ? json_encode($detailValue) : $detailValue));
        }
        // Add JavaScript to auto-submit the form
        $form[] = '</form><script>document.getElementById("' . $formId . '").submit()</script>';

        // Store the form HTML and hash key in order meta for later use (e.g., 3D Secure page)
        update_post_meta($orderId, 'qnbpay_order_form', $form);
        update_post_meta($orderId, 'qnbpay_order_hash', $orderDetails['hash_key']);

        // Return success and the redirection URL (to the intermediate form page)
        return [
            'result' => 'success',
            'redirect' => add_query_arg(['key' => $order->get_order_key()], site_url("/qnbpayform/$orderId/")),
        ];
    }

    /**
     * Format order items for QNBPay API.
     *
     * @param  array  $items  Order items.
     * @return string JSON encoded string of items.
     */
    public function formatOrderItems($items)
    {
        return json_encode($items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Format Order Details for QNBPay API
     *
     * @param  WC_Order|int  $order_id  Order object or Order ID.
     * @param  array  $postedData  Data posted from the checkout form.
     * @return array Formatted order data for QNBPay API.
     */
    private function formatOrder($order_id, $postedData)
    {
        global $qnbpaycore;

        if ($order_id instanceof WC_Order) {
            $order = $order_id;
        } else {
            $order = wc_get_order($order_id);
        }

        $orderId = $order->get_id();

        // Extract card details from posted data
        $installment = intval(data_get($postedData, $this->id . '-installment', 1));
        $cardNumber = preg_replace('/\s+/', '', data_get($postedData, $this->id . '-card-number'));
        $cardExpiry = data_get($postedData, $this->id . '-card-expiry');
        $cardCvc = data_get($postedData, $this->id . '-card-cvc');
        $cardHolder = data_get($postedData, $this->id . '-name-oncard');

        $expiryParts = explode('/', $cardExpiry);
        $expiryMonth = trim($expiryParts[0]);
        $expiryYear = trim($expiryParts[1]);

        if (strlen($expiryYear) == 2) {
            $expiryYear = '20' . $expiryYear;
        }

        $orderTotal = $order->get_total();

        // Validate and potentially adjust the selected installment number
        $installment = $this->checkOrderMaxInstallment($installment, [
            'state' => 'order',
            'order' => $order,
            'binNumber' => $cardNumber,
            'ordertotal' => $orderTotal,
            'currency' => $order->get_currency(),
            'method' => 'formatOrder',
        ]);

        // Create a custom order ID structure for QNBPay
        $customOrderData = $qnbpaycore->createCustomOrderId($orderId);

        $invoiceId = $customOrderData['invoiceid'];
        $customorderId = $customOrderData['customorderid'];
        $invoice_description = sprintf(__('Order Payment OrderId:%s - CustomOrderId:%s - InvoiceId:%s', 'qnbpay-woocommerce'), $orderId, $customorderId, $invoiceId);

        // Format order items for the API
        $items = [];
        foreach ($order->get_items() as $item) {
            $itemData = $item->get_data();
            $tax = floatval($itemData['total_tax']);
            if (isset($itemData['taxes'], $itemData['taxes']['total']) && !empty($itemData['taxes']['total']) && is_array($itemData['taxes']['total'])) {
                $tax = array_sum($itemData['taxes']['total']);
            }
            $price = floatval($itemData['total']) + floatval($tax);
            $items[] = [
                'name' => $itemData['name'],
                'price' => $qnbpaycore->qnbpay_number_format($price / $itemData['quantity']),
                'quantity' => $itemData['quantity'],
                'description' => $itemData['name'],
                'data' => $itemData,
            ];
        }

        // Generate the hash key required by the QNBPay API
        $hashKey = $qnbpaycore->generateHashKey([
            'total' => $orderTotal,
            'installment' => $installment,
            'currency_code' => $order->get_currency(),
            'merchant_key' => $this->merchant_key,
            'invoice_id' => $invoiceId,
        ]);

        // Define the return URL for after payment processing
        $returnUrl = add_query_arg(['key' => $order->get_order_key()], site_url("/qnbpayresult/$orderId/"));
        // $cancelUrl = $order->get_checkout_payment_url(true);

        // Prepare the final parameters array for the API request
        $params = [
            'cc_holder_name' => $cardHolder,
            'cc_no' => $cardNumber,
            'expiry_month' => $expiryMonth,
            'expiry_year' => $expiryYear,
            'cvv' => $cardCvc,
            'currency_code' => $order->get_currency(),
            'installments_number' => $installment,
            'invoice_id' => $invoiceId,
            'invoice_description' => $invoice_description,
            'total' => $orderTotal,
            'merchant_key' => $this->merchant_key,
            'items' => $items,
            'name' => $order->get_billing_first_name(),
            'surname' => $order->get_billing_last_name(),
            'cancel_url' => $returnUrl,
            'return_url' => $returnUrl,
            'hash_key' => $hashKey,
            'transaction_type' => 'Auth',
            'is_comission_from_user' => 0,
            'response_method' => 'POST',
            'bill_email' => $order->get_billing_email(),
            'bill_phone' => $order->get_billing_phone(),
            'ip' => $qnbpaycore->getClientIp(),
        ];

        // Add sale_web_hook_key if it's configured in settings
        if ($this->sale_web_hook_key && !empty($this->sale_web_hook_key)) {
            $params['sale_web_hook_key'] = $this->sale_web_hook_key;
        }

        // Add 3D Secure flag if enabled
        if ($this->enable_3d) {
            $params['is_3d'] = 'yes';
        }

        return $params;
    }

    /**
     * Get available WooCommerce order statuses.
     *
     * @return array Array of order statuses.
     */
    private function getOrderStatuses()
    {
        $order_statuses = wc_get_order_statuses();

        return $order_statuses;
    }

    /**
     * Generate a default order prefix.
     *
     * @return string Default order prefix.
     */
    private function generateDefaultOrderPrefix()
    {
        return strtoupper(sanitize_title($this->id));
    }

    /**
     * Get information about the current logged-in user.
     *
     * @return array User data (id, name, email) or empty array if not logged in.
     */
    private function getUserInformationData()
    {
        global $current_user;

        if (!$current_user) {
            return [];
        }

        return [
            'id' => $current_user->ID,
            'name' => $current_user->display_name,
            'email' => $current_user->user_email,
        ];
    }

    /**
     * Check and adjust the selected installment number based on various limits.
     *
     * @param  int  $installment  Selected installment number.
     * @param  array  $data  Context data (state, order, bin, total, currency, method).
     * @return int Adjusted installment number.
     */
    public function checkOrderMaxInstallment($installment, $data)
    {
        global $qnbpayajax;

        // Use the AJAX handler's validate_bin method to get installment info and max allowed
        $check = $qnbpayajax->validate_bin($data);
        if ($check['status']) {
            if ($check['cardInformation']) {
                foreach ($check['cardInformation'] as $card) {
                    if ($card['installments_number'] == $installment) {
                        $installment = $card['installments_number'];
                        break;
                    }
                }
            }
        }

        // Ensure the selected installment doesn't exceed the calculated maximum
        if (isset($check['maxInstallment']) && intval($check['maxInstallment']) < $installment) {
            $installment = intval($check['maxInstallment']);
        }

        return $installment;
    }

    /**
     * Handle Webhook Notifications from QNBPay.
     * Listens on yoursite.com/wc-api/WC_Gateway_QNBPay/
     *
     * @return void
     */
    public function handle_webhook()
    {
        $secret_key = $this->get_webhook_hash();
        // Verify the key passed in the webhook URL
        $get_key = $_GET['key'] ?? false;
        if (!$get_key) {
            status_header(403);
            exit('Missing key');
        }

        if ($get_key != $secret_key) {
            status_header(403);
            exit('Invalid key');
        }

        global $qnbpaycore;
        // Get payload from POST or raw input (JSON)
        $payload = $_POST;

        $raw_payload = file_get_contents('php://input');
        $qnbpaycore->save_log(__METHOD__, ['post_data' => $payload, 'raw_input' => $raw_payload]);

        // Attempt to parse raw input as JSON first
        $raw_json = json_decode($raw_payload, true);
        if (!json_last_error() && $raw_json) {
            $payload = map_deep($raw_json, 'wp_unslash');
            $payload = map_deep($raw_json, 'wc_clean');
        } else {
            $payload = map_deep($payload, 'wp_unslash');
            $payload = map_deep($payload, 'wc_clean');
        }

        // Verify the hash key received in the payload
        $received_hash_key = isset($payload['hash_key']) ? $payload['hash_key'] : false;
        if (!$received_hash_key) {
            $qnbpaycore->save_log(__METHOD__, ' Error: Webhook missing hash_key.');
            status_header(400);
            exit('Missing hash_key');
        }

        $is_valid_request = $this->verify_webhook_hash($received_hash_key);
        if (!$is_valid_request) {
            $qnbpaycore->save_log(__METHOD__, ' Error: Invalid webhook hash key.');
            status_header(403); // Forbidden
            exit('Invalid hash_key');
        }

        // Extract necessary data from the payload
        $webhook_invoice_id = $payload['invoice_id'] ?? null;
        $payment_status = $payload['payment_status'] ?? null;
        $transaction_type = $payload['transaction_type'] ?? null;
        $order_no = $payload['order_no'] ?? null; // QNBPay transaction ID
        $error_message = $payload['error'] ?? __('Unknown error reported by webhook.', 'qnbpay-woocommerce');

        if (empty($webhook_invoice_id) || is_null($payment_status)) {
            $qnbpaycore->save_log(__METHOD__, ' Error: Missing invoice_id or payment_status in webhook payload. error_message:' . $error_message);
            status_header(400);
            exit('Missing parameters');
        }

        // Extract the WooCommerce order ID from the QNBPay invoice ID
        $invoice_id_explode = explode('_', $webhook_invoice_id);
        if (count($invoice_id_explode) != 3) {
            $qnbpaycore->save_log(__METHOD__, 'Error: Invalid invoice_id format: ' . $webhook_invoice_id);
            status_header(400);
            exit('Invalid invoice_id format');
        }

        $order_id = $invoice_id_explode[1] ?? 0;
        $order_id = intval($order_id);
        if (!($order_id && $order_id > 0)) {
            $qnbpaycore->save_log(__METHOD__, 'Error: Invalid order ID: ' . $order_id . ' (Invoice ID: ' . $webhook_invoice_id . ')');
            status_header(200);
            exit('Order ID not valid');
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            $qnbpaycore->save_log(__METHOD__, ' Error: Order not found for ID: ' . $order_id . ' (Invoice ID: ' . $webhook_invoice_id . ')');
            status_header(200);
            exit('Order not found');
        }

        // Prevent processing if order status indicates it's already paid/processed
        if ($order->has_status(wc_get_is_paid_statuses())) {
            $qnbpaycore->save_log(__METHOD__, 'Info: Order ' . $order_id . ' already processed. Current status: ' . $order->get_status());
            status_header(200);
            exit('Order already processed');
        }

        $token = $qnbpaycore->getToken();
        if (!$token) {
            $qnbpaycore->save_log(__METHOD__, 'Error: Could not get token for checkstatus call.');
            status_header(500);
            exit('Cannot get token');
        }

        // Prepare headers for the checkstatus API call
        $headers = [
            'Authorization' => 'Bearer ' . $token,
        ];

        // Generate Hash key for checkstatus
        $checkStatusHashKey = $qnbpaycore->generateHashKey([
            'invoice_id' => $webhook_invoice_id,
            'merchant_key' => $this->settings['merchant_key'],
        ]);

        // Prepare parameters for the checkstatus API call
        $checkstatus_params = [
            'invoice_id' => $webhook_invoice_id,
            'merchant_key' => $this->merchant_key,
            'hash_key' => $checkStatusHashKey,
            'include_pending_status' => true,
        ];

        // Make the checkstatus API call to verify the payment status independently
        $checkstatus_response = $qnbpaycore->doRequest('checkstatus', $checkstatus_params, $headers);

        if ($checkstatus_response['status']) {

            $jsonResponse = json_decode($checkstatus_response['body'], true);
            // Extract relevant status codes
            $mdStatus = $jsonResponse['mdStatus'] ?? 0;
            $status_code = $jsonResponse['status_code'] ?? 0;

            // If payment is confirmed successful by checkstatus
            if ($mdStatus == 1 && $status_code == 100) {
                // Complete the WooCommerce order
                $order->payment_complete();
                $order->add_order_note(__('Payment completed via QNBPay Webhook.', 'qnbpay-woocommerce'));
                // Set order status based on plugin settings
                $order->update_status($this->settings['order_status']);

                delete_post_meta($order_id, 'qnbpay_order_form');

                $payment_status_description = __('Your order has been paid successfully.', 'qnbpay-woocommerce');

                $order->add_order_note($payment_status_description);
                $qnbpaycore->save_log(__METHOD__, 'Success: Order ' . $order_id . ' updated to completed.');
                status_header(200);

                return;
            } else { // If checkstatus confirms failure or pending
                $order->update_status('failed', sprintf(__('QNBPay status confirmed as Failed via checkstatus. Reason: %s', 'qnbpay-woocommerce'), $error_message));
                $order->add_order_note(sprintf(__('QNBPay payment failed confirmation via checkstatus. Transaction ID: %s. Reason: %s', 'qnbpay-woocommerce'), $order_no, $error_message));
                $qnbpaycore->save_log(__METHOD__, ' Failure (via checkstatus): Order ' . $order_id . ' updated to failed. Reason: ' . $error_message);
            }

            status_header(200);
            exit('OK');
        } else { // If checkstatus API call itself failed
            $qnbpaycore->save_log(__METHOD__, 'Error: checkstatus request failed.');
            status_header(500);
            exit('Checkstatus request failed');
        }
    }

    /**
     * Verify Webhook Hash Key
     * Decrypts and validates the hash_key received in the webhook payload.
     *
     * @param  string  $received_hash_key  The hash_key received in the payload.
     * @return array|false Array with decrypted data on success, false on failure.
     */
    private function verify_webhook_hash($received_hash_key)
    {
        global $qnbpaycore;

        if (empty($received_hash_key)) {
            $qnbpaycore->save_log(__METHOD__, ' Error: Hash key empty.');

            return false;
        }

        // Decode the hash key format (replace '__' and split by ':')
        $components = explode(':', str_replace('__', '/', $received_hash_key));
        if (!(count($components) > 2)) {
            $qnbpaycore->save_log(__METHOD__, ' Error: Invalid hash_key format.');

            return false;
        }

        // Prepare components for decryption
        $password = sha1($this->app_secret);
        $iv = isset($components[0]) ? $components[0] : '';
        $salt = isset($components[1]) ? $components[1] : '';
        $encrypted_data = isset($components[2]) ? $components[2] : '';

        // Recreate the salt hash used during encryption
        $saltWithPassword = hash('sha256', $password . $salt);

        // Decrypt the data
        $decrypted_data = openssl_decrypt($encrypted_data, 'aes-256-cbc', $saltWithPassword, 0, $iv);

        // Check if decryption was successful and data format is correct
        if (strpos($decrypted_data, '|') === false) {
            $qnbpaycore->save_log(__METHOD__, ' Error: Invalid hash_key format.');
            // Log potential decryption failure
            $qnbpaycore->save_log(__METHOD__, ' Decryption failed or data format incorrect. Decrypted data: ' . $decrypted_data);

            return false;
        }
        $exploded_data = explode('|', $decrypted_data);
        $result = [
            'status' => isset($exploded_data[0]) ? $exploded_data[0] : 0,
            'total' => isset($exploded_data[1]) ? $exploded_data[1] : 0,
            'invoiceId' => isset($exploded_data[2]) ? $exploded_data[2] : 0,
            'orderId' => isset($exploded_data[3]) ? $exploded_data[3] : 0,
            'currencyCode' => isset($exploded_data[4]) ? $exploded_data[4] : '',
        ];

        // Log the process for debugging
        $qnbpaycore->save_log(__METHOD__, ['received_hash_key' => $received_hash_key, 'decrypted_data' => $decrypted_data, 'exploded_data' => $exploded_data, 'result' => $result]);

        return $result;
    }
}
