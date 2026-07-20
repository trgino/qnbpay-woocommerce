<?php

namespace QNBPay\Tests\Unit;

use QNBPay\Api\Client;
use QNBPay\Support\Logger;
use QNBPay\Tests\TestCase;

/**
 * Verifies the QNBPay hash generation / verification round-trip
 * (AES-256-CBC, per the QNBPay documentation).
 *
 * @covers \QNBPay\Api\Client
 */
class HashTest extends TestCase
{
    private function client(): Client
    {
        return new Client(['app_secret' => 'super-secret-app-key'], new Logger(false));
    }

    public function test_hash_and_verify_round_trip(): void
    {
        $client = $this->client();

        // Order mirrors verify_hash output: status|total|invoiceId|orderId|currencyCode
        $hash = $client->hash(['1', '20.00', 'INV-123', '9001', 'TRY']);

        $this->assertIsString($hash);
        $this->assertStringNotContainsString('/', $hash, 'Slashes must be escaped as "__".');

        $decoded = $client->verify_hash($hash);

        $this->assertIsArray($decoded);
        $this->assertSame('1', $decoded['status']);
        $this->assertSame('20.00', $decoded['total']);
        $this->assertSame('INV-123', $decoded['invoiceId']);
        $this->assertSame('9001', $decoded['orderId']);
        $this->assertSame('TRY', $decoded['currencyCode']);
    }

    public function test_verify_hash_rejects_garbage(): void
    {
        $client = $this->client();

        $this->assertFalse($client->verify_hash(''));
        $this->assertFalse($client->verify_hash('not-a-valid-bundle'));
    }

    public function test_hash_is_non_deterministic_but_verifiable(): void
    {
        $client = $this->client();

        $a = $client->hash(['1', '5', 'X']);
        $b = $client->hash(['1', '5', 'X']);

        // Random IV/salt => different ciphertext each time...
        $this->assertNotSame($a, $b);

        // ...yet both decrypt back to the same values.
        $da = $client->verify_hash($a);
        $db = $client->verify_hash($b);
        $this->assertSame($da['status'], $db['status']);
        $this->assertSame($da['total'], $db['total']);
        $this->assertSame($da['invoiceId'], $db['invoiceId']);
    }
}
