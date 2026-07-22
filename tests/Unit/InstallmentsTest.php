<?php

namespace QNBPay\Tests\Unit;

use QNBPay\Api\Client;
use QNBPay\Gateway\Installments;
use QNBPay\Support\Logger;
use QNBPay\Tests\TestCase;

/**
 * @covers \QNBPay\Gateway\Installments
 */
class InstallmentsTest extends TestCase
{
    private function installments(array $settings = []): Installments
    {
        $client = new Client($settings, new Logger(false));

        return new Installments($settings, $client);
    }

    public function test_max_installment_returns_one_when_disabled(): void
    {
        $service = $this->installments(['installment' => 'no', 'limitInstallment' => '12']);
        $this->assertSame(1, $service->max_installment('cart', null));
    }

    public function test_max_installment_returns_configured_global_limit(): void
    {
        $service = $this->installments(['installment' => 'yes', 'limitInstallment' => '6']);
        $this->assertSame(6, $service->max_installment('cart', null));
    }

    public function test_render_generates_radio_options(): void
    {
        $service = $this->installments(['installment' => 'yes']);

        $data = [
            ['installments_number' => 1],
            ['installments_number' => 3],
            ['installments_number' => 6],
        ];

        $html = $service->render($data, 6, 'cart');

        $this->assertStringContainsString('qnbpay-installments', $html);
        $this->assertStringContainsString('value="1"', $html);
        $this->assertStringContainsString('value="3"', $html);
        $this->assertStringContainsString('value="6"', $html);
    }

    public function test_render_excludes_installments_above_max(): void
    {
        $service = $this->installments(['installment' => 'yes']);

        $data = [
            ['installments_number' => 1],
            ['installments_number' => 3],
            ['installments_number' => 12],
        ];

        $html = $service->render($data, 3, 'cart');

        $this->assertStringContainsString('value="1"', $html);
        $this->assertStringContainsString('value="3"', $html);
        $this->assertStringNotContainsString('value="12"', $html);
    }
}
