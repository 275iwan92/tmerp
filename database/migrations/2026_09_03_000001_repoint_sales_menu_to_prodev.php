<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Permintaan user 2026-09-03 (screenshot sidebar Sales): menu setelah
// "Inquiry Product" harus "Pro-Dev" (BUKAN "Imposition Engine" lama),
// diikuti "Pro-Cost" -- bukan cuma ganti label, SALES_IMPO harus dialihkan
// ke layar ProDev workbench (route production.prodev) yang baru dibangun
// semalam (docs/inquiry_product_costing/productdevelopment_tier1.md §6
// poin 2: "Imposition pindah dari Inquiry ke Product Development").
// Layar lama inquiry.imposition.list TIDAK dihapus (route/file dibiarkan
// ada), cuma tidak lagi ditaut dari menu -- pola sama spt portal simulasi
// lama yang dipertahankan di repo tapi orphaned dari routing.
// PROD_CARD/PROD_LAYOUT (grup Production > Master) DISEMBUNYIKAN
// (activate=false, bukan dihapus) krn sekarang duplikat -- Pro-Dev di
// Sales jadi 1 satu-satunya entry point resminya, 2 menu beda lokasi
// nunjuk ke layar yang sama cuma bikin bingung.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $sys->table('sys_app_menu')->where('code', 'SALES_IMPO')->update([
            'label' => 'Pro-Dev',
        ]);

        $sys->table('sys_app_menu')->where('code', 'SALES_COST')->update([
            'label' => 'Pro-Cost',
        ]);

        $sys->table('sys_app_menu')->whereIn('code', ['PROD_CARD', 'PROD_LAYOUT'])->update([
            'activate' => false,
        ]);
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');

        $sys->table('sys_app_menu')->where('code', 'SALES_IMPO')->update([
            'label' => 'Imposition Engine',
        ]);

        $sys->table('sys_app_menu')->where('code', 'SALES_COST')->update([
            'label' => 'Product Costing',
        ]);

        $sys->table('sys_app_menu')->whereIn('code', ['PROD_CARD', 'PROD_LAYOUT'])->update([
            'activate' => true,
        ]);
    }
};
