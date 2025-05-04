<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles AJAX requests for the QNBPay gateway.
 *
 * Includes BIN validation, payment rechecks, admin tests, and debug actions.
 *
 * @since   1.0.0
 */
class QNBPay_Ajax
{
    /** @var QNBPay_Ajax|null Singleton instance */
    private static $instance = null;

    /** @var array QNBPay gateway settings. */
    public $qnbOptions;

    /** @var bool Whether installments are enabled globally. */
    public $enableInstallment;

    /** @var int Maximum number of installments allowed globally. */
    public $limitInstallment;

    /** @var bool Whether to limit installments based on product settings. */
    public $limitInstallmentByProduct;

    /** @var bool Whether to limit installments based on cart amount. */
    public $limitInstallmentByCart;

    /** @var float Minimum cart amount required for installments. */
    public $limitInstallmentByCartAmount;

    /**
     * Get the singleton instance of this class.
     *
     * @return QNBPay_Ajax
     */
    public static function get_instance()
    {
        // If instance doesn't exist, create it
        if (is_null(self::$instance)) {
            // Create a new instance of the class
            $self = new self();
            // Initialize the instance with necessary settings
            $self->init();
            // Assign the newly created instance to the static variable
            self::$instance = $self;
        }

        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        // Intentionally left blank for Singleton pattern
    }

    /**
     * Initialize AJAX hooks.
     * Loads settings and registers AJAX actions for logged-in and non-logged-in users.
     *
     * @since 1.0.0
     * @return void
     */
    public function init()
    {
        // Load QNBPay settings from options
        $this->qnbOptions = get_option('woocommerce_qnbpay_settings', []);
        $this->enableInstallment = data_get($this->qnbOptions, 'installment', 'no') === 'yes';
        $this->limitInstallment = data_get($this->qnbOptions, 'limitInstallment', 1);
        $this->limitInstallmentByProduct = data_get($this->qnbOptions, 'limitInstallmentByProduct', 'no') === 'yes';
        $this->limitInstallmentByCart = data_get($this->qnbOptions, 'limitInstallmentByCart', 'no') === 'yes';
        $this->limitInstallmentByCartAmount = data_get($this->qnbOptions, 'limitInstallmentByCartAmount', 0);

        // Hook for the main AJAX handler
        add_action('wp_ajax_qnbpay_ajax', [$this, 'qnbpay_ajax']);
        add_action('wp_ajax_nopriv_qnbpay_ajax', [$this, 'qnbpay_ajax']);
    }

    /**
     * Bin number validation request via ajax.
     *
     * Validates the BIN, gets installment options from QNBPay API, calculates max allowed installments,
     * and returns the result including HTML for the installment selection.
     * @param  array  $postData  Data received from the AJAX request.
     * @return array|void Result array or sends JSON response if called directly via AJAX.
     */
    public function validate_bin($postData)
    {
        global $qnbpaycore;
        // Initialize result array
        $result = [
            'time' => date_i18n('d.m.Y H:i:s'),
            'status' => false,
            'message' => false,
            'html' => false,
        ];

        // Get method from POST data (used to determine if called directly or internally)
        $method = data_get($postData, 'method', false);

        if ($method == 'validate_bin' && !wp_verify_nonce(data_get($postData, 'nonce'), 'qnbpay_ajax_nonce')) {
            $result['message'] = __('Invalid nonce', 'qnbpay-woocommerce');
            wp_json_encode($result);
            return;
        }

        // Extract first 8 digits of the card number for BIN check
        $binNumber = substr(data_get($postData, 'binNumber'), 0, 8);

        // Validate BIN length
        if (strlen($binNumber) != 8) {
            $result['message'] = __('Credit card number is required.', 'qnbpay-woocommerce');
            wp_json_encode($result);
            return;
        }

        // Validate BIN contains only digits
        if (!ctype_digit($binNumber)) {
            $result['message'] = __('Credit card number is invalid.', 'qnbpay-woocommerce');
            wp_json_encode($result);
            return;
        }

        // Get context (cart or order)
        $state = data_get($postData, 'state');

        // Calculate the maximum allowed installment based on settings and context
        $maxInstallment = self::calculateMaxInstallment($state, ($postData['order'] ?? null));
        // Get order total, either from POST data or calculated based on context
        if (data_get($postData, 'ordertotal')) {
            $orderTotal = data_get($postData, 'ordertotal');
        } else {
            // Calculate total from cart or order if not provided
            $orderTotal = self::getOrderTotal($state);
        }

        if (data_get($postData, 'currency')) {
            $orderCurrency = data_get($postData, 'currency');
        } else {
            // Get currency from cart or order if not provided
            $orderCurrency = self::getOrderCurrency($state);
        }

        // Make the API request to QNBPay to get BIN details and installment options
        $response = $qnbpaycore->requestBin([
            'credit_card' => $binNumber,
            'amount' => $orderTotal,
            'currency_code' => $orderCurrency,
        ]);

        $htmlParams = [
            // Parameters needed for rendering the installment HTML
            'maxInstallment' => $maxInstallment,
            'state' => data_get($postData, 'state'),
        ];

        $binInstallments = [];
        // If the API request was successful
        if ($response['status']) {
            // Get the installment options from the response body
            $binInstallments = $response['body'];
            $result['message'] = __('Success', 'qnbpay-woocommerce');
        } else {
            // Use the error message from the API response
            $result['message'] = $response['message'];
        }
        $result = array_merge(
            $result,
            // Merge results: status, message, raw card info, max installment, and rendered HTML
            [
                'status' => true,
                'message' => __('Success', 'qnbpay-woocommerce'),
                'cardInformation' => $response['body'],
                'maxInstallment' => $maxInstallment,
                'html' => self::renderedHtml($binInstallments, $htmlParams),
            ]
        );
        // If called directly via AJAX, send JSON response
        if ($method == 'validate_bin') {
            wp_send_json($result);
            return;
        } else {
            return $result;
        }
    }

    /**
     * Recheck the status of a pending payment via AJAX.
     *
     * Uses the QNBPay checkstatus API to verify the payment status and updates the order accordingly.
     * @param  array  $postData  Data received from the AJAX request, expects 'orderid'.
     * @return void Sends JSON response.
     */
    private function recheckpayment($postData)
    {
        global $qnbpaycore;
        // Initialize result array
        $result = [
            'time' => date_i18n('d.m.Y H:i:s'),
            'status' => false,
            'message' => false,
            'retry' => false,
            'url' => false,
        ];

        if (!wp_verify_nonce(data_get($postData, 'nonce'), 'qnbpay_ajax_nonce')) {
            $result['retry'] = true;
            $result['message'] = __('Invalid nonce', 'qnbpay-woocommerce');
            wp_json_encode($result);
            return;
        }

        // Get order ID from POST data
        $orderId = data_get($postData, 'orderid', 0);
        if ($orderId == 0) {
            $result['message'] = __('Order ID is required.', 'qnbpay-woocommerce');
            wp_json_encode($result);
            return;
        }
        // Get the order object
        $order = wc_get_order($orderId);
        if (!$order) {
            $result['message'] = __('Order not found.', 'qnbpay-woocommerce');
            wp_json_encode($result);
            return;
        }

        // Get API token
        $token = $qnbpaycore->getToken();
        $headers = [
            'Authorization' => 'Bearer ' . $token,
        ];

        // Generate Hash key for checkstatus
        $checkStatusHashKey = $qnbpaycore->generateHashKey([
            'invoice_id' => get_post_meta($orderId, 'qnbpay_invoice_id', true),
            'merchant_key' => data_get($this->qnbOptions, 'merchant_key'),
        ]);

        // Prepare parameters for checkstatus request
        // Get invoice ID  from order meta
        $params = [
            // Retrieve the QNBPay invoice ID stored earlier
            'invoice_id' => get_post_meta($orderId, 'qnbpay_invoice_id', true),
            'merchant_key' => data_get($this->qnbOptions, 'merchant_key'),
            'hash_key' => $checkStatusHashKey,
            'include_pending_status' => true,
        ];

        $response = $qnbpaycore->doRequest('checkstatus', $params, $headers);

        $qnbpaycore->saveOrderLog($orderId, 'recheckstatus', $response, $postData);

        // If the checkstatus request was successful
        if ($response['status']) {
            $jsonResponse = json_decode($response['body'], true);
            // Extract relevant status codes
            $mdStatus = $jsonResponse['mdStatus'] ?? 0;
            $status_code = $jsonResponse['status_code'] ?? 0;

            // If payment is confirmed successful
            if ($mdStatus == 1 && $status_code == 100) {
                // Complete the WooCommerce order
                $order->payment_complete();
                $order->add_order_note(__('Payment completed via QNBPay.', 'qnbpay-woocommerce'));
                // Set order status based on plugin settings
                $order->update_status(data_get($this->qnbOptions, 'order_status'));

                delete_post_meta($orderId, 'qnbpay_order_form');

                $payment_status_description = __('Your order has been paid successfully.', 'qnbpay-woocommerce');

                wc_add_notice($payment_status_description, 'success');
                // Get the URL for the order received (thank you) page
                $redirectUrl = add_query_arg(['qnbpaysuccess' => 1], $order->get_checkout_order_received_url());

                // Prepare JSON response indicating success and redirect URL
                $result['status'] = true;
                $result['url'] = $redirectUrl;
                $result['message'] = $payment_status_description;
                wp_json_encode($result);

                return;
            } else {
                // Payment is not confirmed successful by the API
                $payment_status_description = $post['status_description'] ?? __('Payment has not been confirmed.', 'qnbpay-woocommerce');

                wc_add_notice($payment_status_description, 'error');
                // Get URL to redirect back to payment page with error
                $redirectUrl = add_query_arg(['qnbpayerror' => 1, 'pay_for_order' => true, 'key' => $order->get_order_key()], wc_get_endpoint_url('order-pay', $orderId, wc_get_checkout_url()));

                // Prepare JSON response indicating failure and redirect URL
                $result['url'] = $redirectUrl;
                $result['message'] = $payment_status_description;
                wp_json_encode($result);

                return;
            }
        } else {
            $result['retry'] == true;
            // Prepare JSON response indicating a retry is needed
            $result['message'] = __('Check result not found. Trying again.', 'qnbpay-woocommerce');
            wp_json_encode($result);

            return;
        }
    }

    /**
     * Perform tests for merchant information, commission rates, BIN check, and remote connection via AJAX.
     * Only available for users who can manage options.
     *
     * @since 1.0.0
     * @return void Sends JSON response.
     */
    private function qnbpay_test($postData)
    {
        global $qnbpaycore;
        // Get test card numbers
        $test_cards = $qnbpaycore->get_test_cards();

        // Initialize result array
        $result = [
            'time' => time(),
            'status' => false,
            'commissioncheck' => false,
            'bincheck' => false,
            'remote' => false,
            'message' => false,
        ];

        if (!wp_verify_nonce(data_get($postData, 'nonce'), 'qnbpay_ajax_nonce')) {
            $result['message'] = __('Invalid nonce', 'qnbpay-woocommerce');
            wp_json_encode($result);
            return;
        }

        // Check if the current user has permission to manage options
        if (current_user_can('manage_options')) {
            wp_send_json($result);
            return;
        }
        // Check if test cards are available
        if ($test_cards) {
            $result['remote'] = true;
            if (
                // Check if essential merchant credentials are saved
                data_get($this->qnbOptions, 'merchant_id') &&
                data_get($this->qnbOptions, 'merchant_key') &&
                data_get($this->qnbOptions, 'app_key') &&
                data_get($this->qnbOptions, 'app_secret')
            ) {
                $commissioncheck = $qnbpaycore->getCommissions();
                $result['commissiondata'] = $commissioncheck;
                // Check if commission request was successful
                $result['commissioncheck'] = $commissioncheck['status'];

                // Select a random test card
                $result['credit_card'] = $test_cards[array_rand($test_cards)];
                $binNumber = substr($result['credit_card'], 0, 8);
                // Perform a BIN check using the test card
                $bincheck = $qnbpaycore->requestBin(['credit_card' => $binNumber]);
                $result['bindata'] = $bincheck;
                $result['bincheck'] = $bincheck['status'];

                $result['status'] = true;
            } else {
                $result['message'] = __('The test function can be performed after saving the merchant information.', 'qnbpay-woocommerce');
                // Error message if credentials are not saved
            }
        }

        wp_send_json($result);
        return;
    }

    /**
     * General Ajax Request Callback
     * Routes AJAX requests based on the 'method' parameter.
     *
     * @since 1.0.0
     * @return void Sends JSON response or dies.
     */
    public function qnbpay_ajax()
    {
        // Get POST data, cleaning it
        $postData = map_deep($_POST, 'wc_clean');
        // Get the requested method
        $method = data_get($postData, 'method');

        // Route the request based on the method
        if ($method == 'validate_bin') {
            $this->validate_bin($postData);
        }

        if ($method == 'recheckpayment') {
            // Handle payment recheck request
            $this->recheckpayment($postData);
        }

        if ($method == 'qnbpay_test') {
            // Handle admin test request
            self::qnbpay_test($postData);
        }

        if ($method == 'debug_download') {
            // Handle debug file download request
            self::debug_download($postData);
        }

        if ($method == 'debug_clear') {
            // Handle debug file clear request
            self::debug_clear($postData);
        }

        wp_die();
    }

    /**
     * Get the currency code based on the current context (cart or order).
     *
     * @since 1.0.0
     * @param  string  $orderState  'cart' or 'order'.
     * @return string WooCommerce currency code.
     */
    private function getOrderCurrency($orderState)
    {
        global $woocommerce;
        // Default currency
        $orderTotal = false;
        if ($orderState == 'cart') {
            // Get currency from WooCommerce settings if in cart context
            $orderCurrency = get_woocommerce_currency();
        } elseif ($orderState == 'order') {
            // If in order context (e.g., order-pay page)
            if (get_query_var('order-pay')) {
                $order = wc_get_order(get_query_var('order-pay'));
                // Get currency from the order object
                if ($order) {
                    $orderCurrency = $order->get_currency();
                } else {
                    $orderCurrency = get_woocommerce_currency();
                }
            } else {
                $orderCurrency = get_woocommerce_currency();
            }
        }

        return $orderCurrency;
    }

    /**
     * Get the order total based on the current context (cart or order).
     *
     * @since 1.0.0
     * @param  string  $orderState  'cart' or 'order'.
     * @return float|null Order total or null if not found.
     */
    private function getOrderTotal($orderState)
    {
        global $woocommerce;
        // Default total
        $orderTotal = false;
        if ($orderState == 'cart') {
            // Get total from the cart object
            $orderTotal = data_get($woocommerce, 'cart.total');
        } elseif ($orderState == 'order') {
            // If in order context
            if (get_query_var('order-pay')) {
                $order = wc_get_order(get_query_var('order-pay'));
                // Get total from the order object
                if ($order) {
                    $orderTotal = $order->get_total();
                } else {
                    $orderTotal = data_get($woocommerce, 'cart.total');
                }
            } else {
                $orderTotal = data_get($woocommerce, 'cart.total');
            }
        }

        return $orderTotal;
    }

    /**
     * Render html installment template
     *
     * @since 1.0.0
     * @param  array|false  $installments  Array of installment options from QNBPay API, or false.
     * @param  array  $params  Additional parameters like 'maxInstallment' and 'state'.
     * @return string HTML for the installment selection section.
     */
    private function renderedHtml($installments, $params)
    {
        // Hidden field to store the current state (cart/order)
        $formHtml = '<input type="hidden" name="qnbpay-order-state" value="' . ($params['state'] ?? 'cart') . '">';

        // If no installments available or max installment is 1 (single payment)
        if (!$installments || $params['maxInstallment'] == 1) {
            // Add hidden input for single payment (installment 1)
            $formHtml .= '<input type="hidden" name="qnbpay-installment" value="1">';

            return $formHtml;
        }

        // Start the installment selection container
        $formHtml .= '<div class="qnypay-installments">';
        $formHtml .= '<div class="qnypay-installments-title">' . __('Installment Selection', 'qnbpay-woocommerce') . '</div>';

        // Loop through available installment options from the API response
        foreach ($installments as $perInstallmentKey) {
            $no = $perInstallmentKey['installments_number'];
            // Stop if the installment number exceeds the calculated maximum
            if ($no > $params['maxInstallment']) {
                break;
            }
            // Create radio button and label for each installment option
            $optionValue = $no == 1 ? __('Payment In Advence', 'qnbpay-woocommerce') : sprintf(__('%d installments', 'qnbpay-woocommerce'), $no);
            $formHtml .= ' <div class="qnypay-installments-row">
                <input ' . ($no == 1 ? 'checked' : '') . ' id="installment-pick' . $no . '" type="radio" class="input-radio w-w-50" name="qnbpay-installment" value="' . $no . '">
                <label for="installment-pick' . $no . '"> ' . $optionValue . '</label>
            </div>';
        }
        $formHtml .= '</div>';

        return $formHtml;
    }

    /**
     * Calculate the maximum allowed installment number based on global, product, and cart settings.
     *
     * @since 1.0.0
     * @param  string  $state  'cart' or 'order'.
     * @param  WC_Order|null  $order  The order object if state is 'order'.
     * @return int The maximum allowed installment number.
     */
    private function calculateMaxInstallment($state = 'cart', $order = null)
    {
        global $woocommerce;

        // If installments are globally disabled, return 1
        if (!$this->enableInstallment) {
            return 1;
        }

        // Start with the global installment limit setting
        $_limitInstallment = intval($this->limitInstallment);

        // If limiting by product is disabled, return the global limit
        if (!$this->limitInstallmentByProduct) {
            return $_limitInstallment;
        }

        // --- Calculate Limit Based on Context (Cart or Order) ---
        if ($state == 'cart') {
            // Get items currently in the cart
            $cart_contents = $woocommerce->cart->get_cart();
            if ($cart_contents && !empty($cart_contents)) {
                // Loop through cart items
                foreach ($cart_contents as $cart_content) {
                    // Get the installment limit set for the specific product (if any)
                    $product_limitInstallment = get_post_meta($cart_content['product_id'], '_limitInstallment', true);
                    // If a product limit exists and is lower than the current limit, update the limit
                    if (
                        $product_limitInstallment &&
                        intval($product_limitInstallment) > 0 &&
                        $_limitInstallment > intval($product_limitInstallment)
                    ) {
                        $_limitInstallment = intval($product_limitInstallment);
                    }
                }
            }
            // Check cart amount limit if enabled
            if ($this->limitInstallmentByCart && floatval($this->limitInstallmentByCartAmount) > 0) {
                $cartTotal = $woocommerce->cart->get_cart_contents_total();
                // If cart total is below the minimum amount, force single payment
                if (floatval($cartTotal) < floatval($this->limitInstallmentByCartAmount)) {
                    $_limitInstallment = 1;
                }
            }
        } elseif ($state == 'order' && $order) {
            // If in order context and order object exists
            if ($order) {
                // Get items from the order
                $cart_contents = $order->get_items();
                if ($cart_contents && !empty($cart_contents)) {
                    foreach ($cart_contents as $cart_content) {
                        // Get product-specific limit
                        $product_limitInstallment = get_post_meta($cart_content['product_id'], '_limitInstallment', true);
                        if (
                            $product_limitInstallment &&
                            intval($product_limitInstallment) > 0 &&
                            $_limitInstallment > intval($product_limitInstallment)
                        ) {
                            $_limitInstallment = intval($product_limitInstallment);
                        }
                    }
                }
                // Check order amount limit if enabled
                if ($this->limitInstallmentByCart && floatval($this->limitInstallmentByCartAmount) > 0) {
                    $cartTotal = $order->get_total();
                    // If order total is below the minimum, force single payment
                    if (floatval($cartTotal) < floatval($this->limitInstallmentByCartAmount)) {
                        $_limitInstallment = 1;
                    }
                }
            }
        }

        return $_limitInstallment;
    }

    /**
     * Handle AJAX request to download the debug log file.
     * Only available for users who can manage options.
     *
     * @since 1.0.0
     * @return void Sends JSON response with file URL or error.
     */
    private function debug_download($postData)
    {
        global $qnbpaycore;
        // Initialize result array
        $result = [
            'time' => time(),
            'status' => false,
            'message' => __('Cant find debug file', 'qnbpay-woocommerce'),
        ];

        if (!wp_verify_nonce(data_get($postData, 'nonce'), 'qnbpay_ajax_nonce')) {
            $result['message'] = __('Invalid nonce', 'qnbpay-woocommerce');
            wp_json_encode($result);
            return;
        }

        // Check user permissions
        if (current_user_can('manage_options')) {
            wp_send_json($result);
            return;
        }

        // Get the debug filename
        $filename = $qnbpaycore->debug_file();
        // Check if the file exists
        if (file_exists(QNBPAY_DIR . $filename)) {
            $result['status'] = true;
            // Provide file URL and a suggested download filename
            $result['message'] = __('Success', 'qnbpay-woocommerce');
            $result['file'] = QNBPAY_URL . $filename;
            $result['filename'] = wp_generate_uuid4() . '.log';
        }

        wp_send_json($result);
        return;
    }

    /**
     * Handle AJAX request to clear the debug log file.
     * Only available for users who can manage options.
     *
     * @since 1.0.0
     * @return void Sends JSON response indicating success or failure.
     */
    private function debug_clear($postData)
    {
        global $qnbpaycore;
        // Initialize result array
        $result = [
            'time' => time(),
            'status' => false,
            'message' => __('Cant find debug file', 'qnbpay-woocommerce'),
        ];

        if (!wp_verify_nonce(data_get($postData, 'nonce'), 'qnbpay_ajax_nonce')) {
            $result['message'] = __('Invalid nonce', 'qnbpay-woocommerce');
            wp_json_encode($result);
            return;
        }

        // Check user permissions
        if (current_user_can('manage_options')) {
            wp_send_json($result);
            return;
        }

        // Get the debug filename
        $filename = $qnbpaycore->debug_file();
        // Check if the file exists and is writable
        if (file_exists(QNBPAY_DIR . $filename) && is_writable(QNBPAY_DIR . $filename)) {
            // Delete the file
            unlink(QNBPAY_DIR . $filename); // Use server path for unlink
            $result['status'] = true;
            $result['message'] = __('Success', 'qnbpay-woocommerce');
        }
        wp_send_json($result);
        return;
    }
}
