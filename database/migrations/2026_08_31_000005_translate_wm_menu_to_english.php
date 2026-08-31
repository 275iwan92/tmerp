<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// User 2026-08-31: sidebar Warehouse Management (WM) harus bahasa Inggris,
// dan caption grup Input disederhanakan tanpa prefix "Input --" (cukup
// "Raw Material"/"Finished Goods"/"Semi Finished Goods"), krn item di
// dalamnya sudah jelas transaksional (Stock In/Out/dst). Grup Laporan
// dipertahankan pola "Reports -- <kelas>" (perlu pembeda dari grup RM/FG/
// SFG yang non-Laporan, caption sama persis akan bentrok makna). Bagian
// dari kebijakan menu-Inggris bertahap (docs project_english_menu_policy) --
// scope migration ini CUMA modul INV/WM, jangan disamaratakan modul lain.
return new class extends Migration
{
    private array $labels = [
        // Input groups -> simplified class name only
        'INV_TRX_RM' => 'Raw Material',
        'INV_TRX_FG' => 'Finished Goods',
        'INV_TRX_SFG' => 'Semi Finished Goods',

        // Input items
        'INV_RM_ITEM4' => 'Stock Adjustment & Physical Count',
        'INV_FG_ITEM4' => 'Stock Adjustment & Physical Count',
        'INV_SFG_ITEM4' => 'Stock Adjustment & Physical Count',
        'INV_RM_ITEM5' => 'Adjustment Approval',
        'INV_FG_ITEM5' => 'Adjustment Approval',
        'INV_SFG_ITEM5' => 'Adjustment Approval',

        // Reports groups
        'INV_RPT_RM' => 'Reports — Raw Material',
        'INV_RPT_FG' => 'Reports — Finished Goods',
        'INV_RPT_SFG' => 'Reports — Semi Finished Goods',

        // Report items
        'INV_RAW_RM' => 'Stock Balance',
        'INV_RAW_FG' => 'Stock Balance',
        'INV_RAW_SFG' => 'Stock Balance',
        'INV_CARD_RM' => 'Stock Card',
        'INV_CARD_FG' => 'Stock Card',
        'INV_CARD_SFG' => 'Stock Card',

        // Master Data Terkait group + items
        'INV_REF' => 'Related Master Data',
        'INV_REF_RAW' => 'Raw Material Master',
        'INV_REF_MATCAT' => 'Material Category',
        'INV_REF_PROD' => 'Product Master',
        'INV_REF_WH' => 'Warehouse & Location',

        // Label & QR Code items
        'INV_LBL_ITEM1' => 'Print Pallet Label (QR)',
        'INV_LBL_ITEM2' => 'Print Item Label (QR)',
        'INV_LBL_ITEM3' => 'Label Print History',

        // Scan Center items
        'INV_SCAN_ITEM1' => 'Scan Transaction (Mobile Camera)',
        'INV_SCAN_ITEM2' => 'Track via QR/Barcode',
    ];

    private array $original = [
        'INV_TRX_RM' => 'Input — Bahan Baku & Bahan Pembantu',
        'INV_TRX_FG' => 'Input — Barang Jadi',
        'INV_TRX_SFG' => 'Input — Barang Setengah Jadi',
        'INV_RM_ITEM4' => 'Stock Adjustment & Opname',
        'INV_FG_ITEM4' => 'Stock Adjustment & Opname',
        'INV_SFG_ITEM4' => 'Stock Adjustment & Opname',
        'INV_RM_ITEM5' => 'Persetujuan Penyesuaian',
        'INV_FG_ITEM5' => 'Persetujuan Penyesuaian',
        'INV_SFG_ITEM5' => 'Persetujuan Penyesuaian',
        'INV_RPT_RM' => 'Laporan — Bahan Baku & Bahan Pembantu',
        'INV_RPT_FG' => 'Laporan — Barang Jadi',
        'INV_RPT_SFG' => 'Laporan — Barang Setengah Jadi',
        'INV_RAW_RM' => 'Master Stok',
        'INV_RAW_FG' => 'Master Stok',
        'INV_RAW_SFG' => 'Master Stok',
        'INV_CARD_RM' => 'Kartu Stok',
        'INV_CARD_FG' => 'Kartu Stok',
        'INV_CARD_SFG' => 'Kartu Stok',
        'INV_REF' => 'Master Data Terkait',
        'INV_REF_RAW' => 'Master Bahan Baku',
        'INV_REF_MATCAT' => 'Kategori Bahan Baku',
        'INV_REF_PROD' => 'Master Produk',
        'INV_REF_WH' => 'Master Gudang & Lokasi',
        'INV_LBL_ITEM1' => 'Cetak Label Pallet (QR)',
        'INV_LBL_ITEM2' => 'Cetak Label Barang (QR)',
        'INV_LBL_ITEM3' => 'Riwayat Cetak Label',
        'INV_SCAN_ITEM1' => 'Pindai Transaksi (Kamera HP)',
        'INV_SCAN_ITEM2' => 'Lacak via QR/Barcode',
    ];

    public function up(): void
    {
        $db = DB::connection('tm2sys');
        foreach ($this->labels as $code => $label) {
            $db->table('sys_app_menu')->where('code', $code)->update(['label' => $label]);
        }
    }

    public function down(): void
    {
        $db = DB::connection('tm2sys');
        foreach ($this->original as $code => $label) {
            $db->table('sys_app_menu')->where('code', $code)->update(['label' => $label]);
        }
    }
};
