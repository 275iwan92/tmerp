<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Permintaan user: sembunyikan menu MRP dari sidebar (activate=0) -- Engine MRP
// (App\Services\Production\MrpEngine) tetap dibangun & jalan di balik layar
// saat pembuatan WO, cuma layar/menunya yang tak ditampilkan (belum ada UI
// worklist MRP, netting jalan otomatis, hasilnya berupa PR draft ke Procurement).
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->where('code', 'PROD_MRP')
            ->update(['activate' => 0]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->where('code', 'PROD_MRP')
            ->update(['activate' => 1]);
    }
};
