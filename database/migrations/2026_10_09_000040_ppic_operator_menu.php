<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Operator Queue (tablet at the machine) joins Production > Output. Run on the dummy menu database first (--env=mockup); live at release.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $parent = $sys->table('sys_app_menu')->where('code', 'PROD_OUT')->first();
        if (! $parent || $sys->table('sys_app_menu')->where('code', 'PROD_OPERATOR')->exists()) {
            return;
        }
        $sys->table('sys_app_menu')->insert([
            'parent_id' => $parent->id, 'code' => 'PROD_OPERATOR', 'label' => 'Operator Queue', 'icon' => 'fa-solid fa-stopwatch', 'menu_type' => 'item', 'object_type' => 'form',
            'target' => 'v2:production-operator', 'sort_order' => 5, 'activate' => 1, 'required_object' => $parent->required_object, 'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PROD_OPERATOR')->delete();
    }
};
