<?php

/*
 * REMARK
 * Tujuan       : Mencari transaksi dan master yang masih menunjuk satu supplier, supaya Delete di layar Master Supplier tidak membuat data yatim: bila ada yang menunjuk, supplier hanya dinonaktifkan.
 * Sumber hulu  : Kolom supplier di tm2pur (PO, PV, retur, nota debit, penawaran, arsip pembelian), tm2acc (tagihan vendor), tm2inv (batch, QC, klaim, subkon), tm2prd / tm2sdi / tm2mst (rute proses subkon, bank pegawai, material, daftar harga).
 * Input        : id supplier.
 * Output       : Daftar alasan (label kelompok) yang menahan penghapusan; kosong = boleh dihapus.
 * Dipakai oleh : App\Services\Master\SupplierMasterService::delete (layar md-supplier-form).
 * Status       : Aktif. Diperluas 2026-10-18 (Jo): sebelumnya selalu mengizinkan hapus, padahal tagihan dan PO sudah menunjuk supplier. Tanpa FK lintas-database, jadi dicek di sini.
 */

namespace App\Services\Master;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SupplierGuardService
{
    /** kelompok alasan => [[koneksi, tabel, kolom, ?[kolom => nilai tambahan]], ...] */
    private const REFERENCES = [
        'purchase orders' => [['tm2pur', 'po_order', 'i_supplier_id'], ['tm2pur', 'pr_line_alloc', 'i_supplier_id'], ['tm2pur', 'pq_quotation', 'i_supplier_id']],
        'goods receipts and returns' => [['tm2pur', 'pv_receipt', 'i_supplier_id'], ['tm2pur', 'prt_return', 'i_supplier_id'], ['tm2pur', 'dn_debit_note', 'i_supplier_id']],
        'vendor bills' => [['tm2acc', 'fin_invoice', 'partner_id', ['partner_type' => 'SUPPLIER']], ['tm2acc', 'fin_invoice_receipt', 'supplier_id']],
        'purchase history (archive)' => [['tm2pur', 'po_order_legacy', 'supplier_id'], ['tm2pur', 'po_receive_legacy', 'supplier_id'], ['tm2pur', 'po_invoice_legacy', 'supplier_id'], ['tm2pur', 'po_payment_legacy', 'supplier_id']],
        'inventory records' => [['tm2inv', 'inv_batch', 'supplier_id'], ['tm2inv', 'inv_qc_decision', 'supplier_id'], ['tm2inv', 'inv_supplier_claim', 'supplier_id'], ['tm2inv', 'inv_shortage_request', 'supplier_id'],
            ['tm2inv', 'inv_subcon_issue', 'supplier_id'], ['tm2inv', 'inv_landed_cost', 'supplier_id']],
        'subcontract routings and deliveries' => [['tm2prd', 'prd_wo_operation', 'subcon_supplier_id'], ['tm2sdi', 'iqp_part_process', 'subcon_supplier_id'], ['tm2sdi', 'do_delivery', 'supplier_id'],
            ['tm2mst', 'prod_part_process', 'subcon_supplier_id'], ['tm2mst', 'prod_process', 'default_supplier_id'], ['tm2mst', 'prod_card_process_legacy', 'supplier_id']],
        'supplier bank accounts' => [['tm2mst', 'sup_supplier_bank', 'supplier_id']],
        'material, employee bank and price masters' => [['tm2mst', 'mat_raw', 'suggest_supplier_id'], ['tm2mst', 'emp_bank_account', 'bank_supplier_id'], ['tm2mst', 'sup_paper_price', 'supplier_id'],
            ['tm2mst', 'sup_duplex_price', 'supplier_id'], ['tm2mst', 'sup_single_face_price', 'supplier_id'], ['tm2mst', 'sup_corrugated_rule', 'supplier_id'], ['tm2mst', 'sup_corrugated_index', 'supplier_id']],
    ];

    /** @return list<string> */
    public function blockingReasons(int $supplierId): array
    {
        $reasons = [];
        foreach (self::REFERENCES as $label => $refs) {
            foreach ($refs as $ref) {
                if ($this->used($supplierId, ...$ref)) {
                    $reasons[] = $label;
                    break;
                }
            }
        }

        return $reasons;
    }

    public function canDelete(int $supplierId): bool
    {
        return $this->blockingReasons($supplierId) === [];
    }

    private function used(int $supplierId, string $connection, string $table, string $column, array $where = []): bool
    {
        $schema = Schema::connection($connection);
        if (! $schema->hasTable($table) || ! $schema->hasColumn($table, $column)) {
            return false;
        }

        return DB::connection($connection)->table($table)->where($column, $supplierId)->where($where)->exists();
    }
}
