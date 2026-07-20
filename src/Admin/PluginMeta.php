<?php

namespace QNBPay\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin list-table row meta and action links.
 *
 * @since 2.0.0
 */
class PluginMeta
{
    /**
     * Register hooks.
     *
     * @return void
     */
    public function register()
    {
        add_filter('plugin_row_meta', [$this, 'row_meta'], 10, 2);
        add_filter('plugin_action_links_' . QNBPAY_BASENAME, [$this, 'action_links']);
    }

    /**
     * Add row meta links.
     *
     * @param array  $links
     * @param string $file
     * @return array
     */
    public function row_meta($links, $file)
    {
        if (QNBPAY_BASENAME !== $file) {
            return $links;
        }

        $links[] = '<a href="https://qnbpay.com.tr/" target="_blank" rel="noopener noreferrer">' . esc_html__('Website', 'qnbpay-for-woocommerce') . '</a>';
        $links[] = '<a href="https://qnbpay.com.tr/contact" target="_blank" rel="noopener noreferrer">' . esc_html__('Report Issue', 'qnbpay-for-woocommerce') . '</a>';

        return $links;
    }

    /**
     * Add a "Settings" action link.
     *
     * @param array $links
     * @return array
     */
    public function action_links($links)
    {
        $settings = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=qnbpay')) . '">' . esc_html__('Settings', 'qnbpay-for-woocommerce') . '</a>';
        array_unshift($links, $settings);

        return $links;
    }
}
