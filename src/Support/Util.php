<?php

namespace QNBPay\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Stateless utility helpers (formatting, client IP, test cards).
 *
 * @since 2.0.0
 */
final class Util
{
    /**
     * Format a number to a fixed decimal precision, truncating (not rounding)
     * the final digit — matching the previous behaviour expected by the API.
     *
     * @param float|string|null $price
     * @param int               $decimal
     * @return string
     */
    public static function number_format($price, $decimal = 2)
    {
        $value = is_numeric($price) ? (float) $price : 0.0;
        $formatted = number_format($value, $decimal + 1, '.', '');

        return substr($formatted, 0, -1);
    }

    /**
     * Format a price with the store currency symbol.
     *
     * @param float|string|null $price
     * @return string
     */
    public static function price($price)
    {
        $clean = preg_replace('/\s+/', '', Arr::str($price));

        return self::number_format($clean) . ' ' . get_woocommerce_currency_symbol();
    }

    /**
     * Resolve the client's real IP address, considering proxies and Cloudflare.
     *
     * @return string
     */
    public static function client_ip()
    {
        // Prefer WooCommerce's own geolocation resolver, which already handles
        // Cloudflare, X-Forwarded-For and other proxy headers.
        if (class_exists('WC_Geolocation')) {
            $ip = \WC_Geolocation::get_ip_address();
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        $remote = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '127.0.0.1';
    }

    /**
     * QNBPay test card numbers.
     *
     * @return string[]
     */
    public static function test_cards()
    {
        return [
            '4546711234567894',
            '5571135571135575',
            '6501738564461396',
            '4159560047417732',
            '4506349043174632',
        ];
    }
}
