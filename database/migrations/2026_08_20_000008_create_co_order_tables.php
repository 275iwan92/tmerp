<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// co_order — dicadangkan di banyak tempat (fin_invoice.co_id, fin_voucher_line.co_id,
// iqp_inquiry.co_id) tapi belum pernah didesain (Tier 3A catatan "FK PENDING" sejak awal,
// tidak ada satupun TIER doc). Dibangun dari nol berdasarkan docs/mockups/mockup_co_entry.html
// (persetujuan user 2026-08-20: 1 CO = 1 Quotation, Quotation = sumber item snapshot).
// discount_doc_pct/discount_qty_pct kolom-kembar header meniru pola iqp_inquiry
// (CLAUDE.md §4: "dokumen kolom-kembar header<->co_order, NULL default").
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->create('co_order', function (Blueprint $table) {
            $table->id('id_order');
            $table->string('co_no', 20)->unique();
            $table->date('co_date');
            $table->unsignedBigInteger('quotation_id')->unique()->comment('1 CO = 1 Quotation (keputusan user 2026-08-20)');
            $table->string('customer_po_no', 100)->nullable();
            $table->unsignedInteger('ship_area_id')->nullable()->comment('FK logis -> tm2mst geo_village/district/city, lintas-DB');
            $table->string('ship_area_level', 20)->nullable()->comment('VILLAGE|DISTRICT|CITY');
            $table->string('ship_address_label', 255)->nullable();
            $table->text('ship_address_detail')->nullable()->comment('Jalan/no/RT-RW manual, terpisah dari wilayah terstruktur');
            $table->string('ship_postal_code', 20)->nullable();
            $table->decimal('discount_doc_pct', 5, 2)->nullable()->comment('Snapshot dari iqp_inquiry.discount_doc_value saat CO dibuat, locked');
            $table->decimal('discount_qty_pct', 5, 2)->nullable()->comment('Editable di level CO, default dari iqp_inquiry.discount_qty_value');
            $table->string('doc_status', 20)->default('DRAFT')->comment('DRAFT|CONFIRMED');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->foreign('quotation_id')->references('id_quotation')->on('quo_quotation')->restrictOnDelete();
        });

        Schema::connection('tm2sdi')->create('co_order_item', function (Blueprint $table) {
            $table->id('id_item');
            $table->unsignedBigInteger('order_id');
            $table->unsignedSmallInteger('line_seq')->default(1);
            $table->unsignedBigInteger('quotation_item_id')->nullable()->comment('FK logis -> quo_quotation_item, NULL kalau ditambah manual di CO');
            $table->string('line_kind', 10)->comment('PRODUCT|SERVICE');
            $table->unsignedBigInteger('inquiry_id')->nullable()->comment('FK -> iqp_inquiry, traceability, NULL utk baris manual/service');
            $table->string('description', 255);
            $table->text('spec_note')->nullable();
            $table->decimal('qty', 14, 2)->default(1);
            $table->string('qty_unit', 20)->default('Pcs');
            $table->decimal('unit_price', 18, 4)->default(0);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->foreign('order_id')->references('id_order')->on('co_order')->cascadeOnDelete();
            $table->foreign('inquiry_id')->references('id_inquiry')->on('iqp_inquiry')->nullOnDelete();
        });

        Schema::connection('tm2sdi')->create('co_order_delivery', function (Blueprint $table) {
            $table->id('id_delivery');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('item_id')->comment('FK -> co_order_item, harus PRODUCT');
            $table->string('shipment_type', 10)->default('FULL')->comment('FULL|PARTIAL');
            $table->date('ship_date')->nullable();
            $table->decimal('ship_qty', 14, 2)->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->foreign('order_id')->references('id_order')->on('co_order')->cascadeOnDelete();
            $table->foreign('item_id')->references('id_item')->on('co_order_item')->cascadeOnDelete();
        });

        Schema::connection('tm2sdi')->create('co_order_external_cost', function (Blueprint $table) {
            $table->id('id_cost');
            $table->unsignedBigInteger('order_id');
            $table->string('category', 50)->comment('Trucking|Expedition|Lainnya');
            $table->string('description', 255)->nullable();
            $table->decimal('nominal', 18, 2)->default(0);
            $table->string('tax_treatment', 20)->default('TAXABLE')->comment('TAXABLE|REIMBURSEMENT');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->foreign('order_id')->references('id_order')->on('co_order')->cascadeOnDelete();
        });

        // iqp_inquiry.co_id sudah dicadangkan sejak DDL awal ("FK PENDING") -- pasang
        // sekarang. Ini logical-many (1 CO bisa punya banyak baris dari inquiry beda-beda
        // via co_order_item), jadi co_id di sini artinya "CO TERAKHIR yang menyerap inquiry
        // ini" -- convenience lookup, bukan sumber kebenaran (itu di co_order_item.inquiry_id).
        Schema::connection('tm2sdi')->table('iqp_inquiry', function (Blueprint $table) {
            $table->foreign('co_id')->references('id_order')->on('co_order')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('iqp_inquiry', function (Blueprint $table) {
            $table->dropForeign(['co_id']);
        });

        Schema::connection('tm2sdi')->dropIfExists('co_order_external_cost');
        Schema::connection('tm2sdi')->dropIfExists('co_order_delivery');
        Schema::connection('tm2sdi')->dropIfExists('co_order_item');
        Schema::connection('tm2sdi')->dropIfExists('co_order');
    }
};
