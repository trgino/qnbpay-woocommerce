<?php

namespace QNBPay\Cron;

use QNBPay\Gateway\Gateway;
use QNBPay\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Background reconciliation of pending QNBPay orders via Action Scheduler.
 *
 * Safety net for missed webhooks / interrupted return redirects. Uses Action
 * Scheduler (bundled with WooCommerce) instead of WP-Cron so it runs reliably
 * regardless of site traffic and is observable under WooCommerce > Status >
 * Scheduled Actions.
 *
 * Runs every 10 minutes; the look-back window defaults to 90 minutes (~9x the
 * interval) so an order gets several chances even if a few runs are missed.
 * Both values are filterable.
 *
 * @since 2.0.0
 */
class Reconciler
{
    const HOOK = 'qnbpay_reconcile_pending';
    const GROUP = 'qnbpay';
    const INTERVAL = 600; // 10 minutes, in seconds.

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
        add_action(self::HOOK, [$this, 'run']);
        add_action('init', [self::class, 'ensure_scheduled'], 20);
    }

    /**
     * Ensure the recurring Action Scheduler action exists.
     *
     * @return void
     */
    public static function ensure_scheduled()
    {
        if (!function_exists('as_schedule_recurring_action')) {
            return;
        }

        $already = function_exists('as_has_scheduled_action')
            ? as_has_scheduled_action(self::HOOK, [], self::GROUP)
            : (function_exists('as_next_scheduled_action') && false !== as_next_scheduled_action(self::HOOK, [], self::GROUP));

        if ($already) {
            return;
        }

        as_schedule_recurring_action(time() + MINUTE_IN_SECONDS, self::INTERVAL, self::HOOK, [], self::GROUP);
    }

    /**
     * Remove all scheduled actions (deactivation / uninstall).
     *
     * @return void
     */
    public static function unschedule()
    {
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(self::HOOK, [], self::GROUP);
        }
    }

    /**
     * Reconcile pending orders.
     *
     * @return void
     */
    public function run()
    {
        try {
            $window = (int) apply_filters('qnbpay_reconcile_window_minutes', 90);
            $limit = (int) apply_filters('qnbpay_reconcile_batch_size', 50);
            if ($window < 1) {
                $window = 90;
            }

            if (!function_exists('wc_get_orders')) {
                return;
            }

            $orders = wc_get_orders([
                'limit' => $limit,
                'payment_method' => 'qnbpay',
                'status' => ['pending'],
                'date_created' => '>' . (time() - ($window * MINUTE_IN_SECONDS)),
                'orderby' => 'date',
                'order' => 'ASC',
                'return' => 'objects',
            ]);

            if (empty($orders)) {
                return;
            }

            $gateway = new Gateway();
            $store = $this->plugin->orders();

            foreach ($orders as $order) {
                if (!$order instanceof \WC_Order || $order->is_paid()) {
                    continue;
                }
                $invoice_id = $store->get_invoice_id($order);
                if ('' === $invoice_id) {
                    continue;
                }
                // Do NOT mark failed from the reconciler: a still-pending payment
                // must be left alone and retried on the next run.
                $gateway->finalize_from_checkstatus($order, $invoice_id, 'cron', false);
            }
        } catch (\Throwable $e) {
            $this->plugin->logger()->exception('reconcile', $e);
        }
    }
}
