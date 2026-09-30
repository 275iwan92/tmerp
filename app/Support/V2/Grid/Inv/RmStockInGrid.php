<?php

namespace App\Support\V2\Grid\Inv;

class RmStockInGrid extends StockDocGrid
{
    protected function category(): string
    {
        return 'RM';
    }

    protected function kind(): string
    {
        return 'in';
    }
}
