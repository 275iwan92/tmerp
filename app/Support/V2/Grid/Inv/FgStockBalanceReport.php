<?php

namespace App\Support\V2\Grid\Inv;

class FgStockBalanceReport extends StockBalanceReport
{
    protected function category(): string
    {
        return 'FG';
    }
}
