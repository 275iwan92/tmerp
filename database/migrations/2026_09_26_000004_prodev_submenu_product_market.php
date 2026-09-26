<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Pro-Dev jadi submenu di panel kiri (user 2026-09-26): Inquiry & Quotation > Pro-Dev > {Pro-Dev Worklist, Lookup Product Market}.
return new class extends Migration
{
    public function up(): void
    {
        $m = DB::connection('tm2sys')->table('sys_app_menu');
        $parent = (clone $m)->where('code', 'SALES_INQGRP')->value('id');
        $impo = (clone $m)->where('code', 'SALES_IMPO')->first();

        if (! $parent || ! $impo || (clone $m)->where('code', 'SALES_PRODEVGRP')->exists()) {
            return;
        }

        // sys_app_menu.id bukan auto-increment.
        $group = (int) (clone $m)->max('id') + 1;
        (clone $m)->insert([
            'id' => $group, 'parent_id' => $parent, 'code' => 'SALES_PRODEVGRP', 'label' => 'Pro-Dev', 'icon' => 'fa-solid fa-ruler-combined',
            'menu_type' => 'group', 'target_kind' => 'app', 'sort_order' => $impo->sort_order, 'activate' => 1,
            'created_at' => now(), 'status_code' => 'READY_FOR_QC',
        ]);

        (clone $m)->where('id', $impo->id)->update(['parent_id' => $group, 'label' => 'Pro-Dev Worklist', 'sort_order' => 10]);

        (clone $m)->insert([
            'id' => $group + 1, 'parent_id' => $group, 'code' => 'SALES_PMKT', 'label' => 'Lookup Product Market', 'icon' => 'fa-solid fa-tags',
            'menu_type' => 'item', 'object_type' => 'form', 'target' => 'lookup.product-market', 'target_kind' => 'app',
            'sort_order' => 20, 'activate' => 1, 'created_at' => now(), 'status_code' => 'READY_FOR_QC',
        ]);
    }

    public function down(): void
    {
        $m = DB::connection('tm2sys')->table('sys_app_menu');
        $group = (clone $m)->where('code', 'SALES_PRODEVGRP')->first();

        if (! $group) {
            return;
        }

        (clone $m)->where('code', 'SALES_IMPO')->update(['parent_id' => $group->parent_id, 'label' => 'Pro-Dev', 'sort_order' => $group->sort_order]);
        (clone $m)->where('code', 'SALES_PMKT')->delete();
        (clone $m)->where('id', $group->id)->delete();
    }
};
