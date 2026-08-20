<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Redesain quo_quotation dari 1:1-per-inquiry jadi 1 quotation = N inquiry (per
// customer yg sama) + N baris jasa/tooling bebas -- sesuai
// docs/mockups/mockup_sales_quotation_entry.html. Belum ada data produksi
// (0 baris sejak dibuat kemarin), aman direstruktur total.
return new class extends Migration
{
    public function up(): void
    {
        // quo_quotation_id di iqp_inquiry mengasumsikan 1 inquiry = 1 quotation
        // (sudah tak berlaku -- 1 inquiry sekarang bisa muncul di quo_quotation_item
        // manapun). Traceability sekarang lewat quo_quotation_item.inquiry_id.
        Schema::connection('tm2sdi')->table('iqp_inquiry', function (Blueprint $table) {
            $table->dropForeign(['quotation_id']);
            $table->dropColumn('quotation_id');
        });

        Schema::connection('tm2sdi')->table('quo_quotation', function (Blueprint $table) {
            $table->dropForeign(['inquiry_id']);
            $table->dropColumn('inquiry_id');
            $table->unsignedBigInteger('customer_id')->after('quotation_no')->comment('FK logis -> tm2mst.cust_customer, lintas-DB');
            $table->string('payment_term', 50)->nullable()->after('valid_until');
            $table->string('tax_scheme', 10)->default('EXCL')->after('payment_term')->comment('EXCL|INCL PPN');
            $table->string('currency_code', 5)->default('IDR')->after('tax_scheme');
            $table->text('notes')->nullable()->after('currency_code');
            $table->string('doc_status', 20)->default('DRAFT')->after('notes')->comment('DRAFT|PUBLISHED');
            $table->index('customer_id', 'idx_quo_quotation_customer');
        });

        Schema::connection('tm2sdi')->create('quo_quotation_item', function (Blueprint $table) {
            $table->id('id_item');
            $table->unsignedBigInteger('quotation_id');
            $table->unsignedSmallInteger('line_seq')->default(1);
            $table->string('source_type', 20)->comment('INQUIRY_TIER|INQUIRY_TOOLING|FREEFORM');
            $table->unsignedBigInteger('inquiry_id')->nullable()->comment('FK -> iqp_inquiry, NULL utk FREEFORM');
            $table->unsignedBigInteger('tier_id')->nullable()->comment('FK logis -> iqp_tier (snapshot, tak dipaksa fisik), hanya INQUIRY_TIER');
            $table->string('description', 255);
            $table->text('spec_note')->nullable();
            $table->decimal('qty', 14, 2)->default(1);
            $table->string('qty_unit', 20)->default('Pcs');
            $table->decimal('unit_price', 18, 4)->default(0);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->foreign('quotation_id')->references('id_quotation')->on('quo_quotation')->cascadeOnDelete();
            $table->foreign('inquiry_id')->references('id_inquiry')->on('iqp_inquiry')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->dropIfExists('quo_quotation_item');

        Schema::connection('tm2sdi')->table('quo_quotation', function (Blueprint $table) {
            $table->dropIndex('idx_quo_quotation_customer');
            $table->dropColumn(['customer_id', 'payment_term', 'tax_scheme', 'currency_code', 'notes', 'doc_status']);
            $table->unsignedBigInteger('inquiry_id')->unique()->after('quotation_no');
            $table->foreign('inquiry_id')->references('id_inquiry')->on('iqp_inquiry')->cascadeOnDelete();
        });

        Schema::connection('tm2sdi')->table('iqp_inquiry', function (Blueprint $table) {
            $table->unsignedBigInteger('quotation_id')->nullable();
            $table->foreign('quotation_id')->references('id_quotation')->on('quo_quotation')->nullOnDelete();
        });
    }
};
