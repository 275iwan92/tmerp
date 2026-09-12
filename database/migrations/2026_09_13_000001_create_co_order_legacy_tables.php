<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Arsip riwayat CO dari tm2old.sdi_co(_detail) -- BUKAN co_order/co_order_item.
// co_order desainnya terikat wajib quotation_id (1 CO = 1 Quotation), sementara
// 20.669 CO lama tidak pernah punya lineage Quotation/Inquiry sama sekali --
// memaksakan masuk ke co_order berarti mengubah constraint tabel yang sedang
// dipakai alur transaksi baru, demi data yang sifatnya cuma riwayat/laporan.
// Tabel arsip terpisah ini read-only bagi aplikasi (tidak ada CRUD/UI transaksi),
// tujuannya semata dashboard/laporan (mis. "total belanja Customer X").
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->create('co_order_legacy', function (Blueprint $table) {
            $table->id('id_co_legacy');
            $table->string('legacy_co_no', 30)->unique()->comment('Trace ke tm2old.sdi_co.co_no (trimmed) -- kunci idempoten');
            $table->date('co_date')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable()->comment('LOGICAL -> tm2mst.cust_customer.id_customer, lintas-DB');
            $table->string('customer_po_no', 100)->nullable();
            $table->decimal('subtotal', 18, 2)->nullable();
            $table->decimal('disc_amount', 18, 2)->nullable();
            $table->decimal('ppn_amount', 18, 2)->nullable();
            $table->decimal('grand_total', 18, 2)->nullable();
            $table->string('legacy_status', 20)->nullable()->comment('Nilai co_status APA ADANYA dari sumber, utk referensi');
            $table->string('doc_status_normalized', 20)->nullable()->comment('DRAFT|CONFIRMED|CANCELLED|UNKNOWN -- hasil pemetaan terbaik');
            $table->timestamps();
            $table->string('created_by', 100)->nullable();

            $table->index('customer_id');
            $table->index('co_date');
        });

        Schema::connection('tm2sdi')->create('co_order_legacy_detail', function (Blueprint $table) {
            $table->id('id_co_legacy_detail');
            $table->foreignId('co_legacy_id')->constrained('co_order_legacy', 'id_co_legacy')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable()->comment('LOGICAL -> tm2mst.prod_product.id_product, NULL kalau item_code lama tak ketemu crosswalk');
            $table->string('legacy_item_code', 100)->nullable()->comment('item_code APA ADANYA dari sumber, fallback kalau product_id null');
            $table->decimal('qty', 14, 2)->nullable();
            $table->string('uom_code', 10)->nullable();
            $table->decimal('unit_price', 18, 2)->nullable();
            $table->decimal('qty_unitprice', 18, 2)->nullable();
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->dropIfExists('co_order_legacy_detail');
        Schema::connection('tm2sdi')->dropIfExists('co_order_legacy');
    }
};
