<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// User minta grup laporan Inventory diberi judul ringkas "Laporan" saja
// (bukan "Stok & Laporan"), dan menjadikan pola [grup Input][grup Laporan]
// sbg patokan sidebar module lain ke depan (lihat sidebarmenustandard.md).
// "Stok Bahan Baku" (INV_RAW) juga diganti nama -- layar aslinya (lihat
// #[Title] & heading di inventory/print/master-stock.blade.php) SUDAH
// bernama "Laporan Master Stok" di mana-mana (title bar, header layar,
// kop cetak) -- rekap BERKALA (rentang tanggal, saldo awal/masuk/keluar/
// adj/saldo akhir), bukan daftar statis "Bahan Baku". Label sidebar lama
// menyesatkan (kesannya snapshot RM doang, padahal laporan periodik lintas
// item begitu SFG/FG live) DAN tidak match nama asli layarnya. Dipakai
// "Master Stok" saja di sidebar (bukan "Laporan Master Stok" penuh) supaya
// tidak dobel dengan judul grup "Laporan" di atasnya. "Kartu Stok" (INV_CARD)
// tidak diubah -- nama itu sudah istilah baku & sudah konsisten dgn layarnya.
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2sys');

        $db->table('sys_app_menu')->where('code', 'INV_RPT')->update(['label' => 'Laporan']);
        $db->table('sys_app_menu')->where('code', 'INV_RAW')->update(['label' => 'Master Stok']);
    }

    public function down(): void
    {
        $db = DB::connection('tm2sys');

        $db->table('sys_app_menu')->where('code', 'INV_RPT')->update(['label' => 'Stok & Laporan']);
        $db->table('sys_app_menu')->where('code', 'INV_RAW')->update(['label' => 'Stok Bahan Baku']);
    }
};
