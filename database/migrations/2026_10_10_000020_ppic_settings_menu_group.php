<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// One "PPIC Settings" group under Production for everything an officer changes now and then (machines, processes, tooling, setup, waits, rules, crew, parameters),
// so the daily menu stays short. Items are moved, not copied. Dummy menu database first (--env=mockup); live at release.
return new class extends Migration
{
    private const MOVE = ['MD_MACHINE', 'PROD_MACHINE_MODEL', 'PROD_MST_PROCESS', 'PROD_ROUTING_TPL', 'PROD_MST_BOP_RULE', 'PROD_MST_TOOLING', 'PROD_MST_CHANGEOVER', 'PROD_MST_WAIT_RULE', 'PROD_RULE_LIB', 'PROD_MST_CREW'];

    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $menu = fn () => $sys->table('sys_app_menu');
        $prod = $menu()->where('code', 'PROD')->first();
        if (! $prod) {
            return;
        }
        if (! $menu()->where('code', 'PROD_PPIC_SET')->exists()) {
            $menu()->insert(['parent_id' => $prod->id, 'code' => 'PROD_PPIC_SET', 'label' => 'PPIC Settings', 'icon' => 'fa-solid fa-sliders', 'menu_type' => 'group', 'sort_order' => 25,
                'activate' => 1, 'required_object' => null, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now()]);
        }
        $group = $menu()->where('code', 'PROD_PPIC_SET')->value('id');
        $sort = 10;
        foreach (self::MOVE as $code) {
            $menu()->where('code', $code)->update(['parent_id' => $group, 'sort_order' => $sort]);
            $sort += 10;
        }
        if (! $menu()->where('code', 'PROD_PPIC_PARAMS')->exists()) {
            $menu()->insert(['parent_id' => $group, 'code' => 'PROD_PPIC_PARAMS', 'label' => 'Planning Parameters', 'icon' => 'fa-solid fa-gauge', 'menu_type' => 'item', 'object_type' => 'grid',
                'target' => 'v2:setup-constants', 'sort_order' => $sort, 'activate' => 1, 'status_code' => 'READY_FOR_QC', 'status_note' => 'Constants PPIC_* (Settings > Constants)',
                'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now()]);
        }
        $menu()->where('code', 'PROD_MPS')->update(['label' => 'PPIC Workspace']);
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $master = $sys->table('sys_app_menu')->where('code', 'PROD_MASTER')->value('id');
        $sys->table('sys_app_menu')->whereIn('code', self::MOVE)->update(['parent_id' => $master]);
        $sys->table('sys_app_menu')->whereIn('code', ['PROD_PPIC_PARAMS', 'PROD_PPIC_SET'])->delete();
        $sys->table('sys_app_menu')->where('code', 'PROD_MPS')->update(['label' => 'Production Scheduling']);
    }
};
