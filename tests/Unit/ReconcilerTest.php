<?php

namespace QNBPay\Tests\Unit;

use QNBPay\Cron\Reconciler;
use QNBPay\Plugin;
use QNBPay\Tests\TestCase;

/**
 * @covers \QNBPay\Cron\Reconciler
 */
class ReconcilerTest extends TestCase
{
    public function test_reconciler_constants(): void
    {
        $this->assertSame('qnbpay_reconcile_pending', Reconciler::HOOK);
        $this->assertSame('qnbpay', Reconciler::GROUP);
        $this->assertSame(600, Reconciler::INTERVAL);
    }

    public function test_reconciler_instantiation(): void
    {
        $plugin = Plugin::instance();
        $reconciler = new Reconciler($plugin);

        $this->assertInstanceOf(Reconciler::class, $reconciler);
    }
}
