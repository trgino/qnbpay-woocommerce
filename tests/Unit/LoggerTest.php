<?php

namespace QNBPay\Tests\Unit;

use QNBPay\Support\Logger;
use QNBPay\Tests\TestCase;

/**
 * @covers \QNBPay\Support\Logger
 */
class LoggerTest extends TestCase
{
    public function test_mask_redacts_sensitive_fields(): void
    {
        $masked = Logger::mask([
            'cc_no' => '4546711234567894',
            'cvv' => '123',
            'app_secret' => 'topsecret',
            'name' => 'John Doe',
        ]);

        // Card number keeps only the 6-digit BIN.
        $this->assertSame('454671' . str_repeat('*', 10), $masked['cc_no']);
        $this->assertSame('***', $masked['cvv']);
        $this->assertSame('***', $masked['app_secret']);
        // Non-sensitive fields are untouched.
        $this->assertSame('John Doe', $masked['name']);
    }

    public function test_mask_recurses_into_nested_structures(): void
    {
        $masked = Logger::mask(['payment' => ['cc_no' => '5571135571135575']]);
        $this->assertSame('557113' . str_repeat('*', 10), $masked['payment']['cc_no']);
    }

    public function test_exception_never_throws_without_wc_logger(): void
    {
        $logger = new Logger(true);
        // wc_get_logger() is not defined in the unit environment, so this must
        // resolve to a no-op rather than raising.
        $logger->exception('unit', new \RuntimeException('boom'));

        $this->assertTrue(true);
    }
}
