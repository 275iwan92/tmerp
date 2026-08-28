<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// production_scheduling_tier1.md §9.3 -- breakdown->subkontrak wajib PO resmi
// tipe khusus SUBCONTRACT, terhubung purchase_requisition/purchase_order yang
// sudah live (tm2pur). Kedua kolom genuinely ENUM (dicek via SHOW COLUMNS
// sebelum nulis migration ini) -- pr_requisition.i_source cuma MANUAL/MRP,
// po_order.i_po_type cuma LOCAL/IMPORT. Tambah nilai baru, urutan lama
// dipertahankan (MySQL ALTER ENUM re-declare seluruh daftar).
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2pur')->statement(
            "ALTER TABLE `pr_requisition` MODIFY `i_source` ENUM('MANUAL','MRP','SUBCONTRACT') NOT NULL DEFAULT 'MANUAL'"
        );
        DB::connection('tm2pur')->statement(
            "ALTER TABLE `po_order` MODIFY `i_po_type` ENUM('LOCAL','IMPORT','SUBCONTRACT') NOT NULL DEFAULT 'LOCAL'"
        );
    }

    public function down(): void
    {
        DB::connection('tm2pur')->statement(
            "ALTER TABLE `po_order` MODIFY `i_po_type` ENUM('LOCAL','IMPORT') NOT NULL DEFAULT 'LOCAL'"
        );
        DB::connection('tm2pur')->statement(
            "ALTER TABLE `pr_requisition` MODIFY `i_source` ENUM('MANUAL','MRP') NOT NULL DEFAULT 'MANUAL'"
        );
    }
};
