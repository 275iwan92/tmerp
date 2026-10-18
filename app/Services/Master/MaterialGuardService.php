<?php

/*
 * REMARK
 * Tujuan       : Mencari transaksi dan master yang masih menunjuk satu item bahan baku (mat_raw), supaya Delete di layar Master Raw Material tidak membuat data yatim: bila ada yang menunjuk atau stok masih ada, item hanya dinonaktifkan.
 * Sumber hulu  : Kolom item di tm2pur (PR, PQ, PO, PV, retur, arsip pembelian), tm2inv (stok, batch, booking, transfer, pick list, klaim), tm2prd (bahan WO), tm2sdi (bahan part Inquiry), tm2mst (bahan part, alternatif, arsip kartu), tm2mnt (suku cadang).
 * Input        : id item (material_id).
 * Output       : usage() = jumlah baris penunjuk per kelompok; blockingReasons() = label kelompok yang menahan penghapusan (kosong = boleh dihapus).
 * Dipakai oleh : App\Services\Master\MaterialMasterService::delete dan layar md-raw-form (strip "Used in").
 * Status       : Aktif. Dibuat 2026-10-18 (Jo): v1 menghapus item tanpa cek sama sekali, padahal arsip PO / PV / kartu produk menunjuk material_id. Tanpa FK lintas-database, jadi dicek di sini.
 */

namespace App\Services\Master;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MaterialGuardService
{
    /** kelompok => [[koneksi, tabel, kolom, ?[kolom => nilai tambahan]], ...] */
    private const REFERENCES = [
        'purchase documents' => [['tm2pur', 'pr_line', 'item_id', ['i_item_source' => 'MASTER_ITEM']], ['tm2pur', 'pq_line', 'item_id'], ['tm2pur', 'po_line', 'item_id'], ['tm2pur', 'pv_line', 'item_id'], ['tm2pur', 'prt_line', 'item_id']],
        'purchase history (archive)' => [['tm2pur', 'pr_requisition_legacy_detail', 'material_id'], ['tm2pur', 'po_order_legacy_detail', 'material_id'], ['tm2pur', 'po_receive_legacy_detail', 'material_id'], ['tm2pur', 'po_invoice_legacy_detail', 'material_id']],
        'stock and warehouse records' => [['tm2inv', 'rm_stock_d', 'material_id'], ['tm2inv', 'rm_booking_d', 'material_id'], ['tm2inv', 'rm_stockcard', 'material_id'], ['tm2inv', 'wh_pick_list_line', 'material_id'],
            ['tm2inv', 'inv_batch', 'item_id', ['stock_class' => 'RM']], ['tm2inv', 'inv_transfer_line', 'item_id', ['stock_class' => 'RM']], ['tm2inv', 'wh_task', 'item_id', ['stock_class' => 'RM']],
            ['tm2inv', 'inv_stock_layer', 'i_item_id', ['i_stock_class' => 'RM']], ['tm2inv', 'inv_item_avg_open', 'item_id', ['stock_class' => 'RM']], ['tm2inv', 'inv_pallet', 'item_id', ['stock_class' => 'RM']],
            ['tm2inv', 'inv_obsolete_provision_line', 'item_id', ['stock_class' => 'RM']], ['tm2inv', 'inv_subcon_issue_line', 'item_id', ['stock_class' => 'RM']]],
        'QC, shortage and supplier claims' => [['tm2inv', 'inv_qc_risk_class', 'material_id'], ['tm2inv', 'inv_shortage_request', 'material_id'], ['tm2inv', 'inv_supplier_claim', 'material_id']],
        'work orders and planning' => [['tm2prd', 'prd_wo_material', 'material_id'], ['tm2prd', 'prd_wo_material', 'substitute_material_id'], ['tm2prd', 'prd_mrp_requirement', 'material_id']],
        'inquiry, product and master card materials' => [['tm2sdi', 'iqp_part_material', 'rawmat_id'], ['tm2mst', 'prod_part_material', 'material_id'], ['tm2mst', 'prod_material_alt', 'material_id'], ['tm2mst', 'prod_material_alt', 'alt_material_id'],
            ['tm2mst', 'mat_usage_standard', 'default_material_id'], ['tm2mst', 'prod_card_rawmat_legacy', 'material_id'], ['tm2mst', 'prod_product_component_legacy', 'material_id']],
        'maintenance spare parts' => [['tm2mnt', 'mnt_asset_part', 'material_id'], ['tm2mnt', 'mnt_order_part', 'material_id'], ['tm2mnt', 'mnt_plan_part', 'material_id']],
    ];

    /** @return array<string, int> kelompok => jumlah baris penunjuk (hanya yang > 0) */
    public function usage(int $materialId): array
    {
        $out = [];
        foreach (self::REFERENCES as $label => $refs) {
            $n = 0;
            foreach ($refs as $ref) {
                $n += $this->count($materialId, ...$ref);
            }
            if ($n > 0) {
                $out[$label] = $n;
            }
        }

        return $out;
    }

    /** @return list<string> */
    public function blockingReasons(int $materialId): array
    {
        return array_keys($this->usage($materialId));
    }

    private function count(int $materialId, string $connection, string $table, string $column, array $where = []): int
    {
        $schema = Schema::connection($connection);
        if (! $schema->hasTable($table) || ! $schema->hasColumn($table, $column)) {
            return 0;
        }

        return DB::connection($connection)->table($table)->where($column, $materialId)->where($where)->count();
    }
}
