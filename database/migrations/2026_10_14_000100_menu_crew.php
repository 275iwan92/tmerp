<?php

/*
 * REMARK
 * Tujuan       : Menu "Assign Crew" (grid mesin SBU Offset Printing + nickname) tepat di bawah "Live Schedule" pada Manufacture > Planning.
 * Sumber hulu  : sys_app_menu (tm2sys), induk PROD_PLAN, saudara PROD_LIVE.
 * Input        : -
 * Output       : Satu baris menu PROD_CREW -> v2:crew (urutan sama dengan Live Schedule, id lebih besar = tepat di bawahnya).
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
        $live = $sys->table('sys_app_menu')->where('code', 'PROD_LIVE')->first();
        if ($live && ! $sys->table('sys_app_menu')->where('code', 'PROD_CREW')->exists()) {
            $sys->table('sys_app_menu')->insert(['parent_id' => $live->parent_id, 'code' => 'PROD_CREW', 'label' => 'Assign Crew', 'icon' => 'fa-solid fa-people-group', 'menu_type' => 'item', 'object_type' => 'form',
                'target' => 'v2:crew', 'sort_order' => $live->sort_order, 'activate' => 1, 'hide_in_menu' => 0, 'required_object' => $live->required_object, 'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PROD_CREW')->delete();
    }
};
