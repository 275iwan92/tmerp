<?php

namespace App\Support\V2\Grid\Inv;

class RmStockCardReport extends StockCardReport
{
    protected function category(): string
    {
        return 'RM';
    }
}
