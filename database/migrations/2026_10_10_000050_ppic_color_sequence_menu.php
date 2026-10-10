<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Colour Sequence (print process order, inks and wash per press) joins Production > Planning. Dummy menu database first (--env=mockup).
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $parent = $sys->table('sys_app_menu')->where('code', 'PROD_PLAN')->first();
        if ($parent && ! $sys->table('sys_app_menu')->where('code', 'PROD_COLOR_SEQ')->exists()) {
            $sys->table('sys_app_menu')->insert(['parent_id' => $parent->id, 'code' => 'PROD_COLOR_SEQ', 'label' => 'Colour Sequence', 'icon' => 'fa-solid fa-palette', 'menu_type' => 'item', 'object_type' => 'form',
                'target' => 'v2:ppic-color-sequence', 'sort_order' => 12, 'activate' => 1, 'required_object' => $parent->required_object, 'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PROD_COLOR_SEQ')->delete();
    }
};
