<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// User 2026-10-04: "GENERAL nanti menu itu ada di sales module, bisa nanti di PRODUCTION module. Sifatnya general bukan
// berarti dibuatkan menu general report." General = an access property (open to every logged-in user, no prices), not a
// menu location: the three no-price CO reports move from the module "General Report" into Sales > Sales Order as the group
// "General Report" (required_object GENERAL), and the module row goes away. Runs on ddt* (--env=mockup) and tm2*.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    public function up(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $module = $menu()->where('code', 'GENERAL')->where('menu_type', 'module')->first();
        if (! $module) {
            return;
        }

        $order = $menu()->where('code', 'SALES_ORDGRP')->value('id');
        if ($order && $menu()->whereIn('code', ['GENERAL_CO_SUMMARY', 'GENERAL_CO_ORDER', 'GENERAL_CO_SHIP'])->exists()) {
            $group = $menu()->where('code', 'SALES_ORDGRP_GENRPT')->value('id');
            if (! $group) {
                $group = $menu()->insertGetId([
                    'parent_id' => $order, 'code' => 'SALES_ORDGRP_GENRPT', 'label' => 'General Report', 'icon' => 'fa-solid fa-globe',
                    'menu_type' => 'group', 'sort_order' => 910, 'activate' => 1, 'required_object' => 'GENERAL',
                    'status_code' => 'READY_FOR_QC', 'created_at' => now(),
                ]);
            }
            $menu()->whereIn('code', ['GENERAL_CO_SUMMARY', 'GENERAL_CO_ORDER', 'GENERAL_CO_SHIP'])->update(['parent_id' => $group]);
        }

        // Anything still under the old module (its "Customer Order" group) is removed with it, deepest level first
        // (parent_id is a self-referencing FK).
        $levels = [[$module->id]];
        while ($children = $menu()->whereIn('parent_id', end($levels))->pluck('id')->all()) {
            $levels[] = $children;
        }
        foreach (array_reverse($levels) as $ids) {
            $menu()->whereIn('id', $ids)->delete();
        }
    }

    public function down(): void
    {
        // The module row is not restored on purpose: "General" is an access flag, never a menu of its own.
    }
};
