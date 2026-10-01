<?php

namespace App\Support\V2\Grid\Sales;

// Pro-Cost > Recalc: inquiry RECALC yang sudah Confirm oleh Sales -- langsung ke Pro-Cost, tanpa Pro-Dev (spec sama).
class CostingRecalcGrid extends CostingListGrid
{
    protected function type(): string
    {
        return 'RECALC';
    }
}
