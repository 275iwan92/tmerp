<?php

namespace App\Support\V2\Grid\Inv;

class SfgStockReturnGrid extends StockDocGrid
{
    protected function category(): string
    {
        return 'SFG';
    }

    protected function kind(): string
    {
        return 'return';
    }
}
