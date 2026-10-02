<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Laporan stok semua kelas dipasang juga di tmerp.test (user 2026-10-03: "apply to mockup.test and tmerp.test").
// Database yang belum punya grup Inventory > Warehouse > Report (tm2sys: menu Warehouse menunggu penilaian user, dan menu v1
// hanya dua tingkat) mendapat SATU grup "Stock Reports" langsung di bawah Inventory, berisi tujuh laporan itu. Database yang
// sudah punya grupnya (ddtsys, migrasi 2026_10_02_000220) tidak diubah. Bila menu Warehouse kelak dipasang di tm2sys, grup
// ini tinggal dipindah ke bawahnya (kodenya sama: INV_WH_RPT).
// Keadaan 2026-10-03: menu Warehouse dipasang di tm2sys pada hari yang sama (2026_10_03_000010_menu_inventory_warehouse_install),
// jadi di kedua database laporannya ada di Inventory > Warehouse > Report dan migration ini tidak membuat apa-apa; ia tetap
// ada sebagai cadangan untuk database yang belum punya menu Warehouse.
return new class extends Migration
{
    // kode => [label, ikon, slug v2, urutan]
    private const REPORTS = [
        'INV_WH_RPT_LOC' => ['Stock by Location', 'fa-solid fa-map-pin', 'inv-rpt-location', 10],
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
        $inv = $menu()->where('code', 'INV')->where('menu_type', 'module')->first();
        if (! $inv || $menu()->where('code', 'INV_WH_RPT')->exists()) {
            return;
        }
        $stamp = ['activate' => 1, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now(), 'doc_slug' => 'inventoryreport'];
        // Sesudah Semi Finished Goods (30), sebelum Label & QR Code (33).
        $group = $menu()->insertGetId(['parent_id' => $inv->id, 'code' => 'INV_WH_RPT', 'label' => 'Stock Reports', 'icon' => 'fa-solid fa-chart-line', 'menu_type' => 'group', 'sort_order' => 32] + $stamp);
        foreach (self::REPORTS as $code => [$label, $icon, $slug, $sort]) {
            $menu()->insert(['parent_id' => $group, 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'item', 'object_type' => 'report', 'target' => 'v2:'.$slug,
                'sort_order' => $sort] + $stamp);
        }
    }

    public function down(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $group = $menu()->where('code', 'INV_WH_RPT')->where('label', 'Stock Reports')->first();
        if ($group) {
            $menu()->where('parent_id', $group->id)->delete();
            $menu()->where('id', $group->id)->delete();
        }
    }
};
