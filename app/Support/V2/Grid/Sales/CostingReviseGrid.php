<?php

namespace App\Support\V2\Grid\Sales;

// Pro-Cost > Revise: inquiry REVISE yang sudah Confirm oleh ProDev.
class CostingReviseGrid extends CostingListGrid
{
    protected function type(): string
    {
        return 'REVISE';
    }
}
