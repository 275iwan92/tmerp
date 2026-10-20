<?php

/*
 * REMARK
 * Tujuan       : Daftar Inquiry Note (menu Sales > Inquiry Product > Inquiry Note): semua inquiry beserta stage-nya; klik baris atau Print membuka cetak Nota Inquiry (price offer ke customer).
 * Sumber hulu  : InquiryDeskGrid (Sales::desk, meja Sales, semua jenis NEW / REVISE / RECALC); tabel iqp_inquiry, iqp_part.
 * Input        : -
 * Output       : Baris grid; open_url = cetak inquiry-note (PrintDocs).
 * Dipakai oleh : resources/views/v2/screens/nota-inquiry.blade.php (layar v2 slug nota-inquiry).
 * Status       : Aktif (T10, 2026-10-20).
 */

namespace App\Support\V2\Grid\Sales;

use App\Support\V2\Print\PrintDocs;

class InquiryNoteGrid extends InquiryDeskGrid
{
    public function slug(): string
    {
        return 'nota-inquiry';
    }

    public function title(): string
    {
        return 'Inquiry Note';
    }

    public function module(): string
    {
        return 'SALES';
    }

    public function icon(): string
    {
        return 'fa-solid fa-receipt';
    }

    protected function type(): string
    {
        return '';
    }

    protected function desk(): string
    {
        return 'SALES';
    }

    protected function rowUrl(array $r): string
    {
        return PrintDocs::url('inquiry-note', $r['id'], ['from' => 'nota-inquiry']);
    }

    public function newUrl(): ?string
    {
        return null;
    }
}
