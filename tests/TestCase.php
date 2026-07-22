<?php

namespace QNBPay\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case: boots Brain\Monkey and stubs the handful of WordPress /
 * WooCommerce functions the units under test touch. No WordPress install.
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // i18n / escaping — return the string unchanged.
        Functions\when('__')->returnArg(1);
        Functions\when('esc_html__')->returnArg(1);
        Functions\when('esc_attr__')->returnArg(1);
        Functions\when('esc_attr')->alias(static function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        });
        Functions\when('esc_html')->alias(static function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        });
        Functions\when('esc_url')->returnArg(1);
        Functions\when('sanitize_title')->alias(static function ($title) {
            return strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', (string) $title), '-'));
        });
        Functions\when('wp_json_encode')->alias(static function ($data) {
            return json_encode($data);
        });

        // Randomness / currency helpers used by the API client and formatter.
        Functions\when('wp_rand')->alias(static function () {
            return mt_rand();
        });
        Functions\when('get_woocommerce_currency')->justReturn('TRY');
        Functions\when('get_woocommerce_currency_symbol')->justReturn('TL');
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }
}
