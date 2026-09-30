<?php

namespace App\Support\V2\Grid\Inv;

class FgStockReturnGrid extends StockDocGrid
{
    protected function category(): string
    {
        return 'FG';
    }

    protected function kind(): string
    {
        return 'return';
    }
}
