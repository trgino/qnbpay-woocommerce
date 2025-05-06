<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core function for QNBPay Payment Class.
 *
 * Handles API interactions, token management, request formatting,
 * logging, and utility functions for the QNBPay gateway.
 * Implements the Singleton pattern.
 *
 * @since   1.0.0
 */
class QNBPay_Core
{
    /** @var QNBPay_Core|null Singleton instance */
    private static $instance = null;

    /** @var array QNBPay gateway settings. */
    private $qnbOptions = [];

    /** @var bool Whether debug mode is enabled. */
    private $debugMode = false;

    /** @var string|false Filename for the debug log. */
    private $debugFile = false;

    /** @var string|null The API host URL (test or production). */
    private $apiHost = null;

    /** @var string Production API endpoint URL. */
    private $productionApiHost = 'https://portal.qnbpay.com.tr/ccpayment/api/';

    /** @var string Test API endpoint URL. */
    private $testApiHost = 'https://test.qnbpay.com.tr/ccpayment/api/';

    /**
     * Get the singleton instance of this class.
     *
     * @return QNBPay_Core
     */
    public static function get_instance()
    {
        // Check if the instance is null, meaning it hasn't been created yet
        if (is_null(self::$instance)) {
            // Create a new instance of the class
            $self = new self();
            // Initialize the instance with necessary settings
            $self->init();
            // Assign the newly created instance to the static variable
            self::$instance = $self;
        }

        // Return the singleton instance
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
     * Initialize the core class properties and hooks.
     *
     * Loads settings, sets debug mode, determines API host, and adds WordPress hooks.
     * @since 1.0.0
     * @return void
     */
    public function init()
    {
        // Retrieve QNBPay settings from WooCommerce options
        $this->qnbOptions = get_option('woocommerce_qnbpay_settings', []);

        // Set debug mode based on the settings
        $this->debugMode = data_get($this->qnbOptions, 'debugMode', 'no') === 'yes';

        // Generate or retrieve the debug file name
        $this->debugFile = $this->debug_file();

        // Determine the API host based on the settings
        $this->apiHost = self::apiHost($this->qnbOptions);

        add_action('init', [$this, 'qnbpay_rewrites']);

        // Add query variables for QNBPay
        add_action('query_vars', [$this, 'qnbpay_vars']);

        // Handle form submissions and results for QNBPay
        add_action('template_redirect', [$this, 'qnbpay_forms']);
    }

    /**
     * Add custom rewrite rules for QNBPay form and result handling.
     *
     * Maps URLs like /qnbpayform/{order_id}/ and /qnbpayresult/{order_id}/ to WordPress query variables.
     * @since 1.0.0
     * @return void
     */
    public function qnbpay_rewrites()
    {
        // Add custom rewrite rules for QNBPay forms and results
        add_rewrite_rule('^qnbpayform/([0-9]+)/?', 'index.php?qnbpayform=$matches[1]', 'top');
        add_rewrite_rule('^qnbpayresult/([0-9]+)/?', 'index.php?qnbpayresult=$matches[1]', 'top');
    }

    /**
     * Add custom query variables used by the rewrite rules.
     *
     * Registers 'qnbpayform' and 'qnbpayresult' so WordPress recognizes them.
     * @param  array  $vars  Existing query variables.
     * @return array Modified query variables.
     */
    public function qnbpay_vars($vars)
    {
        // Add 'qnbpayform' to the list of query variables
        $vars[] = 'qnbpayform';

        // Add 'qnbpayresult' to the list of query variables
        $vars[] = 'qnbpayresult';

        // Return the modified list of query variables
        return $vars;
    }

    /**
     * Handle requests for the custom QNBPay form and result URLs.
     * Outputs HTML for 3D Secure form or processes payment results and redirects.
     *
     * @since 1.0.0
     */
    public function qnbpay_forms()
    {
        // Check if the 'qnbpayform' query variable is set
        if (get_query_var('qnbpayform')) {
            // --- Handle 3D Secure Form Display ---
            $orderId = get_query_var('qnbpayform');
            if ($orderId > 0) {
                // Retrieve the order key from the URL
                $order_key = $_GET['key'] ?? '';
                // Get the order form data from post meta
                $qnbpay_order_form = get_post_meta($orderId, 'qnbpay_order_form', true);
                // Retrieve the WooCommerce order object
                $order = wc_get_order($orderId);
                // Check if the order form and order exist and the keys match
                if ($qnbpay_order_form && $order && $order_key == $order->get_order_key()) {
                    global $wp_query;
                    $wp_query->is_404 = false; // Prevent 404 error

                    // Set the content type to HTML
                    header('Content-Type: text/html; charset=utf-8');

                    // Output the order form as an HTML page
                    echo '<!DOCTYPE html>';
                    echo '<html lang="tr">';
                    echo '<head>';
                    echo '<meta charset="UTF-8">';
                    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
                    echo '<title>QNBPay</title>';
                    echo '</head>';
                    echo '<body>';
                    echo implode('', $qnbpay_order_form);
                    echo '</body>';
                    echo '</html>';
                    exit;
                } else {
                    // Redirect to the home page if the order form is invalid
                    wp_redirect(home_url(), 302, 'qnbpayform');
                    exit;
                }
            }
        }

        // Check if the 'qnbpayresult' query variable is set
        if (get_query_var('qnbpayresult')) {
            // --- Handle Payment Result Processing ---
            $orderId = get_query_var('qnbpayresult');
            if ($orderId > 0) {
                // Retrieve the order key from the URL
                $order_key = $_GET['key'] ?? '';
                // Get the order form data from post meta
                $qnbpay_order_form = get_post_meta($orderId, 'qnbpay_order_form', true);
                // Retrieve the WooCommerce order object
                $order = wc_get_order($orderId);
                // Check if the order form and order exist and the keys match
                if ($qnbpay_order_form && $order && $order_key == $order->get_order_key()) {
                    global $wp_query;
                    $wp_query->is_404 = false; // Prevent 404 error

                    // Define the statuses that indicate a completed payment
                    $paidStatuses = [
                        'wc-completed' => 'wc-completed',
                    ];

                    $paidStatuses[data_get($this->qnbOptions, 'order_status', 'wc-completed')] = data_get($this->qnbOptions, 'order_status', 'wc-completed');

                    // Check if the order status is in the list of paid statuses
                    // If already paid, redirect to thank you page immediately.
                    if (in_array($order->get_status(), $paidStatuses)) {
                        // Delete the order form meta and redirect to the order received page
                        delete_post_meta($orderId, 'qnbpay_order_form');
                        wp_redirect($order->get_checkout_order_received_url(), 302, 'qnbpayresult');
                        exit;
                    }

                    // Determine the request method and clean the POST data
                    $requstMethod = $_SERVER['REQUEST_METHOD'] ?? '';
                    $post = ($requstMethod == 'POST' ? map_deep($_POST, 'wc_clean') : []);
                    // Retrieve payment status and invoice ID from the POST data
                    $payment_status = $post['payment_status'] ?? 0;
                    $invoice_id = $post['invoice_id'] ?? false;

                    // Log the order response
                    $this->saveOrderLog($orderId, 'qnbReply', $post);

                    // Handle rejected payment (payment_status == 0)
                    if ($payment_status == 0) {
                        $payment_status_description = $post['status_description'] ?? __('Payment for your order has been rejected by the payment broker.', 'qnbpay-woocommerce');

                        $order->update_status('pending', $payment_status_description);

                        update_post_meta($orderId, 'qnbpayerror', $payment_status_description);

                        $redirectUrl = add_query_arg(['qnbpayerror' => 1, 'pay_for_order' => true, 'key' => $order->get_order_key()], wc_get_endpoint_url('order-pay', $orderId, wc_get_checkout_url()));
                        wp_redirect($redirectUrl, 302, $payment_status_description);
                        exit;
                    }

                    // Handle missing invoice ID in the response
                    if (!$invoice_id) {
                        $payment_status_description = __('Order identifier not found.', 'qnbpay-woocommerce');

                        $order->update_status('pending', $payment_status_description);

                        update_post_meta($orderId, 'qnbpayerror', $payment_status_description);

                        $redirectUrl = add_query_arg(['qnbpayerror' => 1, 'pay_for_order' => true, 'key' => $order->get_order_key()], wc_get_endpoint_url('order-pay', $orderId, wc_get_checkout_url()));
                        wp_redirect($redirectUrl, 302, $payment_status_description);
                        exit;
                    }

                    // Handle potentially successful payment (payment_status == 1) - requires verification via checkstatus
                    if ($payment_status == 1 && $invoice_id) {
                        update_post_meta($orderId, 'qnbpay_invoice_id', $invoice_id);

                        // Get the authorization token
                        $token = $this->getToken();
                        $headers = [
                            'Authorization' => 'Bearer ' . $token,
                        ];

                        // Generate Hash key for checkstatus
                        $checkStatusHashKey = $this->generateHashKey([
                            'invoice_id' => get_post_meta($orderId, 'qnbpay_invoice_id', true),
                            'merchant_key' => data_get($this->qnbOptions, 'merchant_key'),
                        ]);

                        // Prepare parameters for checkstatus request
                        // Get invoice ID  from order meta
                        $params = [
                            'invoice_id' => $invoice_id,
                            'merchant_key' => data_get($this->qnbOptions, 'merchant_key'),
                            'hash_key' => $checkStatusHashKey,
                            'include_pending_status' => true,
                        ];

                        // Send a request to check the payment status
                        $response = $this->doRequest('checkstatus', $params, $headers);

                        // Log the status check response
                        $post['generated_params'] = $params;
                        $this->saveOrderLog($orderId, 'checkstatus', $response, $post);
                        // Process the checkstatus response
                        if ($response['status']) {
                            $jsonResponse = json_decode($response['body'], true);
                            $mdStatus = $jsonResponse['mdStatus'] ?? 0;
                            $status_code = $jsonResponse['status_code'] ?? 0;

                            // If checkstatus confirms successful payment (mdStatus=1, status_code=100)
                            if ($mdStatus == 1 && $status_code == 100) {

                                $order->payment_complete();
                                $order->add_order_note(__('Payment completed via QNBPay.', 'qnbpay-woocommerce'));
                                $order->update_status(data_get($this->qnbOptions, 'order_status'));

                                delete_post_meta($orderId, 'qnbpay_order_form');
                                delete_post_meta($orderId, 'qnbpayerror');
                                delete_post_meta($orderId, 'qnbpayrecheck');

                                $payment_status_description = __('Your order has been paid successfully.', 'qnbpay-woocommerce');

                                $redirectUrl = add_query_arg(['qnbpaysuccess' => 1], $order->get_checkout_order_received_url());
                                wp_redirect($redirectUrl, 302, $payment_status_description);
                                exit;
                            } else {
                                // Handle payment confirmed as failed or still pending by checkstatus
                                $payment_status_description = $post['status_description'] ?? __('Payment has not been confirmed.', 'qnbpay-woocommerce');

                                $order->update_status('pending', $payment_status_description);

                                update_post_meta($orderId, 'qnbpayerror', $payment_status_description);

                                $redirectUrl = add_query_arg(['qnbpayerror' => 1, 'pay_for_order' => true, 'key' => $order->get_order_key()], wc_get_endpoint_url('order-pay', $orderId, wc_get_checkout_url()));

                                wp_redirect($redirectUrl, 302, $payment_status_description);
                                exit;
                            }
                        } else {
                            // Handle checkstatus API call failure - assume pending and ask user to wait/recheck
                            $payment_status_description = __('Your payment is being processed. Please wait...', 'qnbpay-woocommerce');

                            $order->update_status('pending', $payment_status_description);

                            update_post_meta($orderId, 'qnbpayrecheck', $payment_status_description);

                            $redirectUrl = add_query_arg(['qnbpayrecheck' => 1, 'key' => $order->get_order_key()], wc_get_endpoint_url('order-pay', $orderId, wc_get_checkout_url()));

                            wp_redirect($redirectUrl, 302, $payment_status_description);
                            exit;
                        }
                    }
                } else {
                    // Redirect to the checkout page if the order form is invalid
                    wp_redirect(wc_get_checkout_url(), 302, 'qnbpayresult');
                    exit;
                }
            }
        }
    }

    /**
     * Get Token from QNBPay API
     *
     * Retrieves an API token, using a transient cache to avoid repeated requests.
     * @return string|false The API token or false on failure.
     */
    public function getToken()
    {
        // Define the transient key for storing the token
        $tokenKey = 'qnybayToken';

        // Attempt to retrieve the token from the transient cache
        $token = get_transient($tokenKey);

        // If the token is found in the cache, return it
        if ($token === true) {
            return $token;
        }

        // Prepare parameters for the token request
        $params = [
            'app_id' => data_get($this->qnbOptions, 'app_key'),
            'app_secret' => data_get($this->qnbOptions, 'app_secret'),
        ];

        // Send a request to obtain a new token
        $response = $this->doRequest('token', $params);

        // Check if the response is successful and the status code is 200
        if ($response['status'] && $response['code'] == 200) {
            // Decode the response body to extract the token
            $responseBody = json_decode($response['body'], true);

            // If the token is present in the response, store it in the transient cache
            if (isset($responseBody['data']['token'])) {
                $token = $responseBody['data']['token'];
                set_transient($tokenKey, $token, HOUR_IN_SECONDS);

                return $token;
            }
        }

        // Return false if the token could not be retrieved
        return false;
    }

    /**
     * Request Bin number for card details
     *
     * Sends the first 8 digits of a credit card (BIN) to QNBPay to get installment options.
     * @param  array  $params  Parameters including 'credit_card', 'amount', 'currency_code'.
     * @return array Response array with 'status', 'body', 'code', 'message'.
     */
    public function requestBin($params)
    {
        // Initialize the response array with default values
        $response = [
            'status' => false,
            'body' => [],
            'code' => '-1',
            'message' => __('Unexpected error occurred.', 'qnbpay-woocommerce'),
        ];

        // Retrieve the authorization token
        $token = self::getToken();

        // Check if the token retrieval was unsuccessful
        if (!$token) {
            $response['message'] = __('Cant get token from payment agent.', 'qnbpay-woocommerce');

            return $response; // Return the response with an error message
        }

        // Set the authorization header using the retrieved token
        $headers = [
            'Authorization' => 'Bearer ' . $token,
        ];

        // Prepare the parameters for the request
        $postParams = [
            'credit_card' => data_get($params, 'credit_card'),
            'amount' => data_get($params, 'amount', '100'),
            'currency_code' => data_get($params, 'currency_code', get_woocommerce_currency()),
            'merchant_key' => data_get($this->qnbOptions, 'merchant_key'),
            'is_comission_from_user' => 0,
        ];

        // Send a request to the 'getpos' endpoint with the prepared parameters and headers
        $_response = $this->doRequest('getpos', $postParams, $headers);

        // Log the response from the request
        $this->save_log(__METHOD__, $_response);

        // Set the response code based on the request response
        $response['code'] = $_response['code'];

        // Check if the request was successful and the response code is 200
        if ($_response['status'] && $_response['code'] == 200) {
            // Decode the response body to extract data
            $responseBody = json_decode($_response['body'], true);
            $response['body'] = $responseBody;

            // Check if the response contains a status code
            if (isset($responseBody['status_code'])) {
                $response['code'] = $responseBody['status_code'];
                $response['message'] = $responseBody['status_description'];

                // If the status code indicates success, update the response status and body
                if ($responseBody['status_code'] == 100) {
                    $response['status'] = true;
                    $response['body'] = data_get($responseBody, 'data');
                }
            } else {
                // Set an error message if no results were obtained from the payment agent
                $response['message'] = __('Cant get any results from payment agent.', 'qnbpay-woocommerce');
            }
        }

        // Return the final response
        return $response;
    }

    /**
     * Get Commissions Information from QNBPay API.
     *
     * Fetches the commission rates configured for the merchant account.
     * @return array Response array with 'status', 'body', 'code', 'message'.
     */
    public function getCommissions()
    {
        // Initialize the response array with default values
        $response = [
            'status' => false,
            'body' => [],
            'code' => '-1',
            'message' => __('Unexpected error occurred.', 'qnbpay-woocommerce'),
        ];

        // Retrieve the authorization token
        $token = self::getToken();

        // Check if the token retrieval was unsuccessful
        if (!$token) {
            // Set an error message indicating token retrieval failure
            $response['message'] = __('Cant get token from payment agent.', 'qnbpay-woocommerce');

            return $response; // Return the response with an error message
        }

        // Set the authorization header using the retrieved token
        $headers = [
            'Authorization' => 'Bearer ' . $token,
        ];

        // Prepare the parameters for the request
        $params = [
            'currency_code' => get_woocommerce_currency(),
        ];

        // Send a request to the 'commissions' endpoint with the prepared parameters and headers
        $_response = self::doRequest('commissions', $params, $headers);

        // Log the response from the request
        $this->save_log(__METHOD__, $_response);

        // Set the response code based on the request response
        $response['code'] = $_response['code'];

        // Check if the request was successful and the response code is 200
        if ($_response['status'] && $_response['code'] == 200) {
            // Decode the response body to extract data
            $responseBody = json_decode($_response['body'], true);
            $response['body'] = $responseBody;

            // Check if the response contains a status code
            if (isset($responseBody['status_code'])) {
                $response['code'] = $responseBody['status_code'];
                $response['message'] = $responseBody['status_description'];

                // If the status code indicates success, update the response status and body
                if ($responseBody['status_code'] == 100) {
                    $response['status'] = true;
                    $response['body'] = data_get($responseBody, 'data');
                }
            } else {
                // Set an error message if no results were obtained from the payment agent
                $response['message'] = __('Cant get any results from payment agent.', 'qnbpay-woocommerce');
            }
        }

        // Return the final response
        return $response;
    }

    /**
     * Generate Hash Key for QNBPay
     *
     * Creates an encrypted hash key required for certain API requests, based on provided data.
     * @param  array  $dataList  Data to be included in the hash.
     * @return string The generated hash key.
     */
    public function generateHashKey($dataList)
    {
        // Initialize an array to store formatted data
        $genData = [];

        // Iterate over the data list and populate the genData array
        foreach ($dataList as $key => $value) {
            $genData[$key] = $value;
        }

        // Concatenate the data values into a single string separated by '|'
        $genData = implode('|', $genData);

        // Generate a random initialization vector (IV) for encryption
        $iv = substr(sha1(mt_rand()), 0, 16);

        // Hash the app secret to use as a password for encryption
        $password = sha1(data_get($this->qnbOptions, 'app_secret'));

        // Generate a random salt and combine it with the password
        $salt = substr(sha1(mt_rand()), 0, 4);
        $saltWithPassword = hash('sha256', $password . $salt);

        // Encrypt the concatenated data using AES-256-CBC encryption
        $encrypted = openssl_encrypt($genData, 'aes-256-cbc', $saltWithPassword, 0, $iv);

        // Bundle the IV, salt, and encrypted data into a single string
        $msg_encrypted_bundle = $iv . ':' . $salt . ':' . $encrypted;

        // Replace '/' with '__' in the encrypted bundle to avoid URL issues
        $msg_encrypted_bundle = str_replace('/', '__', $msg_encrypted_bundle);

        // Return the final encrypted bundle
        return $msg_encrypted_bundle;
    }

    /**
     * Select Api Host
     *
     * Returns the correct API endpoint URL based on whether test mode is enabled.
     * @param  array  $params  Gateway settings array.
     * @return string The appropriate API host URL (test or production).
     */
    public function apiHost($params)
    {
        $isTestMode = data_get($params, 'testmode', 'no') === 'yes';

        return $isTestMode ? $this->testApiHost : $this->productionApiHost;
    }

    /**
     * Make Request to QNBPay
     *
     * Sends a POST request to the specified QNBPay API endpoint with JSON payload.
     * @param  string  $method  API endpoint method (e.g., 'token', 'getpos').
     * @param  array  $params  Request parameters.
     * @param  array  $headers  Optional request headers.
     * @return array Response array with 'status', 'code', 'body', 'message'.
     */
    public function doRequest($method, $params, $headers = [])
    {
        $response = [
            'status' => false,
            'code' => 0,
            'body' => [],
            'message' => __('Unexpected error occurred.', 'qnbpay-woocommerce'),
        ];

        $defaultHeaders = [
            'Content-Type' => 'application/json',
        ];

        $mergedHeaders = array_merge($defaultHeaders, $headers);

        $remote_request = wp_remote_post(
            $this->apiHost . $method,
            [
                'user-agent' => QNBPAY_BASENAME . QNBPAY_VERSION,
                'timeout' => 25,
                'headers' => $mergedHeaders,
                'body' => json_encode($params),
            ]
        );

        $response['code'] = wp_remote_retrieve_response_code($remote_request);

        if (is_wp_error($remote_request)) {
            $response['message'] = $remote_request->get_error_message();

            return $response;
        }

        if ($response['code'] != 200) {
            $response['message'] = __('Cant connect to payment agent.', 'qnbpay-woocommerce');
        }

        if (!empty(wp_remote_retrieve_body($remote_request))) {
            $response['body'] = wp_remote_retrieve_body($remote_request);
            $response['message'] = __('Success', 'qnbpay-woocommerce');
            $response['status'] = true;
        } else {
            $response['message'] = __('Cant get any results from payment agent.', 'qnbpay-woocommerce');
        }

        $this->save_log(__METHOD__, ['method' => $method, 'params' => $params, 'headers' => $mergedHeaders, 'response' => $response]);

        return $response;
    }

    /**
     * Format Installment Response
     *
     * Transforms the raw commission/installment data from the API into a structured array.
     * @param  array|false  $response  Raw commission response from QNBPay API.
     * @return array Formatted array keyed by card program slug.
     */
    public function formatInstallmentResponse($response)
    {
        $output = [];

        if (!$response) {
            return $output;
        }

        foreach ($response as $taksitSayisi => $kartlar) {
            foreach ($kartlar as $banka) {
                $slug = sanitize_title(data_get($banka, 'card_program'));
                if (!isset($output[$slug])) {
                    $output[$slug] = [
                        'groupName' => $slug,
                        'rates' => [],
                    ];
                }
                $commission = data_get($banka, 'merchant_commission_percentage');
                if ($commission != 'x') {
                    $output[$slug]['rates'][$taksitSayisi] = [
                        'active' => 1,
                        'value' => data_get($banka, 'merchant_commission_percentage'),
                    ];
                }
            }
        }

        return $output;
    }

    /**
     * Format a price with the WooCommerce currency symbol.
     *
     * @since 1.0.0
     * @param  float|string  $price  The price value.
     * @return string Formatted price string.
     */
    public function qnbpay_price($price)
    {
        $price = preg_replace('/\s+/', '', $price);
        $price = $this->qnbpay_number_format($price);
        $price = $price . ' ' . get_woocommerce_currency_symbol();

        return $price;
    }

    /**
     * Format a number to a specific decimal precision without rounding the last digit.
     *
     * @since 1.0.0
     * @param  float|string  $price  The number to format.
     * @param  int  $decimal  Number of decimal places.
     * @return string Formatted number string.
     */
    public function qnbpay_number_format($price, $decimal = 2)
    {
        $_price = floatval($price);
        $_price = number_format($_price, ($decimal + 1), '.', '');
        $_price = substr($_price, 0, -1);

        return $_price;
    }

    /**
     * Get the client's real IP address, considering proxies and Cloudflare.
     *
     * @since 1.0.0
     * @return string The client's IP address.
     */
    public function getClientIp()
    {
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP']) && filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP)) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            if (strpos($_SERVER['HTTP_CLIENT_IP'], ',') !== false) {
                $ips = explode(',', $_SERVER['HTTP_CLIENT_IP']);
                $ip = current($ips);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            } elseif (filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP)) {
                return $_SERVER['HTTP_CLIENT_IP'];
            }
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            if (strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',') !== false) {
                $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $ip = current($ips);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            } elseif (filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP)) {
                return $_SERVER['HTTP_X_FORWARDED_FOR'];
            }
        }

        return (isset($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';

    }

    /**
     * Get a list of test card numbers provided by QNBPay.
     *
     * @since 1.0.0
     * @return array List of test card numbers.
     */
    public function get_test_cards()
    {

        $cards = [
            '4546711234567894',
            '5571135571135575',
            '6501738564461396',
            '4159560047417732',
            '4506349043174632',
        ];

        return $cards;
    }

    /**
     * Generate a unique random number for custom order IDs, ensuring it doesn't already exist.
     *
     * @since 1.0.0
     * @return int A unique random integer.
     */
    private function generateOrderId()
    {
        global $wpdb;
        $randomNumber = random_int(1000000000, 9999999999);
        $tableName = $wpdb->prefix . 'qnbpay_orders_ids';
        $result = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . $tableName . ' WHERE customorderid = %d', $randomNumber));
        if ($result) {
            return $this->generateOrderId();
        }

        return $randomNumber;
    }

    /**
     * Create and store a custom order ID structure (customorderid, invoiceid) for a WooCommerce order.
     *
     * @since 1.0.0
     * @param int $orderId The WooCommerce order ID.
     * @return array An array containing the generated 'invoiceid' and 'customorderid'.
     */
    public function createCustomOrderId($orderId)
    {
        global $wpdb;
        $customOrderId = $this->generateOrderId();

        $tableName = $wpdb->prefix . 'qnbpay_orders_ids';

        $invoiceid = data_get($this->qnbOptions, 'order_prefix') . '_' . $orderId . '_' . $customOrderId;
        $wpdb->insert($tableName, [
            'orderid' => $orderId,
            'customorderid' => $customOrderId,
            'invoiceid' => $invoiceid,
            'createdate' => date('Y-m-d H:i:s'),
        ]);

        return [
            'invoiceid' => $invoiceid,
            'customorderid' => $customOrderId,
        ];
    }

    /**
     * Retrieve the custom order ID details (orderid, customorderid, invoiceid) based on a specific field.
     *
     * @since 1.0.0
     * @param string $by The field to search by ('orderid', 'customorderid', 'invoiceid').
     * @param mixed $value The value to search for.
     * @return array|false The row data as an associative array, or false if not found.
     */
    public function getCustomOrderId($by, $value)
    {
        global $wpdb;
        if (in_array($by, ['orderid', 'customorderid', 'invoiceid'])) {
            $placeholder = ['orderid' => '%d', 'customorderid' => '%d', 'invoiceid' => '%s'];

            $tableName = $wpdb->prefix . 'qnbpay_orders_ids';

            $result = $wpdb->get_row($wpdb->prepare('SELECT orderid, customorderid, invoiceid FROM ' . $tableName . ' WHERE ' . $by . ' = ' . $placeholder[$by] . ' order by createdate desc limit 1', $value), ARRAY_A);
            if ($result) {
                return $result;
            }
        }

        return false;
    }

    /**
     * Save a log entry related to a specific order in the custom database table.
     *
     * @since 1.0.0
     * @param  int  $orderId  Order ID.
     * @param  string  $action  Action being logged (e.g., 'formatOrder', 'qnbReply', 'checkstatus').
     * @param  array  $params  Primary data associated with the action.
     * @param  array  $details  Secondary or detailed data.
     * @return int|false The ID of the inserted log row or false on failure.
     */
    public function saveOrderLog($orderId, $action, $params, $details = [])
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'qnbpay_orders',
            [
                'orderid' => $orderId,
                'createdate' => date_i18n('Y-m-d H:i:s'),
                'action' => $action,
                'data' => json_encode($this->mask_sensitive_data($params)), // Mask sensitive data in $params
                'details' => json_encode($this->mask_sensitive_data($details)), // Mask sensitive data in $details
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );

        return $wpdb->insert_id;
    }

    /**
     * Get or generate the filename for the debug log file.
     *
     * Ensures the filename persists across requests and changes if the plugin version updates.
     * @return string The debug log filename.
     */
    public function debug_file()
    {
        $check = get_option('woocommerce_qnbpay_debugfile');
        $version = get_option('woocommerce_qnbpay_version', '0.0.0');
        if ($check && $version === QNBPAY_VERSION) {
            return $check;
        }
        $filename = wp_generate_uuid4() . '.qnbpay';
        update_option('woocommerce_qnbpay_debugfile', $filename);
        update_option('woocommerce_qnbpay_version', QNBPAY_VERSION);

        return $filename;
    }

    /**
     * Recursively masks sensitive data within an array or object.
     * Masks credit card numbers (keeping first 8 digits), CVV, holder name, API keys/secrets.
     *
     * @since 1.0.0
     * @access private
     * @param mixed $data The data to mask (array or object).
     * @return mixed The data with sensitive information masked.
     */
    private function mask_sensitive_data($data)
    {
        // If data is not an array or object, return it as is
        if (!is_array($data) && !is_object($data)) {
            return $data;
        }

        // Clone object or copy array to avoid modifying the original by reference elsewhere
        $masked_data = is_object($data) ? clone $data : $data;

        // Iterate through the data, using reference to modify in place
        foreach ($masked_data as $key => &$value) {
            // If the value is an array or object, recurse into it
            if (is_array($value) || is_object($value)) {
                $value = $this->mask_sensitive_data($value);
            } elseif (is_string($value) || is_numeric($value)) {
                // Check for sensitive keys (case-insensitive)
                $lower_key = strtolower($key);
                $stringValue = (string) $value;

                // Mask credit card number (keep first 8 digits)
                if ($lower_key === 'qnbpay-card-number' && ctype_digit(preg_replace('/\s+/', '', $stringValue)) && strlen(preg_replace('/\s+/', '', $stringValue)) > 8) { // Check for 'qnbpay-card-number' specifically, remove spaces before checking digits/length
                    $cleaned_card_number = preg_replace('/\s+/', '', $stringValue);
                    $value = substr($cleaned_card_number, 0, 8) . str_repeat('X', strlen($cleaned_card_number) - 8);
                }
                // Mask API keys/secrets/tokens (completely)
                elseif (in_array($lower_key, ['app_secret', 'app_key', 'password', 'token', 'hash_key'])) {
                    $value = 'XXXXXXXX';
                }
            }
        }
        unset($value); // Unset the reference to the last element

        return $masked_data;
    }

    /**
     * Save data to the debug log file if debug mode is enabled.
     *
     * @since 1.0.0
     * @param  string  $type  A string identifying the type or source of the log entry.
     * @param  mixed  $data  The data to log.
     */
    public function save_log($type, $data)
    {
        if ($this->debugMode) {
            // Mask sensitive data before logging
            $masked_data = $this->mask_sensitive_data($data);

            // Prepare log entry components
            $log_data = [
                '[' . date_i18n('Y-m-d H:i:s') . ']',
                '[' . $type . ']',
                '[Server IP: ' . ($_SERVER['SERVER_ADDR'] ?? 'N/A') . ']',
                '[Client IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . ']',
                json_encode($masked_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), // Use masked data
            ];
            if (is_writable(QNBPAY_DIR)) {
                file_put_contents(QNBPAY_DIR . $this->debugFile, PHP_EOL . implode(' ', $log_data) . PHP_EOL, FILE_APPEND);
            }
        }
    }
}
