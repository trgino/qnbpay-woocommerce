<?php

namespace QNBPay\Tests\Integration;

use QNBPay\Gateway\Gateway;

/**
 * Verifies the gateway is registered with WooCommerce.
 *
 * @group integration
 */
class GatewayRegistrationTest extends \WP_UnitTestCase
{
    public function test_gateway_is_registered_with_woocommerce(): void
    {
        $gateways = WC()->payment_gateways()->payment_gateways();

        $this->assertArrayHasKey('qnbpay', $gateways);
        $this->assertInstanceOf(Gateway::class, $gateways['qnbpay']);
    }

    public function test_gateway_identity_and_refund_support(): void
    {
        $gateway = new Gateway();

        $this->assertSame('qnbpay', $gateway->id);
        $this->assertTrue($gateway->supports('products'));
        $this->assertTrue($gateway->supports('refunds'));
    }
}
