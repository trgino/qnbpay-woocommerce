<?php

namespace QNBPay\Api;

use QNBPay\Support\Arr;
use QNBPay\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * QNBPay REST API client.
 *
 * Encapsulates authentication, request dispatch, hash generation/verification
 * and the individual API endpoints (token, getpos, commissions, checkstatus,
 * refund). Every network call is wrapped in try/catch and always returns a
 * predictable response array.
 *
 * @since 2.0.0
 */
class Client
{
    const PRODUCTION_HOST = 'https://portal.qnbpay.com.tr/ccpayment/api/';
    const TEST_HOST = 'https://test.qnbpay.com.tr/ccpayment/api/';
    const TOKEN_TRANSIENT = 'qnbpay_api_token';

    /** @var array Gateway settings. */
    private $settings;

    /** @var Logger */
    private $logger;

    /**
     * @param array  $settings Gateway settings array.
     * @param Logger $logger   Logger instance.
     */
    public function __construct(array $settings, Logger $logger)
    {
        $this->settings = $settings;
        $this->logger = $logger;
    }

    /**
     * Resolve the API host based on test mode.
     *
     * @return string
     */
    public function host()
    {
        $test = Arr::get($this->settings, 'testmode', 'no') === 'yes';

        return $test ? self::TEST_HOST : self::PRODUCTION_HOST;
    }

    /**
     * Build the default (empty) response envelope.
     *
     * @return array
     */
    private function envelope()
    {
        return [
            'status' => false,
            'code' => 0,
            'body' => [],
            'message' => __('Unexpected error occurred.', 'qnbpay-for-woocommerce'),
        ];
    }

    /**
     * Perform a JSON POST request to a QNBPay endpoint.
     *
     * @param string $method  Endpoint (e.g. 'token', 'getpos', 'checkstatus').
     * @param array  $params  Request body.
     * @param array  $headers Extra headers.
     * @return array {status, code, body, message}
     */
    public function request($method, array $params, array $headers = [])
    {
        $response = $this->envelope();

        try {
            $merged_headers = array_merge(['Content-Type' => 'application/json'], $headers);

            $remote = wp_remote_post(
                $this->host() . $method,
                [
                    'user-agent' => 'QNBPay-WooCommerce/' . QNBPAY_VERSION,
                    'timeout' => 30,
                    'headers' => $merged_headers,
                    'body' => wp_json_encode($params),
                ]
            );

            if (is_wp_error($remote)) {
                $response['message'] = $remote->get_error_message();
                $this->logger->error('request:' . $method, $response['message']);

                return $response;
            }

            $response['code'] = (int) wp_remote_retrieve_response_code($remote);
            $body = wp_remote_retrieve_body($remote);

            if ('' === $body || null === $body) {
                $response['message'] = __('Cant get any results from payment agent.', 'qnbpay-for-woocommerce');

                return $response;
            }

            $response['body'] = $body;
            $response['status'] = true;
            $response['message'] = __('Success', 'qnbpay-for-woocommerce');

            if (200 !== $response['code']) {
                $response['message'] = __('Cant connect to payment agent.', 'qnbpay-for-woocommerce');
            }
        } catch (\Throwable $e) {
            $response['message'] = $e->getMessage();
            $this->logger->exception('request:' . $method, $e);

            return $response;
        }

        $this->logger->debug('request:' . $method, [
            'params' => $params,
            'headers' => $merged_headers,
            'response' => $response,
        ]);

        return $response;
    }

    /**
     * Retrieve (and cache) an API token.
     *
     * @return string|false
     */
    public function token()
    {
        $cached = get_transient(self::TOKEN_TRANSIENT);
        if (is_string($cached) && '' !== $cached) {
            return $cached;
        }

        $response = $this->request('token', [
            'app_id' => Arr::str(Arr::get($this->settings, 'app_key')),
            'app_secret' => Arr::str(Arr::get($this->settings, 'app_secret')),
        ]);

        if ($response['status'] && 200 === $response['code']) {
            $decoded = json_decode($response['body'], true);
            $token = Arr::get($decoded, 'data.token');
            if (is_string($token) && '' !== $token) {
                // Cache slightly under the documented lifetime to be safe.
                set_transient(self::TOKEN_TRANSIENT, $token, 50 * MINUTE_IN_SECONDS);

                return $token;
            }
            $this->logger->error('token', 'Token payload missing data.token parameter: ' . wp_json_encode($decoded));
        } else {
            $this->logger->error('token', 'Failed to retrieve token: ' . $response['message']);
        }

        return false;
    }

    /**
     * Authorization header helper.
     *
     * @return array|false
     */
    private function auth_headers()
    {
        $token = $this->token();
        if (!$token) {
            return false;
        }

        return ['Authorization' => 'Bearer ' . $token];
    }

    /**
     * Request BIN / installment (getpos) details.
     *
     * @param array $params credit_card, amount, currency_code
     * @return array
     */
    public function get_pos(array $params)
    {
        $response = $this->envelope();
        $response['code'] = '-1';

        $headers = $this->auth_headers();
        if (!$headers) {
            $response['message'] = __('Cant get token from payment agent.', 'qnbpay-for-woocommerce');
            $this->logger->error('get_pos', 'Failed to obtain API token for BIN request');

            return $response;
        }

        $post = [
            'credit_card' => Arr::str(Arr::get($params, 'credit_card')),
            'amount' => Arr::get($params, 'amount', '100'),
            'currency_code' => Arr::get($params, 'currency_code', get_woocommerce_currency()),
            'merchant_key' => Arr::str(Arr::get($this->settings, 'merchant_key')),
            'is_comission_from_user' => 0,
        ];

        return $this->normalize($this->request('getpos', $post, $headers), $response);
    }

    /**
     * Fetch merchant commission rates.
     *
     * @return array
     */
    public function get_commissions()
    {
        $response = $this->envelope();
        $response['code'] = '-1';

        $headers = $this->auth_headers();
        if (!$headers) {
            $response['message'] = __('Cant get token from payment agent.', 'qnbpay-for-woocommerce');
            $this->logger->error('get_commissions', 'Failed to obtain API token for commissions request');

            return $response;
        }

        $post = ['currency_code' => get_woocommerce_currency()];

        return $this->normalize($this->request('commissions', $post, $headers), $response);
    }

    /**
     * Query the transaction status of an invoice (checkstatus).
     *
     * @param string $invoice_id
     * @return array {status, code, body(decoded array), message, raw}
     */
    public function check_status($invoice_id)
    {
        $result = [
            'status' => false,
            'code' => 0,
            'body' => [],
            'message' => '',
            'raw' => null,
        ];

        $headers = $this->auth_headers();
        if (!$headers) {
            $result['message'] = __('Cant get token from payment agent.', 'qnbpay-for-woocommerce');
            $this->logger->error('check_status', 'Failed to obtain API token for invoice: ' . $invoice_id);

            return $result;
        }

        $params = [
            'invoice_id' => $invoice_id,
            'merchant_key' => Arr::str(Arr::get($this->settings, 'merchant_key')),
            'hash_key' => $this->hash([$invoice_id, Arr::str(Arr::get($this->settings, 'merchant_key'))]),
            'include_pending_status' => true,
        ];

        $response = $this->request('checkstatus', $params, $headers);
        $result['status'] = (bool) $response['status'];
        $result['code'] = $response['code'];
        $result['message'] = $response['message'];

        if ($response['status']) {
            $decoded = json_decode($response['body'], true);
            $result['raw'] = $decoded;
            // The API may return either an object or a single-element array.
            if (isset($decoded[0]) && is_array($decoded[0])) {
                $decoded = $decoded[0];
            }
            $result['body'] = is_array($decoded) ? $decoded : [];
        }

        return $result;
    }

    /**
     * Refund a (fully or partially) paid transaction.
     *
     * @param string     $invoice_id
     * @param float|null $amount Amount to refund; empty/null refunds the full amount.
     * @return array {status, code, message, body}
     */
    public function refund($invoice_id, $amount = null)
    {
        $result = [
            'status' => false,
            'code' => 0,
            'message' => '',
            'body' => [],
        ];

        $headers = $this->auth_headers();
        if (!$headers) {
            $result['message'] = __('Cant get token from payment agent.', 'qnbpay-for-woocommerce');
            $this->logger->error('refund', 'Failed to obtain API token for refund invoice: ' . $invoice_id);

            return $result;
        }

        $merchant_key = Arr::str(Arr::get($this->settings, 'merchant_key'));
        $amount_str = (null === $amount || '' === $amount) ? '' : (string) $amount;

        $params = [
            'invoice_id' => $invoice_id,
            'merchant_key' => $merchant_key,
            'amount' => $amount_str,
            // Refund hash order per docs: total|invoice_id|merchant_key
            'hash_key' => $this->hash([$amount_str, $invoice_id, $merchant_key]),
        ];

        $response = $this->request('refund', $params, $headers);
        $result['code'] = $response['code'];
        $result['message'] = $response['message'];

        if ($response['status']) {
            $decoded = json_decode($response['body'], true);
            $result['body'] = is_array($decoded) ? $decoded : [];
            $status_code = (int) Arr::get($result['body'], 'status_code', 0);
            if (100 === $status_code) {
                $result['status'] = true;
            } else {
                $result['message'] = Arr::str(Arr::get($result['body'], 'status_description', $result['message']));
            }
        }

        return $result;
    }

    /**
     * Normalize a getpos/commissions response into the plugin envelope.
     *
     * @param array $api      Raw request() result.
     * @param array $response Envelope to populate.
     * @return array
     */
    private function normalize(array $api, array $response)
    {
        $response['code'] = $api['code'];

        if ($api['status'] && 200 === (int) $api['code']) {
            $decoded = json_decode($api['body'], true);
            $response['body'] = $decoded;

            if (isset($decoded['status_code'])) {
                $response['code'] = $decoded['status_code'];
                $response['message'] = Arr::str(Arr::get($decoded, 'status_description'));
                if (100 === (int) $decoded['status_code']) {
                    $response['status'] = true;
                    $response['body'] = Arr::get($decoded, 'data', []);
                }
            } else {
                $response['message'] = __('Cant get any results from payment agent.', 'qnbpay-for-woocommerce');
            }
        }

        return $response;
    }

    /**
     * Generate an encrypted QNBPay hash key from ordered values.
     *
     * The values are joined with "|" in the given order, then AES-256-CBC
     * encrypted with a key derived from the app secret (per QNBPay docs).
     *
     * @param array $values Ordered list of values.
     * @return string
     */
    public function hash(array $values)
    {
        $data = implode('|', array_map([Arr::class, 'str'], $values));

        $iv = substr(sha1((string) wp_rand()), 0, 16);
        $salt = substr(sha1((string) wp_rand()), 0, 4);
        $password = sha1(Arr::str(Arr::get($this->settings, 'app_secret')));
        $salt_with_password = hash('sha256', $password . $salt);

        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $salt_with_password, 0, $iv);

        $bundle = $iv . ':' . $salt . ':' . $encrypted;

        return str_replace('/', '__', $bundle);
    }

    /**
     * Verify and decrypt a QNBPay hash key.
     *
     * @param string $hash_key
     * @return array|false {status, total, invoiceId, orderId, currencyCode} or false.
     */
    public function verify_hash($hash_key)
    {
        if (empty($hash_key) || !is_string($hash_key)) {
            return false;
        }

        try {
            $hash_key = str_replace('__', '/', $hash_key);
            $components = explode(':', $hash_key);
            if (count($components) < 3) {
                return false;
            }

            $iv = $components[0];
            $salt = $components[1];
            $encrypted = $components[2];

            $password = sha1(Arr::str(Arr::get($this->settings, 'app_secret')));
            $salt_with_password = hash('sha256', $password . $salt);

            $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $salt_with_password, 0, $iv);

            if (!is_string($decrypted) || strpos($decrypted, '|') === false) {
                return false;
            }

            $parts = explode('|', $decrypted);

            return [
                'status' => isset($parts[0]) ? $parts[0] : '',
                'total' => isset($parts[1]) ? $parts[1] : '',
                'invoiceId' => isset($parts[2]) ? $parts[2] : '',
                'orderId' => isset($parts[3]) ? $parts[3] : '',
                'currencyCode' => isset($parts[4]) ? $parts[4] : '',
            ];
        } catch (\Throwable $e) {
            $this->logger->exception('verify_hash', $e);

            return false;
        }
    }

    /**
     * Transform commission data into a keyed installment structure.
     *
     * @param array|false $response
     * @return array
     */
    public function format_installments($response)
    {
        $output = [];
        if (!$response || !is_array($response)) {
            return $output;
        }

        foreach ($response as $count => $cards) {
            if (!is_array($cards)) {
                continue;
            }
            foreach ($cards as $card) {
                $slug = sanitize_title(Arr::str(Arr::get($card, 'card_program')));
                if ('' === $slug) {
                    continue;
                }
                if (!isset($output[$slug])) {
                    $output[$slug] = ['groupName' => $slug, 'rates' => []];
                }
                $commission = Arr::get($card, 'merchant_commission_percentage');
                if ('x' !== $commission) {
                    $output[$slug]['rates'][$count] = ['active' => 1, 'value' => $commission];
                }
            }
        }

        return $output;
    }
}
