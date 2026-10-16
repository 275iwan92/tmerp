<?php

/*
 * REMARK
 * Tujuan       : Layar Setup > Help and Support > Design Notes: catatan perancangan pengembang sebagai kilas balik untuk Jo (mengapa dirancang demikian), dibaca seperti artikel; dipisah dari Guides.
 * Sumber hulu  : tm2sys.sys_guide bagian section = design (docs/design-notes/<area>/NN_slug.md lewat php artisan guides:import).
 * Input        : ?q= (cari), ?doc= (id catatan); chip area (Maintenance, Inventory, Ppic).
 * Output       : feed() dan article() seperti GuidesLookup; hanya baca.
 * Dipakai oleh : Menu SET_DESIGN_NOTES (v2:setup-design-notes); tampilan resources/views/v2/screens/setup-docs-library.blade.php.
 * Status       : Aktif.
 */

namespace App\Support\V2\Grid\Setup;

class DesignNotesLookup extends GuidesLookup
{
    public function slug(): string
    {
        return 'setup-design-notes';
    }

    public function title(): string
    {
        return 'Design Notes';
    }

    public function icon(): string
    {
        return 'fa-solid fa-clock-rotate-left';
    }

    public function guide(): ?string
    {
        return 'Design notes: why each module was designed the way it is (plans, phases, decisions). A look back for the owner; nothing can be changed here.';
    }

    protected function section(): string
    {
        return 'design';
    }
}
