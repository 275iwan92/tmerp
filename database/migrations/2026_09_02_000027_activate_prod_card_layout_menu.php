<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/inquiry_product_costing/productdevelopment_tier1.md §6 poin 4 -- flip
// activate=true skrg SETELAH layar ProDev (production/prodev) selesai
// dibangun & TERVERIFIKASI bisa dibuka tanpa error (HTTP kernel 200 +
// Livewire::test() end-to-end: run engine Tingkat 1, pick candidate, tambah
// routing/finishing, cetak Master Card -- semua lolos 2026-09-02). Slot menu
// PROD_CARD/PROD_LAYOUT sudah disiapkan sejak migration
// 2026_08_28_000026_hide_master_card_layout_rename_crew.php, tinggal
// dinyalakan. Keduanya diarahkan ke layar workbench yang SAMA
// (production.prodev, lihat main-menu.blade.php itemRoutes()) krn Master
// Card & Layout digabung jadi 1 layar kerja (tier1 §4.1 -- Master Card =
// tombol cetak dari data ProDev, bukan layar input terpisah).
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->whereIn('code', ['PROD_CARD', 'PROD_LAYOUT'])
            ->update(['activate' => true]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->whereIn('code', ['PROD_CARD', 'PROD_LAYOUT'])
            ->update(['activate' => false]);
    }
};
