<?php

namespace App\Support\V2\Grid\Inv;

class SfgStockBalanceReport extends StockBalanceReport
{
    protected function category(): string
    {
        return 'SFG';
    }
}
