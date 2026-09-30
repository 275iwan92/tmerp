<?php

namespace App\Support\V2\Grid\Sales;

use Illuminate\Database\Eloquent\Builder;

// Per item CO: qty order, rencana kirim (CPDEL), terkirim (DO SHIPPED), sisa.
class CoShipSummaryReport extends CoReportGrid
{
    public function slug(): string
    {
        return 'co-rpt-ship-sum';
    }

    public function title(): string
    {
        return 'Order Qty Only - Summary with Shipment';
    }

    protected function scope(Builder $q): Builder
    {
        return $q->whereHas('order', fn ($o) => $o->where('stat_cancel', false)->whereNotIn('doc_status', ['CLOSED', 'CANCELLED']));
    }

    public function columns(): array
    {
        return [
            ['key' => 'co', 'label' => 'No CO'],
            ['key' => 'co_date', 'label' => 'Tgl CO', 'type' => 'date'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'po', 'label' => 'No PO Customer'],
            ['key' => 'product', 'label' => 'Product'],
            ['key' => 'unit', 'label' => 'Satuan'],
            ['key' => 'qty', 'label' => 'Qty Order', 'type' => 'number', 'sum' => true],
            ['key' => 'planned', 'label' => 'Rencana Kirim', 'type' => 'number', 'sum' => true],
            ['key' => 'shipped', 'label' => 'Terkirim', 'type' => 'number', 'sum' => true],
            ['key' => 'remain', 'label' => 'Sisa', 'type' => 'number', 'sum' => true],
            ['key' => 'due', 'label' => 'Due Date', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::STATUS_TONES],
        ];
    }
}
