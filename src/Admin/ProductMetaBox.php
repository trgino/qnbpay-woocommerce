<?php

namespace QNBPay\Admin;

use QNBPay\Plugin;
use QNBPay\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Per-product installment limit meta box.
 *
 * @since 2.0.0
 */
class ProductMetaBox
{
    /** @var Plugin */
    private $plugin;

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register()
    {
        add_action('add_meta_boxes', [$this, 'add']);
        add_action('save_post_product', [$this, 'save']);
    }

    /**
     * Add the meta box when the feature is enabled.
     *
     * @return void
     */
    public function add()
    {
        $settings = $this->plugin->settings();
        if ('yes' !== Arr::get($settings, 'enabled') || 'yes' !== Arr::get($settings, 'limitInstallmentByProduct', 'no')) {
            return;
        }

        add_meta_box(
            'qnbpay_installment_limit',
            __('Installment Limit', 'qnbpay-for-woocommerce'),
            [$this, 'render'],
            'product',
            'side'
        );
    }

    /**
     * Render the meta box.
     *
     * @param \WP_Post $post
     * @return void
     */
    public function render($post)
    {
        wp_nonce_field('qnbpay_installment_limit', 'qnbpay_installment_limit_nonce');
        $limit = (int) get_post_meta($post->ID, '_limitInstallment', true);
        echo '<select name="_limitInstallment">';
        echo '<option value="0">' . esc_html__('Default', 'qnbpay-for-woocommerce') . '</option>';
        for ($i = 1; $i <= 12; $i++) {
            printf(
                '<option value="%1$s"%2$s>%3$s</option>',
                esc_attr((string) $i),
                selected($limit, $i, false),
                /* translators: %s: installment count */
                esc_html(sprintf(__('%s Installment', 'qnbpay-for-woocommerce'), $i))
            );
        }
        echo '</select>';
    }

    /**
     * Save the meta box value.
     *
     * @param int $post_id
     * @return void
     */
    public function save($post_id)
    {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        if (!isset($_POST['qnbpay_installment_limit_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['qnbpay_installment_limit_nonce'])), 'qnbpay_installment_limit')) {
            return;
        }
        if (!current_user_can('edit_product', $post_id)) {
            return;
        }
        if (!isset($_POST['_limitInstallment'])) {
            return;
        }

        try {
            $value = (int) $_POST['_limitInstallment']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            if ($value > 0) {
                update_post_meta($post_id, '_limitInstallment', $value);
            } else {
                delete_post_meta($post_id, '_limitInstallment');
            }
        } catch (\Throwable $e) {
            $this->plugin->logger()->exception('product_meta_save', $e);
        }
    }
}
