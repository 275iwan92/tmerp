<?php

use Database\Seeders\StockTransferApprovalSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menu Inventory & Warehouse di SEMUA database (user 2026-10-03: "apply to mockup.test and tmerp.test"). Migration 000202
// dan 000205 hanya berjalan di ddt*; migration ini memasang baris yang sama di database yang belum punya (tm2sys) dan
// tidak mengubah yang sudah ada:
//   Inventory > Warehouse: Warehouse Map | Stock Transfer | Setup (Buildings, Storage Areas, Bins) | Report (Stock by Location)
//   Inventory > Label & QR Code: Print Location Label, Print Pallet Label, Print Item Label, Label Print History
//   Inventory > Scan Center: Track via QR/Barcode
// Approval Stock Transfer (Setting Approval TF tidak aktif + menu Approval Center) dipasang bila Setting Approval v2 sudah
// ada di database itu (Database\Seeders\StockTransferApprovalSeeder; bisa dijalankan lagi nanti).
return new class extends Migration
{
    private const BY = 'claude (v2 build)';

    // kode => [label, ikon, slug v2, jenis objek, urutan]
    private const WAREHOUSE = [
        'INV_WH_MAP' => ['Warehouse Map', 'fa-solid fa-map-location-dot', 'warehouse-map', 'form', 10],
        'INV_WH_TF' => ['Stock Transfer', 'fa-solid fa-right-left', 'stock-transfer', 'form', 20],
    ];

    private const SETUP = [
        'INV_WH_BLD' => ['Buildings', 'fa-solid fa-building', 'wh-buildings', 'grid', 10],
        'INV_WH_ZONE' => ['Storage Areas', 'fa-solid fa-vector-square', 'wh-zones', 'grid', 20],
        'INV_WH_BIN' => ['Bins', 'fa-solid fa-table-cells', 'wh-bins', 'grid', 30],
    ];

    private const REPORT = [
        'INV_WH_RPT_LOC' => ['Stock by Location', 'fa-solid fa-map-pin', 'inv-rpt-location', 'report', 10],
    ];

    // Baris yang sudah ada di menu tanpa layar: kode => slug v2.
    private const EXISTING = ['INV_LBL_ITEM1' => 'label-pallet', 'INV_LBL_ITEM2' => 'label-item', 'INV_LBL_ITEM3' => 'inv-label-history', 'INV_SCAN_ITEM2' => 'scan-track'];

    private function row(array $v): array
    {
        return $v + ['activate' => 1, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => self::BY, 'status_updated_at' => now(), 'created_at' => now(), 'doc_slug' => 'warehouselayout'];
    }

    public function up(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $inv = $menu()->where('code', 'INV')->where('menu_type', 'module')->first();
        if (! $inv) {
            return;
        }
        $group = fn (int $parent, string $code, string $label, string $icon, int $sort) => $menu()->where('code', $code)->value('id')
            ?? $menu()->insertGetId($this->row(['parent_id' => $parent, 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'group', 'sort_order' => $sort]));
        $items = function (int $parent, array $list) use ($menu) {
            foreach ($list as $code => [$label, $icon, $slug, $type, $sort]) {
                if (! $menu()->where('code', $code)->exists()) {
                    $menu()->insert($this->row(['parent_id' => $parent, 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'item', 'object_type' => $type,
                        'target' => 'v2:'.$slug, 'sort_order' => $sort]));
                }
            }
        };

        $wh = $group($inv->id, 'INV_WH', 'Warehouse', 'fa-solid fa-warehouse', 5);
        $items($wh, self::WAREHOUSE);
        $items($group($wh, 'INV_WH_SETUP', 'Setup', 'fa-solid fa-sliders', 800), self::SETUP);
        $items($group($wh, 'INV_WH_RPT', 'Report', 'fa-solid fa-chart-line', 900), self::REPORT);

        if ($lbl = $menu()->where('code', 'INV_LBL')->first()) {
            $items($lbl->id, ['INV_LBL_LOC' => ['Print Location Label (QR)', 'fa-solid fa-location-dot', 'label-location', 'form', 5]]);
        }
        foreach (self::EXISTING as $code => $slug) {
            $menu()->where('code', $code)->whereNull('target')->update(['target' => 'v2:'.$slug, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => self::BY, 'status_updated_at' => now()]);
        }
        StockTransferApprovalSeeder::install();
    }

    public function down(): void
    {
        // Menu dibiarkan: di ddt* baris ini milik migration 000202 / 000205.
    }
};
