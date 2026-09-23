<?php

namespace Devlib\Components;

// Laporan cetak (mockup_print_stock_card, mockup_print_master_stock): sama dengan x-dl::print-out
// (kop, kertas, halaman otomatis) + kriteria, ringkasan KPI, kelompok + subtotal, kolom berjalan.
class Report extends PrintOut
{
    public function tag(): string
    {
        return 'x-dl::report';
    }

    public function source(): string
    {
        return $this->source ?? 'laporan';
    }
}
