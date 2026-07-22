<?php

namespace QNBPay\Tests\Unit;

use QNBPay\Api\Client;
use QNBPay\Support\Logger;
use QNBPay\Tests\TestCase;

/**
 * @covers \QNBPay\Api\Client
 */
class ClientTest extends TestCase
{
    public function test_host_resolution_for_test_and_production_mode(): void
    {
        $testClient = new Client(['testmode' => 'yes'], new Logger(false));
        $this->assertSame(Client::TEST_HOST, $testClient->host());

        $prodClient = new Client(['testmode' => 'no'], new Logger(false));
        $this->assertSame(Client::PRODUCTION_HOST, $prodClient->host());
    }

    public function test_format_installments_groups_card_programs(): void
    {
        $client = new Client([], new Logger(false));

        $rawResponse = [
            '1' => [
                ['card_program' => 'Bonus', 'merchant_commission_percentage' => '0'],
                ['card_program' => 'World', 'merchant_commission_percentage' => '1.5'],
            ],
            '3' => [
                ['card_program' => 'Bonus', 'merchant_commission_percentage' => '2.5'],
                ['card_program' => 'World', 'merchant_commission_percentage' => 'x'],
            ],
        ];

        $formatted = $client->format_installments($rawResponse);

        $this->assertArrayHasKey('bonus', $formatted);
        $this->assertArrayHasKey('world', $formatted);

        $this->assertSame(1, $formatted['bonus']['rates'][1]['active']);
        $this->assertSame('0', $formatted['bonus']['rates'][1]['value']);
        $this->assertSame('2.5', $formatted['bonus']['rates'][3]['value']);

        $this->assertArrayNotHasKey(3, $formatted['world']['rates'], 'Rate with "x" commission should be excluded.');
    }

    public function test_format_installments_handles_invalid_input(): void
    {
        $client = new Client([], new Logger(false));

        $this->assertSame([], $client->format_installments(false));
        $this->assertSame([], $client->format_installments(null));
        $this->assertSame([], $client->format_installments([]));
    }
}
