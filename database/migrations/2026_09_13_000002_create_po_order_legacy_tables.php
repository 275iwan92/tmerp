<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Arsip riwayat PO dari tm2old.pur_po_v2 (header, lebih lengkap pajaknya) +
// pur_po_detail (baris, di-join by po_no=doc_no) -- BUKAN po_order/po_line.
// po_order tidak punya blocker arsitektural spt co_order, tapi tetap dipisah
// ke tabel arsip: 22.976 PO lama statusnya historis (DNOTE/CLOSE/dst dari alur
// ScriptCase lama), bukan dokumen yang perlu ikut alur approval/receiving hidup
// di po_order. Tujuan semata dashboard/laporan (mis. "total AP ke Supplier X").
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2pur')->create('po_order_legacy', function (Blueprint $table) {
            $table->id('id_po_legacy');
            $table->string('legacy_po_no', 30)->unique()->comment('Trace ke tm2old.pur_po.po_no (trimmed) -- kunci idempoten');
            $table->date('po_date')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable()->comment('LOGICAL -> tm2mst.sup_supplier.id_supplier, lintas-DB');
            $table->decimal('subtotal', 18, 2)->nullable();
            $table->decimal('disc_amount', 18, 2)->nullable();
            $table->decimal('ppn_amount', 18, 2)->nullable();
            $table->decimal('grand_total', 18, 2)->nullable();
            $table->string('legacy_status', 20)->nullable()->comment('Nilai po_status APA ADANYA dari sumber (pur_po v1), utk referensi');
            $table->string('doc_status_normalized', 20)->nullable()->comment('DRAFT|APPROVED|CLOSED|CANCELLED|UNKNOWN -- hasil pemetaan terbaik');
            $table->timestamps();
            $table->string('created_by', 100)->nullable();

            $table->index('supplier_id');
            $table->index('po_date');
        });

        Schema::connection('tm2pur')->create('po_order_legacy_detail', function (Blueprint $table) {
            $table->id('id_po_legacy_detail');
            $table->foreignId('po_legacy_id')->constrained('po_order_legacy', 'id_po_legacy')->cascadeOnDelete();
            $table->unsignedBigInteger('material_id')->nullable()->comment('LOGICAL -> tm2mst.mat_raw.material_id, NULL kalau item_code lama tak ketemu crosswalk');
            $table->string('legacy_item_code', 100)->nullable()->comment('item_code APA ADANYA dari sumber, fallback kalau material_id null');
            $table->decimal('qty', 14, 2)->nullable();
            $table->string('uom_code', 10)->nullable();
            $table->decimal('unit_price', 18, 2)->nullable();
            $table->decimal('qty_unitprice', 18, 2)->nullable();
            $table->timestamps();

            $table->index('material_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2pur')->dropIfExists('po_order_legacy_detail');
        Schema::connection('tm2pur')->dropIfExists('po_order_legacy');
    }
};
