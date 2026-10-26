<?php

namespace App;

class BillSplitter
{
    public function __construct(private float $total, private int $people)
    {
        if ($people < 1) {
            throw new \InvalidArgumentException('на 0 человек не делится');
        }
        if ($total < 0) {
            throw new \InvalidArgumentException('сумма не может быть меньше нуля');
        }
    }

    public function tip(int $percent): float
    {
        return round($this->total * $percent / 100, 2);
    }

    public function totalWithTip(int $percent): float
    {
        return round($this->total + $this->tip($percent), 2);
    }

    // сколько скидывается каждый
    public function perPerson(int $tipPercent = 0): float
    {
        return round($this->totalWithTip($tipPercent) / $this->people, 2);
    }
}
