<?php

namespace QNBPay\Tests\Unit;

use QNBPay\Support\Util;
use QNBPay\Tests\TestCase;

/**
 * @covers \QNBPay\Support\Util
 */
class UtilTest extends TestCase
{
    public function test_number_format_truncates_without_rounding(): void
    {
        // 19.999 -> two decimals, last digit truncated (not rounded up).
        $this->assertSame('19.99', Util::number_format('19.999'));
        $this->assertSame('20.00', Util::number_format(20));
        $this->assertSame('0.00', Util::number_format(null));
        $this->assertSame('0.00', Util::number_format('not-a-number'));
    }

    public function test_price_appends_currency_symbol(): void
    {
        $this->assertSame('19.99 TL', Util::price('19.999'));
        $this->assertSame('10.00 TL', Util::price(' 10 '));
    }

    public function test_test_cards_are_all_numeric(): void
    {
        $cards = Util::test_cards();
        $this->assertNotEmpty($cards);
        foreach ($cards as $card) {
            $this->assertMatchesRegularExpression('/^\d{16}$/', $card);
        }
    }
}
