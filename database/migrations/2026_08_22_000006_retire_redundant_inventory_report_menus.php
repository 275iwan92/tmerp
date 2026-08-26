<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// User menegaskan ULANG (2x): grup Laporan Inventory HANYA 2 item -- Kartu
// Stok & Master Stok -- masing-masing sudah internal terbagi 3 kelas
// (RM/FG/SFG) via tombol switchCategory() (lihat commit d3deb47). Jadi
// "Stok Barang Jadi" (INV_FG, dulu placeholder NOT_BUILT terpisah utk FG)
// sekarang REDUNDAN -- fungsinya sudah tercakup di Master Stok kategori FG.
// "Riwayat Mutasi" (INV_HIST) & "Periode Stok" (INV_PERIOD) juga di-retire
// atas instruksi eksplisit "yang lain di report inventory dihilangkan" --
// PENGECUALIAN dari aturan umum sidebarmenustandard.md (NOT_BUILT normalnya
// tetap tampil sbg placeholder) -- di sini user secara spesifik minta cuma 2.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->whereIn('code', ['INV_FG', 'INV_HIST', 'INV_PERIOD'])
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->whereIn('code', ['INV_FG', 'INV_HIST', 'INV_PERIOD'])
            ->update(['is_active' => true]);
    }
};
