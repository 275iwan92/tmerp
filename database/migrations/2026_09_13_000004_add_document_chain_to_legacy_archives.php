<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Rantai telusur antar-dokumen di level BARIS untuk arsip legacy.
//
// Sumber lama menyimpan kunci rantai yang sangat rapat (dicek langsung ke
// tm2old): sdi_dnote_detail.co_detail_id 100%, sdi_cinv_detail.co_detail_id
// 100%, pur_prcv_detail.po_detail_id 100%, pur_po_detail.pr_detail_id 69,7%,
// pur_pr_detail.co_detail_id 65,9%, pur_po_detail.co_detail_id 29,6%.
// Tanpa kolom-kolom ini arsip cuma jadi pulau terpisah; dengan ini bisa
// ditelusuri 1 baris order customer -> PR -> PO -> Penerimaan -> Surat Jalan
// -> Invoice (dan ke Produksi lewat prd_output.co_detail_id nanti).
//
// Tiap tabel detail menyimpan DUA hal:
//   - legacy_*_id  : PK asli di tm2old -- kunci join sumber + jejak audit
//   - *_legacy_detail_id : FK internal hasil resolve ke tabel arsip kita,
//     supaya join dashboard cepat (terindeks) tanpa lookup teks.
// pur_po_detail.pr_no SENGAJA tidak dipakai: 0% terisi (kolom mati).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('co_order_legacy_detail', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_co_detail_id')->nullable()->after('co_legacy_id');
            $table->index('legacy_co_detail_id');
        });

        Schema::connection('tm2pur')->table('pr_requisition_legacy_detail', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_pr_detail_id')->nullable()->after('pr_legacy_id');
            $table->unsignedBigInteger('legacy_co_detail_id')->nullable()->after('legacy_pr_detail_id');
            $table->index('legacy_pr_detail_id');
            $table->index('legacy_co_detail_id');
        });

        Schema::connection('tm2pur')->table('po_order_legacy_detail', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_po_detail_id')->nullable()->after('po_legacy_id');
            $table->unsignedBigInteger('legacy_pr_detail_id')->nullable()->after('legacy_po_detail_id');
            $table->unsignedBigInteger('legacy_co_detail_id')->nullable()->after('legacy_pr_detail_id');
            $table->index('legacy_po_detail_id');
            $table->index('legacy_pr_detail_id');
            $table->index('legacy_co_detail_id');
        });

        Schema::connection('tm2pur')->table('po_receive_legacy_detail', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_prcv_detail_id')->nullable()->after('receive_legacy_id');
            $table->unsignedBigInteger('legacy_po_detail_id')->nullable()->after('legacy_prcv_detail_id');
            $table->index('legacy_po_detail_id');
        });

        Schema::connection('tm2sdi')->table('co_delivery_legacy_detail', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_dnote_detail_id')->nullable()->after('delivery_legacy_id');
            $table->unsignedBigInteger('legacy_co_detail_id')->nullable()->after('legacy_dnote_detail_id');
            $table->index('legacy_co_detail_id');
        });

        Schema::connection('tm2sdi')->table('co_invoice_legacy_detail', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_cinv_detail_id')->nullable()->after('invoice_legacy_id');
            $table->unsignedBigInteger('legacy_co_detail_id')->nullable()->after('legacy_cinv_detail_id');
            $table->index('legacy_co_detail_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('co_order_legacy_detail', fn (Blueprint $t) => $t->dropColumn('legacy_co_detail_id'));
        Schema::connection('tm2pur')->table('pr_requisition_legacy_detail', fn (Blueprint $t) => $t->dropColumn(['legacy_pr_detail_id', 'legacy_co_detail_id']));
        Schema::connection('tm2pur')->table('po_order_legacy_detail', fn (Blueprint $t) => $t->dropColumn(['legacy_po_detail_id', 'legacy_pr_detail_id', 'legacy_co_detail_id']));
        Schema::connection('tm2pur')->table('po_receive_legacy_detail', fn (Blueprint $t) => $t->dropColumn(['legacy_prcv_detail_id', 'legacy_po_detail_id']));
        Schema::connection('tm2sdi')->table('co_delivery_legacy_detail', fn (Blueprint $t) => $t->dropColumn(['legacy_dnote_detail_id', 'legacy_co_detail_id']));
        Schema::connection('tm2sdi')->table('co_invoice_legacy_detail', fn (Blueprint $t) => $t->dropColumn(['legacy_cinv_detail_id', 'legacy_co_detail_id']));
    }
};
