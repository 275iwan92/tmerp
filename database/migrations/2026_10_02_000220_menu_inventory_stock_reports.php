<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menu Inventory > Warehouse > Report (user 2026-10-02: "build all reports related with inventory movement, stock card and
// other reports ... stockcard, summary all stocks, recapitulation of all items, inspection stocks, report items group by
// location warehouse, loss and gain all items every monthly"): laporan stok SEMUA kelas (RM / SFG / FG) di samping
// Stock by Location yang sudah ada. Laporan per kelas (Stock Balance, Stock Card, Slow Moving Item) tetap di menu kelasnya.
// Berjalan di database yang sudah punya grup Inventory > Warehouse > Report (sekarang ddt*; tm2sys menyusul bersama menu
// Warehouse, backlog 05.20).
return new class extends Migration
{
    // kode => [label, ikon, slug v2, urutan]
    private const REPORTS = [
        'INV_WH_RPT_CARD' => ['Stock Card', 'fa-solid fa-clipboard-list', 'inv-rpt-card', 20],
        'INV_WH_RPT_RECAP' => ['Stock Recapitulation', 'fa-solid fa-table-list', 'inv-rpt-recap', 30],
        'INV_WH_RPT_SUM' => ['Stock Summary', 'fa-solid fa-layer-group', 'inv-rpt-summary', 40],
        'INV_WH_RPT_OPN' => ['Stock Opname', 'fa-solid fa-clipboard-check', 'inv-rpt-opname', 50],
        'INV_WH_RPT_LG' => ['Stock Loss & Gain', 'fa-solid fa-scale-unbalanced', 'inv-rpt-loss-gain', 60],
        'INV_WH_RPT_QC' => ['Stock in Inspection', 'fa-solid fa-magnifying-glass-chart', 'inv-rpt-inspection', 70],
    ];

    public function up(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $group = $menu()->where('code', 'INV_WH_RPT')->first();
        if (! $group) {
            return;
        }
        foreach (self::REPORTS as $code => [$label, $icon, $slug, $sort]) {
            if ($menu()->where('code', $code)->exists()) {
                continue;
            }
            $menu()->insert(['parent_id' => $group->id, 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'item', 'object_type' => 'report', 'target' => 'v2:'.$slug,
                'sort_order' => $sort, 'activate' => 1, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now(),
                'doc_slug' => 'inventoryreport']);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', array_keys(self::REPORTS))->delete();
    }
};
