<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Master T4d (Jo 2026-10-18): halaman v1 Master Warehouse (+ grid md-warehouse) dihapus karena lembar Gedung (wh-buildings), Bin dan Cetak Label Lokasi
// sudah mengelola tabel yang sama. Menu Master Data > Master Warehouse (MD_WH) dan Inventory > Warehouse & Location (INV_REF_WH) kini membuka lembar Gedung.
// Hanya mengubah target tm2sys.sys_app_menu (tidak ada skema atau data transaksi); jalankan dengan --path di mockup (--env=mockup) dan tmerp.
return new class extends Migration
{
    private const CODES = ['MD_WH', 'INV_REF_WH'];

    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', self::CODES)->where('target', 'v2:md-warehouse')->update(['target' => 'v2:wh-buildings']);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', self::CODES)->where('target', 'v2:wh-buildings')->update(['target' => 'v2:md-warehouse']);
    }
};
