<?php

use App\BillSplitter;
use PHPUnit\Framework\TestCase;

class BillSplitterTest extends TestCase
{
    public function testTip(): void
    {
        // 10% от 350 лей
        $bill = new BillSplitter(350, 3);
        $this->assertEquals(35, $bill->tip(10));
    }

    public function testTotalWithTip(): void
    {
        // 200 лей + 15% чаевых
        $bill = new BillSplitter(200, 2);
        $this->assertEquals(230, $bill->totalWithTip(15));
    }

    public function testPerPersonNoTip(): void
    {
        // 300 лей на четверых, без чаевых
        $bill = new BillSplitter(300, 4);
        $this->assertEquals(75, $bill->perPerson());
    }

    public function testPerPersonWithTip(): void
    {
        // 350 лей на троих + 10% официанту
        $bill = new BillSplitter(350, 3);
        $this->assertEquals(128.33, $bill->perPerson(10));
    }

    public function testZeroPeopleThrows(): void
    {
        // никто не пришёл
        $this->expectException(InvalidArgumentException::class);
        new BillSplitter(100, 0);
    }

    public function testNegativeTotalThrows(): void
    {
        // отрицательный счёт не бывает
        $this->expectException(InvalidArgumentException::class);
        new BillSplitter(-50, 2);
    }
}
