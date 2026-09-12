<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Arsip riwayat 5 modul lanjutan dari tm2old, pola sama dgn co_order_legacy/
// po_order_legacy (migration 2026_09_13_00000{1,2}): read-only, tujuan
// dashboard/laporan, TIDAK terhubung ke tabel transaksi hidup (pr_requisition,
// pv_receipt, dst) yang desainnya utk alur baru bukan arsip histori.
return new class extends Migration
{
    public function up(): void
    {
        // 1. Purchase Requisition -- tm2old.pur_pr(_detail)
        Schema::connection('tm2pur')->create('pr_requisition_legacy', function (Blueprint $table) {
            $table->id('id_pr_legacy');
            $table->string('legacy_pr_no', 30)->unique();
            $table->date('pr_date')->nullable();
            $table->string('department', 50)->nullable();
            $table->string('legacy_status', 20)->nullable();
            $table->string('doc_status_normalized', 20)->nullable();
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
        });
        Schema::connection('tm2pur')->create('pr_requisition_legacy_detail', function (Blueprint $table) {
            $table->id('id_pr_legacy_detail');
            $table->foreignId('pr_legacy_id')->constrained('pr_requisition_legacy', 'id_pr_legacy')->cascadeOnDelete();
            $table->unsignedBigInteger('material_id')->nullable();
            $table->string('legacy_item_code', 100)->nullable();
            $table->decimal('qty', 14, 2)->nullable();
            $table->string('uom_code', 10)->nullable();
            $table->decimal('est_unitprice', 18, 2)->nullable();
            $table->timestamps();
            $table->index('material_id');
        });

        // 2. Purchase Receive -- tm2old.pur_prcv(_detail)
        Schema::connection('tm2pur')->create('po_receive_legacy', function (Blueprint $table) {
            $table->id('id_receive_legacy');
            $table->string('legacy_prcv_no', 30)->unique();
            $table->date('prcv_date')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('po_legacy_id')->nullable()->comment('-> po_order_legacy.id_po_legacy, NULL kalau po_no sumber tak ketemu');
            $table->string('legacy_po_no', 30)->nullable();
            $table->string('legacy_status', 20)->nullable();
            $table->string('doc_status_normalized', 20)->nullable();
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->index('supplier_id');
            $table->index('po_legacy_id');
        });
        Schema::connection('tm2pur')->create('po_receive_legacy_detail', function (Blueprint $table) {
            $table->id('id_receive_legacy_detail');
            $table->foreignId('receive_legacy_id')->constrained('po_receive_legacy', 'id_receive_legacy')->cascadeOnDelete();
            $table->unsignedBigInteger('material_id')->nullable();
            $table->string('legacy_item_code', 100)->nullable();
            $table->decimal('qty_receive', 20, 4)->nullable();
            $table->string('uom_receive', 10)->nullable();
            $table->decimal('unit_price', 18, 2)->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->timestamps();
            $table->index('material_id');
        });

        // 3. Purchase Payment -- tm2old.pur_ppym(_detail)
        Schema::connection('tm2pur')->create('po_payment_legacy', function (Blueprint $table) {
            $table->id('id_payment_legacy');
            $table->string('legacy_ppym_no', 30)->unique();
            $table->date('ppym_date')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->decimal('grand_total', 18, 2)->nullable();
            $table->string('legacy_status', 20)->nullable();
            $table->string('doc_status_normalized', 20)->nullable();
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->index('supplier_id');
        });
        Schema::connection('tm2pur')->create('po_payment_legacy_detail', function (Blueprint $table) {
            $table->id('id_payment_legacy_detail');
            $table->foreignId('payment_legacy_id')->constrained('po_payment_legacy', 'id_payment_legacy')->cascadeOnDelete();
            $table->string('legacy_pinv_no', 30)->nullable()->comment('Referensi Purchase Invoice -- pur_pinv belum dimigrasi, disimpan sbg teks');
            $table->decimal('paid_amount', 18, 2)->nullable();
            $table->timestamps();
        });

        // 4. Surat Jalan / Delivery Note -- tm2old.sdi_dnote(_detail)
        Schema::connection('tm2sdi')->create('co_delivery_legacy', function (Blueprint $table) {
            $table->id('id_delivery_legacy');
            $table->string('legacy_dnote_no', 30)->unique();
            $table->date('dnote_date')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('co_legacy_id')->nullable()->comment('-> co_order_legacy.id_co_legacy, NULL kalau co_no sumber tak ketemu');
            $table->string('legacy_co_no', 30)->nullable();
            $table->decimal('grand_total', 18, 2)->nullable();
            $table->string('legacy_status', 20)->nullable();
            $table->string('doc_status_normalized', 20)->nullable();
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->index('customer_id');
            $table->index('co_legacy_id');
        });
        Schema::connection('tm2sdi')->create('co_delivery_legacy_detail', function (Blueprint $table) {
            $table->id('id_delivery_legacy_detail');
            $table->foreignId('delivery_legacy_id')->constrained('co_delivery_legacy', 'id_delivery_legacy')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('legacy_item_code', 100)->nullable();
            $table->decimal('qty_ship', 14, 2)->nullable();
            $table->string('uom_code', 10)->nullable();
            $table->decimal('unit_price', 18, 2)->nullable();
            $table->decimal('qty_unitprice', 18, 2)->nullable();
            $table->timestamps();
            $table->index('product_id');
        });

        // 5. Customer Invoice -- tm2old.sdi_cinv(_detail). sdi_cinv_prebill/
        // print/print_detail/rcp/rcp_detail SENGAJA tidak diikutkan: rcp*
        // 0 baris, prebill/print adalah salinan turunan cetak (redundan thd
        // sdi_cinv, bukan sumber AR independen).
        Schema::connection('tm2sdi')->create('co_invoice_legacy', function (Blueprint $table) {
            $table->id('id_invoice_legacy');
            $table->string('legacy_cinv_no', 30)->unique();
            $table->date('cinv_date')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('co_legacy_id')->nullable()->comment('-> co_order_legacy.id_co_legacy, NULL kalau co_no sumber tak ketemu');
            $table->string('legacy_co_no', 30)->nullable();
            $table->decimal('subtotal', 18, 2)->nullable();
            $table->decimal('disc_amount', 18, 2)->nullable();
            $table->decimal('ppn_amount', 18, 2)->nullable();
            $table->decimal('grand_total', 18, 2)->nullable();
            $table->decimal('dp_amount', 18, 2)->nullable();
            $table->decimal('remain_of_bill', 18, 2)->nullable()->comment('Sisa tagihan blm dibayar per snapshot sumber -- basis AR');
            $table->string('legacy_status', 20)->nullable();
            $table->string('doc_status_normalized', 20)->nullable();
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->index('customer_id');
            $table->index('co_legacy_id');
        });
        Schema::connection('tm2sdi')->create('co_invoice_legacy_detail', function (Blueprint $table) {
            $table->id('id_invoice_legacy_detail');
            $table->foreignId('invoice_legacy_id')->constrained('co_invoice_legacy', 'id_invoice_legacy')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('legacy_item_code', 100)->nullable();
            $table->decimal('qty_inv', 14, 2)->nullable();
            $table->string('uom_code', 10)->nullable();
            $table->decimal('unit_price', 18, 2)->nullable();
            $table->decimal('qty_unitprice', 18, 2)->nullable();
            $table->timestamps();
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2pur')->dropIfExists('pr_requisition_legacy_detail');
        Schema::connection('tm2pur')->dropIfExists('pr_requisition_legacy');
        Schema::connection('tm2pur')->dropIfExists('po_receive_legacy_detail');
        Schema::connection('tm2pur')->dropIfExists('po_receive_legacy');
        Schema::connection('tm2pur')->dropIfExists('po_payment_legacy_detail');
        Schema::connection('tm2pur')->dropIfExists('po_payment_legacy');
        Schema::connection('tm2sdi')->dropIfExists('co_delivery_legacy_detail');
        Schema::connection('tm2sdi')->dropIfExists('co_delivery_legacy');
        Schema::connection('tm2sdi')->dropIfExists('co_invoice_legacy_detail');
        Schema::connection('tm2sdi')->dropIfExists('co_invoice_legacy');
    }
};
