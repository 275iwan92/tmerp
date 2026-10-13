<?php

/*
 * REMARK
 * Tujuan       : Menu "Live Schedule" (pemantauan read-only jadwal berlaku) tepat di bawah "Work Order" pada Production > Planning.
 * Sumber hulu  : sys_app_menu (tm2sys), induk PROD_PLAN.
 * Input        : -
 * Output       : Satu baris menu PROD_LIVE -> v2:production-live (urutan tepat setelah PROD_WO_LIST).
 * Dipakai oleh : AppMenu (menu v2).
 * Status       : Jalankan di ddt* (--env=mockup); di tm2* saat rilis.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $wo = $sys->table('sys_app_menu')->where('code', 'PROD_WO_LIST')->first();
        if ($wo && ! $sys->table('sys_app_menu')->where('code', 'PROD_LIVE')->exists()) {
            $sys->table('sys_app_menu')->insert(['parent_id' => $wo->parent_id, 'code' => 'PROD_LIVE', 'label' => 'Live Schedule', 'icon' => 'fa-solid fa-chart-gantt', 'menu_type' => 'item', 'object_type' => 'form',
                'target' => 'v2:production-live', 'sort_order' => $wo->sort_order + 1, 'activate' => 1, 'hide_in_menu' => 0, 'required_object' => $wo->required_object, 'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PROD_LIVE')->delete();
    }
};
