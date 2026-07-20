<?php

namespace QNBPay\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Secure logging with PCI-aware masking.
 *
 * Uses the WooCommerce logger (WC_Logger) which stores files under a protected
 * uploads/wc-logs directory instead of the previous web-accessible file inside
 * the plugin folder. All payloads are masked before being written.
 *
 * @since 2.0.0
 */
final class Logger
{
    const SOURCE = 'qnbpay';

    /** @var bool Whether debug logging is enabled. */
    private $enabled;

    /** @var \WC_Logger_Interface|null */
    private $wcLogger = null;

    /**
     * @param bool $enabled Whether debug logging is enabled.
     */
    public function __construct($enabled = false)
    {
        $this->enabled = (bool) $enabled;
    }

    /**
     * Log a masked debug entry.
     *
     * @param string $type Context / source label.
     * @param mixed  $data Arbitrary payload (array, object, scalar).
     * @return void
     */
    public function debug($type, $data)
    {
        if (!$this->enabled) {
            return;
        }

        try {
            $logger = $this->logger();
            if (!$logger) {
                return;
            }

            $payload = wp_json_encode(
                self::mask($data),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

            $logger->debug(
                '[' . (string) $type . '] ' . $payload,
                ['source' => self::SOURCE]
            );
        } catch (\Throwable $e) {
            // Logging must never break the payment flow. As a last resort fall
            // back to PHP's error log (we cannot re-enter the WC logger here).
            error_log('QNBPay logger failure: ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }

    /**
     * Log an error entry (always written, regardless of debug mode).
     *
     * @param string $type
     * @param mixed  $data
     * @return void
     */
    public function error($type, $data = '')
    {
        try {
            $logger = $this->logger();
            if (!$logger) {
                return;
            }
            $payload = is_scalar($data) ? (string) $data : wp_json_encode(self::mask($data));
            $logger->error('[' . (string) $type . '] ' . $payload, ['source' => self::SOURCE]);
        } catch (\Throwable $e) {
            error_log('QNBPay logger failure: ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }

    /**
     * Log a caught exception with its message and origin (file:line).
     *
     * Central helper so no catch block ever swallows an error silently.
     *
     * @param string     $context Where it happened (e.g. __METHOD__).
     * @param \Throwable $e
     * @return void
     */
    public function exception($context, \Throwable $e)
    {
        $this->error($context, sprintf(
            '%s in %s:%d',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));
    }

    /**
     * Whether debug logging is currently enabled.
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * Resolve the WooCommerce logger instance lazily.
     *
     * @return \WC_Logger_Interface|null
     */
    private function logger()
    {
        if (null === $this->wcLogger && function_exists('wc_get_logger')) {
            $this->wcLogger = wc_get_logger();
        }

        return $this->wcLogger;
    }

    /**
     * Recursively mask sensitive data (PAN, CVV, secrets, tokens).
     *
     * @param mixed $data
     * @return mixed
     */
    public static function mask($data)
    {
        if (!is_array($data) && !is_object($data)) {
            return $data;
        }

        $masked = is_object($data) ? clone $data : $data;

        foreach ($masked as $key => &$value) {
            if (is_array($value) || is_object($value)) {
                $value = self::mask($value);
                continue;
            }

            if (!is_string($value) && !is_numeric($value)) {
                continue;
            }

            $lowerKey = strtolower((string) $key);
            $stringValue = (string) $value;

            // Fully redact secrets, tokens, CVV and hash material.
            if (in_array($lowerKey, ['app_secret', 'app_key', 'password', 'token', 'hash_key', 'cvv', 'cvc', 'qnbpay-card-cvc'], true)) {
                $value = '***';
                continue;
            }

            // Mask card numbers, keeping only the first 6 digits (BIN).
            if (in_array($lowerKey, ['qnbpay-card-number', 'cc_no', 'credit_card', 'card_number'], true)) {
                $digits = preg_replace('/\D+/', '', $stringValue);
                if (is_string($digits) && strlen($digits) > 6) {
                    $value = substr($digits, 0, 6) . str_repeat('*', strlen($digits) - 6);
                }
            }
        }
        unset($value);

        return $masked;
    }
}
