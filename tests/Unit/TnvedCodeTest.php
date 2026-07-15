<?php

namespace Tests\Unit;

use App\Models\TnvedItem;
use App\Support\TnvedCode;
use PHPUnit\Framework\TestCase;

class TnvedCodeTest extends TestCase
{
    public function test_format_full_product_code(): void
    {
        $this->assertSame('2812 90 000 0', TnvedItem::formatDisplayCode('2812900000'));
        $this->assertSame('2812 90 000 0', TnvedCode::formatDisplay('2812900000'));
    }

    public function test_format_group_code(): void
    {
        $this->assertSame('2812', TnvedItem::formatDisplayCode('2812000000'));
    }

    public function test_resolve_levels(): void
    {
        $this->assertSame(2, TnvedItem::resolveLevel('2812000000'));
        $this->assertSame(3, TnvedItem::resolveLevel('2812900000'));
        $this->assertSame(5, TnvedItem::resolveLevel('2812900001'));
    }

    public function test_normalize_short_code_without_changing_meaning(): void
    {
        $this->assertSame('2812000000', TnvedItem::normalizeCode('2812'));
        $this->assertSame('2812900000', TnvedItem::normalizeCode('2812900000'));
    }

    public function test_level_name_for_group(): void
    {
        $this->assertSame('группа', TnvedItem::resolveLevelName(2));
    }
}
