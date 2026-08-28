<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Screen #1 (picklist Plan Delivery berdiri sendiri, wo-generate.blade.php)
// DIHAPUS 2026-08-28 atas permintaan user -- fungsinya digabung jadi modal
// dalam WO Detail (production.wo.new / production.wo.entry), bukan layar +
// menu tersendiri lagi. Hapus baris menu PROD_WO_GENERATE supaya sidebar
// tak nunjuk ke route yang sudah tak ada. Data (route/screen file) sudah
// dihapus di sisi kode; migration ini cuma bersihkan sisa menu.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PROD_WO_GENERATE')->delete();
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $planId = $sys->table('sys_app_menu')->where('code', 'PROD_PLAN')->value('id');
        if ($planId && $sys->table('sys_app_menu')->where('code', 'PROD_WO_GENERATE')->doesntExist()) {
            $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;
            $sys->table('sys_app_menu')->insert([
                'id' => $nextId,
                'parent_id' => $planId,
                'code' => 'PROD_WO_GENERATE',
                'label' => 'Generate WO dari Plan Delivery',
                'menu_type' => 'item',
                'object_type' => 'grid',
                'target_kind' => 'app',
                'sort_order' => 14,
                'activate' => true,
                'status_code' => 'READY_FOR_QC',
                'created_at' => now(),
            ]);
        }
    }
};
