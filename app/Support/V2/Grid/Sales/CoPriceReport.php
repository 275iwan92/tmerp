<?php

namespace App\Support\V2\Grid\Sales;

// Semua CO dengan harga, diskon & subtotal per item.
class CoPriceReport extends CoReportGrid
{
    public function slug(): string
    {
        return 'co-rpt-price';
    }

    public function title(): string
    {
        return 'Order with Price - All Status';
    }

    public function columns(): array
    {
        return [
            ['key' => 'co', 'label' => 'No CO'],
            ['key' => 'co_date', 'label' => 'Tgl CO', 'type' => 'date'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'po', 'label' => 'No PO Customer'],
            ['key' => 'sales', 'label' => 'Sales By'],
            ['key' => 'product', 'label' => 'Product'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'sum' => true],
            ['key' => 'unit', 'label' => 'Satuan'],
            ['key' => 'currency', 'label' => 'Mata Uang'],
            ['key' => 'price', 'label' => 'Harga Satuan', 'type' => 'money'],
            ['key' => 'disc', 'label' => 'Diskon %', 'type' => 'number'],
            ['key' => 'subtotal', 'label' => 'Subtotal', 'type' => 'money', 'sum' => true],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::STATUS_TONES],
        ];
    }
}
