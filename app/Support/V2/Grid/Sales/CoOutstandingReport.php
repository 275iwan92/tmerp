<?php

namespace App\Support\V2\Grid\Sales;

use Illuminate\Database\Eloquent\Builder;

// CO CONFIRMED yang qty-nya belum terkirim penuh (terkirim = DO SHIPPED).
class CoOutstandingReport extends CoReportGrid
{
    public function slug(): string
    {
        return 'co-rpt-outstanding';
    }

    public function title(): string
    {
        return 'Outstanding Order';
    }

    protected function scope(Builder $q): Builder
    {
        return $q->whereHas('order', fn ($o) => $o->where('stat_cancel', false)->where('doc_status', 'CONFIRMED'));
    }

    public function rows(): array
    {
        return array_values(array_filter(parent::rows(), fn ($r) => $r['remain'] > 0));
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
            ['key' => 'shipped', 'label' => 'Terkirim', 'type' => 'number', 'sum' => true],
            ['key' => 'remain', 'label' => 'Outstanding', 'type' => 'number', 'sum' => true],
            ['key' => 'due', 'label' => 'Due Date', 'type' => 'date'],
            ['key' => 'sales', 'label' => 'Sales By'],
        ];
    }
}
