<?php

namespace App\Support\V2\Grid\Sales;

// Semua CO dikelompokkan per status dokumen (varian Group By).
class CoStatusReport extends CoReportGrid
{
    public function slug(): string
    {
        return 'co-rpt-status';
    }

    public function title(): string
    {
        return 'Order Qty Only - Order Status';
    }

    public function columns(): array
    {
        return [['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::STATUS_TONES], ...$this->qtyColumns()];
    }

    public function groupBy(): ?string
    {
        return 'status';
    }
}
