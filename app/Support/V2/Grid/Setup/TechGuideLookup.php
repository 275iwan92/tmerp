<?php

/*
 * REMARK
 * Tujuan       : Layar Setup > Help and Support > Technical Guide: kondisi sistem per modul dan sub-modul (kartu konsep, kartu engine, relasi, peta besar), bahasa Inggris, dibaca seperti artikel; bagian rumus rahasia hanya untuk yang berhak.
 * Sumber hulu  : tm2sys.sys_guide bagian section = tg (docs/tg/<modul>/<sub-modul>/NN_slug.md lewat php artisan guides:import).
 * Input        : ?q= (cari), ?doc= (id kartu); chip "Modul · Sub-modul".
 * Output       : feed() dan article() seperti GuidesLookup; hanya baca.
 * Dipakai oleh : Menu SET_TECH_GUIDE (v2:setup-tech-guide); tampilan resources/views/v2/screens/setup-docs-library.blade.php. Pohon modul > sub-modul seperti mockup disetujui Jo menyusul.
 * Status       : BARU 2026-10-16, tahap 1 (daftar kartu dengan chip; pohon belum).
 */

namespace App\Support\V2\Grid\Setup;

class TechGuideLookup extends GuidesLookup
{
    public function slug(): string
    {
        return 'setup-tech-guide';
    }

    public function title(): string
    {
        return 'Technical Guide';
    }

    public function icon(): string
    {
        return 'fa-solid fa-diagram-project';
    }

    public function guide(): ?string
    {
        return 'Technical Guide: how each module works today (concept cards, engine cards, relations), written for agents and the owner. Nothing can be changed here.';
    }

    protected function section(): string
    {
        return 'tg';
    }
}
