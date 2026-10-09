<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menu 2.01 dipisah menjadi dua modul sesuai docs/mockups/menu_v201_split.jpg: INVENTORY (dokumen, nilai, pemilik) dan
// WAREHOUSE (fisik: peta, penerimaan, pick, transfer, hitung, label, setup). Hanya memindahkan baris (parent_id, urutan) dan
// menambah kelompok / modul; kode item dan target layar TIDAK berubah. Modul WH memakai required_object INV, jadi hak akses
// sama dengan Inventory. Idempoten. Jalankan hanya dengan --env=mockup sampai rilis ke tm2*.
return new class extends Migration
{
    private const BY = 'claude (menu v2.01 split)';

    public function up(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $inv = $menu()->where('code', 'INV')->where('menu_type', 'module')->first();
        if (! $inv) {
            return;
        }

        $row = fn (array $v) => $v + ['activate' => 1, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => self::BY, 'status_updated_at' => now(), 'created_at' => now(),
            'target_kind' => 'app', 'doc_slug' => 'warehouselayout'];
        $id = fn (string $code) => $menu()->where('code', $code)->value('id');
        $group = fn (int $parent, string $code, string $label, string $icon, int $sort) => $id($code)
            ?? $menu()->insertGetId($row(['parent_id' => $parent, 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'group', 'sort_order' => $sort]));
        // Pindahkan baris yang sudah ada: kode => urutan baru.
        $move = function (int $parent, array $codes) use ($menu) {
            foreach ($codes as $code => $sort) {
                $menu()->where('code', $code)->update(['parent_id' => $parent, 'sort_order' => $sort]);
            }
        };

        $menu()->where('id', $inv->id)->update(['short_label' => 'INV', 'tooltip' => 'Inventory (stock, value, ownership)']);
        $whId = $id('WH') ?? $menu()->insertGetId($row(['parent_id' => null, 'code' => 'WH', 'label' => 'Warehouse', 'short_label' => 'WH', 'tooltip' => 'Warehouse (physical handling)',
            'icon' => 'fa-solid fa-warehouse', 'menu_type' => 'module', 'sort_order' => 31, 'required_object' => 'INV']));

        // WAREHOUSE
        $move($group($whId, 'WH_MAP', 'Map & Monitor', 'fa-solid fa-map-location-dot', 10), ['INV_WH_MAP' => 10, 'INV_WH_STACK' => 20, 'INV_WH_RPT_LOC' => 30]);
        $move($group($whId, 'WH_RCV', 'Receiving & Putaway', 'fa-solid fa-dolly', 20), ['INV_WH_RCV' => 10]);
        $move($group($whId, 'WH_PICK', 'Pick List', 'fa-solid fa-clipboard-list', 30), ['INV_WH_PICK' => 10]);
        $move($group($whId, 'WH_TRF', 'Transfer', 'fa-solid fa-right-left', 40), ['INV_WH_TRANSIT' => 10]);
        $move($group($whId, 'WH_COUNT', 'Count', 'fa-solid fa-list-check', 50), ['INV_WH_COUNT' => 10]);
        // Labels & Scan = grup Label & QR Code yang ada (beserta Report-nya) + isi Scan Center.
        $menu()->where('code', 'INV_LBL')->update(['parent_id' => $whId, 'label' => 'Labels & Scan', 'sort_order' => 60]);
        $move($id('INV_LBL'), ['INV_SCAN_ITEM1' => 20, 'INV_SCAN_ITEM2' => 30]);
        $move($whId, ['INV_TOOLS' => 70, 'INV_WH_SETUP' => 90]);
        $move($id('INV_WH_SETUP'), ['INV_REF_WH' => 40]);

        // INVENTORY
        $move($group($inv->id, 'INV_TRF_DOC', 'Stock Transfer', 'fa-solid fa-right-left', 35), ['INV_WH_TF' => 10, 'INV_WH_TF_APPR' => 20]);
        $move($group($inv->id, 'INV_SUBCON', 'Subcontract', 'fa-solid fa-industry', 40), ['INV_WH_SUBCON' => 10, 'INV_WH_RPT_VENDOR' => 20]);
        $move($group($inv->id, 'INV_CUSTMAT', 'Customer Material', 'fa-solid fa-box-open', 45), ['INV_WH_RPT_CUSTMAT' => 10]);
        $menu()->where('code', 'INV_BQ')->update(['sort_order' => 50]);
        $menu()->where('code', 'INV_WH_RPT')->update(['parent_id' => $inv->id, 'label' => 'Reports', 'sort_order' => 60]);
        $menu()->where('code', 'INV_REF')->update(['sort_order' => 70]);

        // Grup lama yang kosong dimatikan (tidak menjadi yatim di menu).
        foreach (['INV_WH', 'INV_SCAN'] as $code) {
            if ($gid = $id($code)) {
                if (! $menu()->where('parent_id', $gid)->exists()) {
                    $menu()->where('id', $gid)->update(['activate' => 0]);
                }
            }
        }
    }

    public function down(): void
    {
        // Pemindahan menu tidak dibalik.
    }
};
