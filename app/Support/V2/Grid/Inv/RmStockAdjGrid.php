<?php

namespace App\Support\V2\Grid\Inv;

class RmStockAdjGrid extends StockDocGrid
{
    protected function category(): string
    {
        return 'RM';
    }

    protected function kind(): string
    {
        return 'adj';
    }
}
