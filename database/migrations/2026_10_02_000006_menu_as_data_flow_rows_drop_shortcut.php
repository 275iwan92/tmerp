<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Menu = data (cara ScriptCase, user 2026-10-02): layar yang sama boleh muncul di banyak menu cukup dengan baris ber-target
// sama, jadi kolom shortcut_of tidak diperlukan (3 pintasan master di PREORDER sudah baris biasa). Grup "Flow" yang tadinya
// ditulis di kode (AppMenu::extraGroups) jadi baris menu juga: target berawalan '/' = alamat di dalam aplikasi.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    // modul => [kode grup, [kode item, label, ikon, alamat]...]
    private const FLOWS = [
        'SALES' => ['SALES_FLOWGRP', [['F-SALES', 'Sales Flow: Inquiry → CPDEL', 'fa-solid fa-diagram-project', '/v2/flow/SALES']]],
        'PREORDER' => ['PREORDER_FLOWGRP', [
            ['F-COSTING', 'Costing Flow: ProDev → ProCost → Publish', 'fa-solid fa-diagram-project', '/v2/flow/COSTING'],
            ['G-PRODUCT', 'Guide: Master Product & Revision', 'fa-solid fa-book', '/v2/pedoman/product-master'],
        ]],
    ];

    public function up(): void
    {
        $db = DB::connection('tm2sys');
        if (Schema::connection('tm2sys')->hasColumn('sys_app_menu', 'shortcut_of')) {
            Schema::connection('tm2sys')->table('sys_app_menu', fn ($t) => $t->dropColumn('shortcut_of'));
        }
        foreach (self::FLOWS as $module => [$grpCode, $items]) {
            $mod = $db->table('sys_app_menu')->where('code', $module)->value('id');
            if (! $mod || $db->table('sys_app_menu')->where('code', $grpCode)->exists()) {
                continue;
            }
            $grp = $db->table('sys_app_menu')->insertGetId(['parent_id' => $mod, 'code' => $grpCode, 'label' => 'Flow',
                'icon' => 'fa-solid fa-diagram-project', 'menu_type' => 'group', 'sort_order' => 950, 'activate' => 1, 'status_code' => 'READY_FOR_QC']);
            foreach ($items as $n => [$code, $label, $icon, $url]) {
                $db->table('sys_app_menu')->insert(['parent_id' => $grp, 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'item',
                    'object_type' => 'report', 'target' => $url, 'sort_order' => ($n + 1) * 10, 'activate' => 1, 'status_code' => 'READY_FOR_QC']);
            }
        }
    }

    public function down(): void
    {
        $db = DB::connection('tm2sys');
        foreach (self::FLOWS as [$grpCode, $items]) {
            $db->table('sys_app_menu')->whereIn('code', [...array_column($items, 0), $grpCode])->delete();
        }
    }
};
