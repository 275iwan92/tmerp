<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Grup Master Data di menu PREORDER hanya berisi master yang berhubungan langsung dengan Inquiry (user 2026-10-02: "jangan ada
// currency, rate dan lain sebagainya"): pintasan Satuan, Currency, Kurs, dan Constants yang dibuat migrasi 000008 dicabut lagi.
// Layarnya tetap ada di menu asalnya (Master Data / Setup).
return new class extends Migration
{
    protected $connection = 'tm2sys';

    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', ['PRE_MD_UOM', 'PRE_MD_CURRENCY', 'PRE_MD_EXCHRATE', 'PRE_SET_CONSTANTS'])->delete();
    }

    public function down(): void
    {
        // Sengaja kosong: pintasan ini dicabut atas keputusan user.
    }
};
