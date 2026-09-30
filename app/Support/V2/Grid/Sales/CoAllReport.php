<?php

namespace App\Support\V2\Grid\Sales;

// Semua CO, semua status.
class CoAllReport extends CoReportGrid
{
    public function slug(): string
    {
        return 'co-rpt-all';
    }

    public function title(): string
    {
        return 'Order Qty Only - All Status';
    }

    public function columns(): array
    {
        return [...$this->qtyColumns(), ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::STATUS_TONES]];
    }
}
