<?php

namespace App\Support\V2\Grid\Inv;

class RmStockOutGrid extends StockDocGrid
{
    protected function category(): string
    {
        return 'RM';
    }

    protected function kind(): string
    {
        return 'out';
    }
}
