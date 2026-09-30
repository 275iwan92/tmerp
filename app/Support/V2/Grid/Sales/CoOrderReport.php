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
        return 'Satu laporan untuk semua kebutuhan: klik tombol Kolom untuk menyalakan kolom tambahan '
            .'(Status, Harga Satuan, Diskon, Subtotal, Rencana Kirim, Terkirim, Sisa, Bulan, Mode). '
            .'Kotak di atas menyaring Open / Confirmed / Outstanding / Batal, Filter menyaring per kolom, '
            .'dan Grup (Customer, Bulan, Status, Sales By) menjumlahkan Qty & Subtotal = Total Sales.';
    }

    public function columns(): array
    {
        $hidden = ['visible' => false];

        return [
            ...$this->qtyColumns(),
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::STATUS_TONES] + $hidden,
            ['key' => 'mode', 'label' => 'Mode CO', 'type' => 'badge', 'tones' => ['INTEGRATED' => 'info', 'INSTANT' => 'accent']] + $hidden,
            ['key' => 'month', 'label' => 'Bulan'] + $hidden,
            ['key' => 'currency', 'label' => 'Mata Uang'] + $hidden,
            ['key' => 'price', 'label' => 'Harga Satuan', 'type' => 'money'] + $hidden,
            ['key' => 'disc', 'label' => 'Diskon %', 'type' => 'number'] + $hidden,
            ['key' => 'subtotal', 'label' => 'Subtotal', 'type' => 'money', 'sum' => true] + $hidden,
            ['key' => 'planned', 'label' => 'Rencana Kirim', 'type' => 'number', 'sum' => true] + $hidden,
            ['key' => 'shipped', 'label' => 'Terkirim', 'type' => 'number', 'sum' => true] + $hidden,
            ['key' => 'remain', 'label' => 'Sisa', 'type' => 'number', 'sum' => true] + $hidden,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'Semua Status'],
            ['label' => 'Open & tidak batal', 'where' => ['open' => true], 'tone' => 'info'],
            ['label' => 'Confirmed', 'where' => ['status' => 'CONFIRMED'], 'tone' => 'success'],
            ['label' => 'Outstanding', 'where' => ['outstanding' => true], 'tone' => 'warn'],
            ['label' => 'Batal', 'where' => ['status' => 'CANCELLED'], 'tone' => 'danger'],
        ];
    }
}
