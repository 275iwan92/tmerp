<?php

namespace App\Support\V2\Grid\Sales;

// Customer Order Report — SATU laporan pengganti 7 laporan v1 "Cust.Order Reports" (user 2026-09-30: dikecilkan,
// user memilih kolom sendiri). Varian v1 -> cara pakai di sini:
//   Order Qty Only (Open / All / Order Status) = kolom awal + kotak statistik / Grup Status;
//   Summary with Shipment = nyalakan kolom Rencana Kirim, Terkirim, Sisa;
//   Order with Price (All Status / Total Sales) = nyalakan kolom harga + Grup Customer/Bulan (subtotal otomatis);
//   Outstanding Order = kotak statistik Outstanding.
class CoOrderReport extends CoReportGrid
{
    public function slug(): string
    {
        return 'co-rpt-order';
    }

    public function title(): string
    {
        return 'Customer Order Report';
    }

    public function guide(): ?string
    {
        // Dua kalimat utuh (bukan sambungan) supaya masing-masing punya terjemahan di kamus; maksimal 255 huruf.
        return $this->seesPrice()
            ? 'One report for every need. Columns switches on extra columns (Status, Unit Price, Discount, Subtotal, Planned Shipment, Shipped, Remaining, Month, Mode). The boxes above filter by status; Group totals Qty & Subtotal = Total Sales.'
            : 'One report for every need. Columns switches on extra columns (Status, Planned Shipment, Shipped, Remaining, Month, Mode). The boxes above filter by status; Group (Customer, Month, Status, Sales By) totals the Qty.';
    }

    public function columns(): array
    {
        $hidden = ['visible' => false];

        return [
            ...$this->qtyColumns(),
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::STATUS_TONES] + $hidden,
            ['key' => 'mode', 'label' => 'CO Mode', 'type' => 'badge', 'tones' => ['INTEGRATED' => 'info', 'INSTANT' => 'accent']] + $hidden,
            ['key' => 'month', 'label' => 'Month'] + $hidden,
            ['key' => 'currency', 'label' => 'Currency'] + $hidden,
            ['key' => 'price', 'label' => 'Unit Price', 'type' => 'money'] + $hidden,
            ['key' => 'disc', 'label' => 'Discount %', 'type' => 'number'] + $hidden,
            ['key' => 'subtotal', 'label' => 'Subtotal', 'type' => 'money', 'sum' => true] + $hidden,
            ['key' => 'planned', 'label' => 'Planned Shipment', 'type' => 'number', 'sum' => true] + $hidden,
            ['key' => 'shipped', 'label' => 'Shipped', 'type' => 'number', 'sum' => true] + $hidden,
            ['key' => 'remain', 'label' => 'Remaining', 'type' => 'number', 'sum' => true] + $hidden,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All Statuses'],
            ['label' => 'Open & not cancelled', 'where' => ['open' => true], 'tone' => 'info'],
            ['label' => 'Confirmed', 'where' => ['status' => 'CONFIRMED'], 'tone' => 'success'],
            ['label' => 'Outstanding', 'where' => ['outstanding' => true], 'tone' => 'warn'],
            ['label' => 'Cancelled', 'where' => ['status' => 'CANCELLED'], 'tone' => 'danger'],
        ];
    }
}
