<?php

namespace QNBPay\Tests\Unit;

use QNBPay\Support\Arr;
use QNBPay\Tests\TestCase;

/**
 * @covers \QNBPay\Support\Arr
 */
class ArrTest extends TestCase
{
    public function test_get_returns_top_level_value(): void
    {
        $this->assertSame('bar', Arr::get(['foo' => 'bar'], 'foo'));
    }

    public function test_get_resolves_dot_notation(): void
    {
        $data = ['cart' => ['total' => 42]];
        $this->assertSame(42, Arr::get($data, 'cart.total'));
    }

    public function test_get_returns_default_when_missing(): void
    {
        $this->assertSame('fallback', Arr::get(['a' => 1], 'b', 'fallback'));
        $this->assertNull(Arr::get(['a' => 1], 'x.y.z'));
    }

    public function test_get_reads_object_properties(): void
    {
        $obj = (object) ['name' => 'QNB'];
        $this->assertSame('QNB', Arr::get($obj, 'name'));
    }

    public function test_str_is_null_safe(): void
    {
        $this->assertSame('', Arr::str(null));
        $this->assertSame('123', Arr::str(123));
        $this->assertSame('1', Arr::str(true));
        $this->assertSame('0', Arr::str(false));
        $this->assertSame('abc', Arr::str('abc'));
        $this->assertSame('', Arr::str(['not', 'scalar']));
    }
}
