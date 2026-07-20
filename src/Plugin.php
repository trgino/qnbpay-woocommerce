<?php

namespace QNBPay;

use QNBPay\Admin\PluginMeta;
use QNBPay\Admin\ProductMetaBox;
use QNBPay\Admin\TransactionHistory;
use QNBPay\Ajax\Ajax;
use QNBPay\Api\Client;
use QNBPay\Blocks\BlocksSupport;
use QNBPay\Frontend\Endpoints;
use QNBPay\Gateway\Gateway;
use QNBPay\Gateway\OrderStore;
use QNBPay\Install\Installer;
use QNBPay\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Central plugin bootstrap and service container.
 *
 * @since 2.0.0
 */
final class Plugin
{
    /** @var Plugin|null */
    private static $instance = null;

    /** @var array Gateway settings. */
    private $settings = [];

    /** @var Logger|null */
    private $logger = null;

    /** @var Client|null */
    private $client = null;

    /** @var OrderStore|null */
    private $orders = null;

    /** @var bool */
    private $booted = false;

    /**
     * Singleton accessor.
     *
     * @return Plugin
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    /**
     * Boot the plugin.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        // Declare compatibility very early (must run before WooCommerce init).
        add_action('before_woocommerce_init', [$this, 'declare_compatibility']);

        if (!$this->requirements_met()) {
            add_action('admin_notices', [$this, 'requirements_notice']);

            return;
        }

        $this->settings = get_option('woocommerce_qnbpay_settings', []);
        if (!is_array($this->settings)) {
            $this->settings = [];
        }

        try {
            $this->register_modules();
        } catch (\Throwable $e) {
            // Never let an initialisation error take down the whole site.
            $this->logger()->exception('boot', $e);
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>'
                    . esc_html__('QNBPay Payment Gateway failed to initialise. Check WooCommerce > Status > Logs.', 'qnbpay-for-woocommerce')
                    . '</p></div>';
            });
        }
    }

    /**
     * Register all runtime hooks and modules.
     *
     * @return void
     */
    private function register_modules()
    {
        // Translations are loaded automatically by WordPress (just-in-time) since
        // 4.6 because the text domain matches the plugin slug.

        // Schema upgrade guard + rewrite rules.
        add_action('init', [Installer::class, 'maybe_upgrade']);
        add_action('init', [$this, 'maybe_flush_rewrite'], PHP_INT_MAX);

        // Register the gateway with WooCommerce.
        add_filter('woocommerce_payment_gateways', [$this, 'register_gateway']);

        // Front-end endpoints (3D form + result handler).
        (new Endpoints($this))->register();

        // AJAX handlers.
        (new Ajax($this))->register();

        // Background reconciliation of pending orders (missed-webhook safety net).
        (new \QNBPay\Cron\Reconciler($this))->register();

        // Admin modules.
        if (is_admin()) {
            (new ProductMetaBox($this))->register();
            (new TransactionHistory())->register();
            (new PluginMeta())->register();
        }

        // Checkout Blocks integration.
        add_action('woocommerce_blocks_payment_method_type_registration', [$this, 'register_blocks_support']);
    }

    /**
     * Declare HPOS and Cart/Checkout Blocks compatibility.
     *
     * @return void
     */
    public function declare_compatibility()
    {
        if (!class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            return;
        }
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', QNBPAY_FILE, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', QNBPAY_FILE, true);
    }

    /**
     * Whether the environment meets minimum requirements.
     *
     * @return bool
     */
    public function requirements_met()
    {
        if (version_compare(PHP_VERSION, QNBPAY_MIN_PHP, '<')) {
            return false;
        }
        if (!class_exists('WooCommerce') && !function_exists('WC')) {
            return false;
        }
        if (defined('WC_VERSION') && version_compare(WC_VERSION, QNBPAY_MIN_WC, '<')) {
            return false;
        }

        return true;
    }

    /**
     * Admin notice shown when requirements are not met.
     *
     * @return void
     */
    public function requirements_notice()
    {
        $message = sprintf(
            /* translators: 1: min PHP, 2: min WooCommerce */
            esc_html__('QNBPay Payment Gateway requires WooCommerce %2$s+ and PHP %1$s+ to run.', 'qnbpay-for-woocommerce'),
            esc_html(QNBPAY_MIN_PHP),
            esc_html(QNBPAY_MIN_WC)
        );
        echo '<div class="notice notice-error"><p>' . $message . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
    }

    /**
     * Flush rewrite rules once, right after activation.
     *
     * @return void
     */
    public function maybe_flush_rewrite()
    {
        if (get_transient('qnbpay_flush_rewrite')) {
            delete_transient('qnbpay_flush_rewrite');
            flush_rewrite_rules(false);
        }
    }

    /**
     * Register the gateway class with WooCommerce.
     *
     * @param array $gateways
     * @return array
     */
    public function register_gateway($gateways)
    {
        $gateways[] = Gateway::class;

        return $gateways;
    }

    /**
     * Register the Checkout Blocks payment method integration.
     *
     * @param \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry
     * @return void
     */
    public function register_blocks_support($registry)
    {
        if (class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            $registry->register(new BlocksSupport());
        }
    }

    // ---------------------------------------------------------------------
    // Service accessors
    // ---------------------------------------------------------------------

    /**
     * @return array
     */
    public function settings()
    {
        return $this->settings;
    }

    /**
     * @return Logger
     */
    public function logger()
    {
        if (null === $this->logger) {
            $enabled = isset($this->settings['debugMode']) && 'yes' === $this->settings['debugMode'];
            $this->logger = new Logger($enabled);
        }

        return $this->logger;
    }

    /**
     * @return Client
     */
    public function client()
    {
        if (null === $this->client) {
            $this->client = new Client($this->settings, $this->logger());
        }

        return $this->client;
    }

    /**
     * @return OrderStore
     */
    public function orders()
    {
        if (null === $this->orders) {
            $this->orders = new OrderStore($this->logger());
        }

        return $this->orders;
    }
}
