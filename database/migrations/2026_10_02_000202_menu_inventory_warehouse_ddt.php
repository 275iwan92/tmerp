<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menu Inventory > Warehouse (user 2026-10-02: "warehouse mapping", "input moving item antar warehouse", "Facility for QR
// Code, Labelling"):
//   Inventory > Warehouse: Warehouse Map | Stock Transfer | Setup (Buildings, Storage Areas, Bins) | Report (Stock by Location)
//   Inventory > Label & QR Code: + Print Location Label; Print Item Label dan Label Print History kini menunjuk layarnya
//   Inventory > Scan Center: Track via QR/Barcode kini menunjuk layarnya
// Print Pallet Label dan Scan Transaction tetap belum dibangun (butuh pallet ber-identitas dan tugas gudang, tahap berikut).
// HANYA database dummy ddt* (menunggu user menilai); tm2sys menyusul setelah disetujui.
return new class extends Migration
{
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

    // Baris yang sudah ada di menu (NOT_BUILT): kode => slug v2.
    private const EXISTING = ['INV_LBL_ITEM2' => 'label-item', 'INV_LBL_ITEM3' => 'inv-label-history', 'INV_SCAN_ITEM2' => 'scan-track'];

    private function sys()
    {
        return DB::connection('tm2sys');
    }

    private function isDummy(): bool
    {
        return str_starts_with($this->sys()->getDatabaseName(), 'ddt');
    }

    private function row(array $v): array
    {
        return $v + ['activate' => 1, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now(), 'doc_slug' => 'warehouselayout'];
    }

    public function up(): void
    {
        $menu = fn () => $this->sys()->table('sys_app_menu');
        $inv = $menu()->where('code', 'INV')->where('menu_type', 'module')->first();
        if (! $this->isDummy() || ! $inv || $menu()->where('code', 'INV_WH')->exists()) {
            return;
        }
        $group = fn (int $parent, string $code, string $label, string $icon, int $sort) => $menu()->insertGetId($this->row(['parent_id' => $parent, 'code' => $code, 'label' => $label,
            'icon' => $icon, 'menu_type' => 'group', 'sort_order' => $sort]));
        $items = function (int $parent, array $list) use ($menu) {
            foreach ($list as $code => [$label, $icon, $slug, $type, $sort]) {
                $menu()->insert($this->row(['parent_id' => $parent, 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'item', 'object_type' => $type,
                    'target' => 'v2:'.$slug, 'sort_order' => $sort]));
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
            $menu()->where('code', $code)->update(['target' => 'v2:'.$slug, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (! $this->isDummy()) {
            return;
        }
        $menu = fn () => $this->sys()->table('sys_app_menu');
        $menu()->whereIn('code', array_merge(array_keys(self::WAREHOUSE), array_keys(self::SETUP), array_keys(self::REPORT), ['INV_LBL_LOC', 'INV_WH_SETUP', 'INV_WH_RPT', 'INV_WH']))->delete();
        $menu()->whereIn('code', array_keys(self::EXISTING))->update(['target' => null, 'status_code' => 'NOT_BUILT']);
    }
};
