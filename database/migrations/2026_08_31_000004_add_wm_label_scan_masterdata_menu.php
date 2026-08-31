<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// User minta redesain sidebar Warehouse Management (WM = label topbar utk
// modul INV, lihat migration 2026_08_31_000002) 2026-08-31: tambah grup
// Label & QR Code + Scan Center (placeholder roadmap, belum ada layar --
// masih nunggu keputusan desain tabel pallet, lihat diskusi sesi ini) dan
// grup "Master Data Terkait" (LIVE, murni shortcut ke route MDATA yang
// sudah ada -- master.raw-material/master.mat-category/master.produk/
// master.warehouse -- BUKAN duplikasi data, cuma kemudahan akses dari
// dalam modul WM). Ditempatkan sort_order 33/36/38, di antara Input
// (10-30) dan Laporan (40-60) existing, ikut pola sys_app_menu.id manual
// MAX(id)+1 (bukan auto_increment) dari migration split_inventory_menu_by_stock_class.
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2sys');
        $nextId = (int) $db->table('sys_app_menu')->max('id') + 1;

        $insertGroup = function (string $code, string $label, int $sort, string $status) use ($db, &$nextId) {
            if (! $db->table('sys_app_menu')->where('code', $code)->exists()) {
                $db->table('sys_app_menu')->insert([
                    'id' => $nextId++,
                    'parent_id' => 3, // module INV
                    'code' => $code,
                    'label' => $label,
                    'menu_type' => 'group',
                    'sort_order' => $sort,
                    'activate' => true,
                    'status_code' => $status,
                    'status_updated_by' => 'claude_code',
                    'status_updated_at' => now(),
                    'created_at' => now(),
                ]);
            }

            return $db->table('sys_app_menu')->where('code', $code)->value('id');
        };

        $insertItem = function (string $code, int $parentId, string $label, string $objType, int $sort, string $status) use ($db, &$nextId) {
            if (! $db->table('sys_app_menu')->where('code', $code)->exists()) {
                $db->table('sys_app_menu')->insert([
                    'id' => $nextId++,
                    'parent_id' => $parentId,
                    'code' => $code,
                    'label' => $label,
                    'menu_type' => 'item',
                    'object_type' => $objType,
                    'sort_order' => $sort,
                    'activate' => true,
                    'status_code' => $status,
                    'status_updated_by' => 'claude_code',
                    'status_updated_at' => now(),
                    'created_at' => now(),
                ]);
            }
        };

        // ================= Label & QR Code (placeholder) =================
        $lblGroupId = $insertGroup('INV_LBL', 'Label & QR Code', 33, 'NOT_BUILT');
        $insertItem('INV_LBL_ITEM1', $lblGroupId, 'Cetak Label Pallet (QR)', 'form', 10, 'NOT_BUILT');
        $insertItem('INV_LBL_ITEM2', $lblGroupId, 'Cetak Label Barang (QR)', 'form', 20, 'NOT_BUILT');
        $insertItem('INV_LBL_ITEM3', $lblGroupId, 'Riwayat Cetak Label', 'report', 30, 'NOT_BUILT');

        // ================= Scan Center (placeholder) =================
        $scanGroupId = $insertGroup('INV_SCAN', 'Scan Center', 36, 'NOT_BUILT');
        $insertItem('INV_SCAN_ITEM1', $scanGroupId, 'Pindai Transaksi (Kamera HP)', 'form', 10, 'NOT_BUILT');
        $insertItem('INV_SCAN_ITEM2', $scanGroupId, 'Lacak via QR/Barcode', 'form', 20, 'NOT_BUILT');

        // ================= Master Data Terkait (LIVE shortcut) =================
        $refGroupId = $insertGroup('INV_REF', 'Master Data Terkait', 38, 'READY_FOR_QC');
        $insertItem('INV_REF_RAW', $refGroupId, 'Master Bahan Baku', 'grid', 10, 'READY_FOR_QC');
        $insertItem('INV_REF_MATCAT', $refGroupId, 'Kategori Bahan Baku', 'grid', 20, 'READY_FOR_QC');
        $insertItem('INV_REF_PROD', $refGroupId, 'Master Produk', 'grid', 30, 'READY_FOR_QC');
        $insertItem('INV_REF_WH', $refGroupId, 'Master Gudang & Lokasi', 'grid', 40, 'READY_FOR_QC');
    }

    public function down(): void
    {
        $db = DB::connection('tm2sys');

        $db->table('sys_app_menu')->whereIn('code', [
            'INV_LBL_ITEM1', 'INV_LBL_ITEM2', 'INV_LBL_ITEM3', 'INV_LBL',
            'INV_SCAN_ITEM1', 'INV_SCAN_ITEM2', 'INV_SCAN',
            'INV_REF_RAW', 'INV_REF_MATCAT', 'INV_REF_PROD', 'INV_REF_WH', 'INV_REF',
        ])->delete();
    }
};
