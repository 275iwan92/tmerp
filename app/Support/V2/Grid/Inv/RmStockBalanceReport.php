<?php

namespace App\Support\V2\Grid\Inv;

class RmStockBalanceReport extends StockBalanceReport
{
    protected function category(): string
    {
        return 'RM';
    }
}
