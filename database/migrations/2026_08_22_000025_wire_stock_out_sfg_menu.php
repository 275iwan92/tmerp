<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Stock Out diperluas ke SFG 2026-08-22 (barang setengah jadi keluar gudang
// lanjut ke proses produksi berikutnya -- user konfirmasi skenario nyata:
// SFG sempat nunggu di gudang krn keterlambatan material/prioritas produk
// lain). FG TETAP NOT_BUILT -- barang jadi keluar cuma lewat Delivery Order
// (Dispatch, belum dibangun), bukan Stock Out generik, dikonfirmasi user.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'INV_SFG_ITEM2')->update(['status_code' => 'READY_FOR_QC']);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'INV_SFG_ITEM2')->update(['status_code' => 'NOT_BUILT']);
    }
};
