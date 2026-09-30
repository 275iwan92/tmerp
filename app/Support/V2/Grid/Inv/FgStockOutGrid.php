<?php

namespace App\Support\V2\Grid\Inv;

class FgStockOutGrid extends StockDocGrid
{
    protected function category(): string
    {
        return 'FG';
    }

    protected function kind(): string
    {
        return 'out';
    }
}
