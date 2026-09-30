<?php

namespace App\Support\V2\Grid\Pur;

use App\Support\V2\Grid\DbGrid;

// Induk laporan Procurement (menu Requisition/Order/Receive > Report). Laporan = per baris, grup + subtotal di grid.
abstract class PurReportGrid extends DbGrid
{
    public function module(): string
    {
        return 'PROC';
    }

    public function moduleLabel(): string
    {
        return 'Procurement';
    }

    public function family(): string
    {
        return self::REPORT;
    }

    public function icon(): string
    {
        return 'fa-solid fa-hourglass-half';
    }

    // Umur dokumen dalam hari -> ember yang sama di semua laporan outstanding.
    protected function ageBucket(?\DateTimeInterface $date): ?string
    {
        if (! $date) {
            return null;
        }
        $days = (int) now()->startOfDay()->diffInDays($date, true);

        return match (true) {
            $days <= 7 => '0-7 days',
            $days <= 30 => '8-30 days',
            default => '> 30 days',
        };
    }
}
