<?php

namespace App\Support\V2\Grid\Sales;

use Illuminate\Database\Eloquent\Builder;

// Total penjualan (CO tidak batal) dikelompokkan per customer; Grup bisa diganti ke Bulan / Sales By.
class CoTotalSalesReport extends CoReportGrid
{
    public function slug(): string
    {
        return 'co-rpt-sales';
    }

    public function title(): string
    {
        return 'Order with Price - Total Sales';
    }

    protected function scope(Builder $q): Builder
    {
        return $q->whereHas('order', fn ($o) => $o->where('stat_cancel', false));
    }

    public function columns(): array
    {
        return [
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'month', 'label' => 'Bulan'],
            ['key' => 'co', 'label' => 'No CO'],
            ['key' => 'sales', 'label' => 'Sales By'],
            ['key' => 'product', 'label' => 'Product'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'sum' => true],
            ['key' => 'subtotal', 'label' => 'Total Penjualan', 'type' => 'money', 'sum' => true],
        ];
    }

    public function groupBy(): ?string
    {
        return 'customer';
    }
}
