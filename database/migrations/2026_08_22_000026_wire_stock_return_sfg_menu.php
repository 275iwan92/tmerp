<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Return from Production diperluas ke SFG 2026-08-22 (sesudah Stock Out SFG)
// -- kalau SFG bisa keluar ke produksi, wajar bisa diretur juga (cacat/tak
// kepakai kembali ke gudang), pola sama persis dgn RM. FG tetap NOT_BUILT
// (tak pernah bisa Stock Out, jadi tak ada sumber utk diretur).
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'INV_SFG_ITEM3')->update(['status_code' => 'READY_FOR_QC']);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'INV_SFG_ITEM3')->update(['status_code' => 'NOT_BUILT']);
    }
};
