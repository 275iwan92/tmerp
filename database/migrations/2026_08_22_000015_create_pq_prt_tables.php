<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PQ (Purchase Quotation) & PRT (Purchase Return) -- DDL dasar dari
// docs/procurement_tier3.md §3 & §6, disesuaikan setelah user memberi mockup HTML
// (mockup_purchase_quotation.html / mockup_purchase_return.html, 2026-08-22)
// yang menunjukkan beberapa field belum ada di draf DDL asli:
//   PQ: i_lead_time_day di HEADER (draf DDL cuma level baris).
//   PRT: field QC inspector, komunikasi/logistik retur, referensi Credit Note
//        supplier, batas waktu pengganti -- semua tak ada di draf DDL; status
//        di draf cuma DRAFT|CONFIRMED|COMPLETED|CANCELLED padahal mockup minta
//        alur persetujuan 2-pihak (perlu SUBMITTED) -- aman krn kolom `status`
//        VARCHAR(20) polos, bukan ENUM MySQL sungguhan, jadi nilai baru tidak
//        butuh ALTER. replacement_pv_id juga dipindah dari header ke BARIS
//        (mockup: tracking pengganti per baris, bukan 1 PV pengganti utk
//        seluruh dokumen) -- kolom header lama TETAP ada utk kasus sederhana.
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2pur');

        if (! $db->hasTable('pq_quotation')) {
            $db->create('pq_quotation', function (Blueprint $table) {
                $table->id('pq_id');
                $table->string('doc_no', 30)->nullable()->unique();
                $table->date('i_doc_date');
                $table->unsignedBigInteger('i_supplier_id')->comment('LOGICAL -> tm2mst.sup_supplier (1 PQ = 1 supplier)');
                $table->unsignedBigInteger('i_pr_id')->nullable()->comment('PR acuan, opsional -- PQ boleh berdiri sendiri');
                $table->date('i_quotation_valid_until');
                $table->unsignedBigInteger('i_currency_id')->nullable()->comment('LOGICAL -> tm2mst.fin_currency');
                $table->decimal('i_fx_rate', 18, 6)->default(1);
                $table->string('i_payment_term', 50)->nullable();
                $table->unsignedSmallInteger('i_lead_time_day')->nullable()->comment('[DELTA mockup] estimasi lead time umum supplier utk PQ ini -- per-baris tetap ada di pq_line utk item spesial');
                $table->string('i_notes', 255)->nullable();
                $table->decimal('c_total_idr', 18, 4)->nullable();
                $table->string('status', 20)->default('DRAFT')->comment('DRAFT|CONFIRMED|EXPIRED|CANCELLED');
                $table->string('cancel_reason', 255)->nullable();
                $table->string('created_by', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->string('updated_by', 100)->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->index('i_supplier_id');
                $table->index('i_pr_id');
                $table->index('i_quotation_valid_until');
                $table->index('status');
            });
        }

        if (! $db->hasTable('pq_line')) {
            $db->create('pq_line', function (Blueprint $table) {
                $table->id('pq_line_id');
                $table->unsignedBigInteger('pq_id');
                $table->unsignedSmallInteger('line_no');
                $table->unsignedBigInteger('pr_line_id')->nullable()->comment('baris PR yang ditawar, opsional');
                $table->unsignedBigInteger('item_id')->nullable()->comment('LOGICAL -> m_raw_material');
                $table->string('i_description', 255);
                $table->decimal('i_qty', 20, 4);
                $table->string('i_uom', 50);
                $table->decimal('i_price_fx', 18, 4)->comment('harga dalam mata uang penawaran');
                $table->decimal('c_price_idr', 18, 4)->nullable();
                $table->decimal('c_subtotal_idr', 18, 4)->nullable();
                $table->unsignedSmallInteger('i_lead_time_day')->nullable();
                $table->string('i_notes', 255)->nullable();
                $table->string('created_by', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->string('updated_by', 100)->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->unique(['pq_id', 'line_no']);
                $table->index('pr_line_id');
                $table->foreign('pq_id')->references('pq_id')->on('pq_quotation')->cascadeOnDelete();
            });
        }

        if (! $db->hasTable('prt_return')) {
            $db->create('prt_return', function (Blueprint $table) {
                $table->id('prt_id');
                $table->string('doc_no', 30)->nullable()->unique()->comment('NRB/YY/#####');
                $table->date('i_doc_date');
                $table->unsignedBigInteger('i_supplier_id');
                $table->unsignedBigInteger('pv_id')->nullable()->comment('PV asal -- NULL bila retur dari stok (i_source=FROM_STOCK)');
                $table->unsignedBigInteger('po_id')->nullable();
                $table->string('i_source', 20)->default('QC_REJECT')->comment('QC_REJECT|FROM_STOCK');
                $table->string('i_disposition', 20)->comment('REPLACEMENT|CREDIT_NOTE');
                $table->string('i_reason', 255);
                $table->decimal('c_credit_value', 18, 4)->nullable()->comment('nilai pengurang tagihan bila CREDIT_NOTE');
                $table->unsignedBigInteger('replacement_pv_id')->nullable()->comment('PV barang pengganti -- kasus sederhana 1 dok pengganti; kasus per-baris lihat prt_line.replacement_pv_id');
                $table->unsignedBigInteger('rm_stock_doc_id')->nullable()->comment('LOGICAL -> tm2inv rm_stock OUT doc_cat=RETURN, NULL sampai stok diposting manual (v1 belum otomatis)');
                // [DELTA mockup_purchase_return.html] -- tak ada di draf DDL awal.
                $table->string('qc_inspector', 100)->nullable();
                $table->string('comm_method', 50)->nullable()->comment('metode komunikasi ke supplier (Email/Telp/WA/dst)');
                $table->string('supplier_pic', 100)->nullable();
                $table->date('physical_return_date')->nullable()->comment('tanggal barang fisik dikirim balik ke supplier');
                $table->string('expedition', 100)->nullable();
                $table->string('cn_supplier_no', 50)->nullable()->comment('No. Credit Note dari supplier');
                $table->date('cn_received_date')->nullable();
                $table->date('replacement_due_date')->nullable();
                $table->string('status', 20)->default('DRAFT')->comment('DRAFT|SUBMITTED|CONFIRMED|COMPLETED|CANCELLED -- VARCHAR polos, SUBMITTED ditambah utk alur approval mockup (bukan di draf DDL awal)');
                $table->string('cancel_reason', 255)->nullable();
                $table->string('created_by', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->string('updated_by', 100)->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->index('pv_id');
                $table->index('status');
                $table->index('i_supplier_id');
            });
        }

        if (! $db->hasTable('prt_line')) {
            $db->create('prt_line', function (Blueprint $table) {
                $table->id('prt_line_id');
                $table->unsignedBigInteger('prt_id');
                $table->unsignedBigInteger('pv_line_id')->nullable();
                $table->unsignedSmallInteger('line_no');
                $table->unsignedBigInteger('item_id')->nullable();
                $table->string('c_item_code', 60)->nullable();
                $table->string('i_description', 255);
                $table->decimal('i_qty', 20, 4)->comment('satuan stok');
                $table->string('i_uom', 50);
                $table->decimal('c_unit_price', 18, 4)->nullable();
                $table->decimal('c_line_value', 18, 4)->nullable();
                $table->boolean('is_stock_moved')->default(false)->comment('1 bila qty ini pernah masuk stok -> perlu Stock Out RETURN');
                $table->string('i_lot_no', 100)->nullable();
                $table->string('i_notes', 255)->nullable();
                // [DELTA mockup] tracking pengganti PER BARIS (mockup punya tabel
                // pengganti per baris, bukan cuma 1 replacement_pv_id di header).
                $table->unsignedBigInteger('replacement_pv_id')->nullable();
                $table->decimal('replacement_qty', 20, 4)->nullable();
                $table->string('replacement_status', 20)->nullable()->comment('PENDING|RECEIVED');
                $table->string('created_by', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->string('updated_by', 100)->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->unique(['prt_id', 'line_no']);
                $table->index('pv_line_id');
                $table->foreign('prt_id')->references('prt_id')->on('prt_return')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        $db = Schema::connection('tm2pur');
        $db->dropIfExists('prt_line');
        $db->dropIfExists('prt_return');
        $db->dropIfExists('pq_line');
        $db->dropIfExists('pq_quotation');
    }
};
