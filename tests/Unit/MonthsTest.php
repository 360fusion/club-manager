<?php

namespace Tests\Unit;

use App\Support\Months;
use PHPUnit\Framework\TestCase;

class MonthsTest extends TestCase
{
    public function test_it_reads_names_and_abbreviations(): void
    {
        $this->assertSame(11, Months::number('Nov'));
        $this->assertSame(11, Months::number('november'));
        $this->assertSame(9, Months::number('Sept'));
        $this->assertSame(6, Months::number(' June '));
        $this->assertNull(Months::number('Ma'));
        $this->assertNull(Months::number('Someday'));
        $this->assertNull(Months::number(null));
    }

    public function test_it_names_a_month(): void
    {
        $this->assertSame('October', Months::name(10));
        $this->assertNull(Months::name(13));
        $this->assertNull(Months::name(null));
    }
}
