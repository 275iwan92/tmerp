<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// mockup_master_customer.html (v2) — field baru dari tab Printing Specs,
// Sales & Finance (delta), dan Governance yang belum ada di skema manapun.
// "Sales Organization"/"Distribution Channel" mockup SENGAJA tidak dibawa —
// keputusan lama customer_spec.md §1.5 ("SAP-isme tak relevan
// percetakan") masih berlaku meski skema lengkap dokumen itu sendiri sudah
// disuperseded oleh cust_customer yang nyata berjalan sekarang.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->table('cust_customer', function (Blueprint $table) {
            // Overview — identitas tambahan
            $table->string('trade_name', 200)->nullable()->after('customer_name');
            $table->string('parent_group', 200)->nullable()->after('trade_name');
            $table->string('industry_category', 150)->nullable()->after('parent_group');

            // Printing Specs — mempengaruhi asumsi quotation, artwork proofing,
            // routing produksi, label pengiriman, penerimaan kualitas.
            $table->string('primary_product', 150)->nullable();
            $table->string('secondary_product', 150)->nullable();
            $table->string('default_substrate', 150)->nullable();
            $table->string('color_standard', 150)->nullable();
            $table->string('proofing_method', 150)->nullable();
            $table->string('artwork_approval', 150)->nullable();
            $table->string('retained_sample', 150)->nullable();
            $table->string('barcode_verification', 150)->nullable();
            $table->string('finishing_preference', 200)->nullable();
            $table->string('die_owner', 150)->nullable();
            $table->string('packing_instruction', 200)->nullable();
            $table->text('quality_notes')->nullable();

            // Sales & Finance — delta dari tab Accounting yang sudah ada
            // (payment_term/currency_code/credit_limit tetap dipakai apa adanya).
            $table->foreignId('sales_division_id')->nullable()->comment('SBU produksi utama pelanggan ini')
                ->constrained('org_division', 'division_id')->nullOnDelete();
            $table->string('price_list_code', 50)->nullable();
            $table->enum('tax_profile', ['PPN_11', 'EXEMPT'])->default('PPN_11');
            $table->boolean('credit_check_enabled')->default(true);
            $table->enum('credit_rating', ['A', 'B', 'C'])->default('B')->comment('A=Low Risk, B=Medium, C=High Risk');

            // Governance
            $table->boolean('tax_validated')->default(false)->comment('NPWP diverifikasi manual oleh Finance');
            $table->enum('kyc_status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('cust_customer', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_division_id');
            $table->dropColumn([
                'trade_name', 'parent_group', 'industry_category',
                'primary_product', 'secondary_product', 'default_substrate', 'color_standard',
                'proofing_method', 'artwork_approval', 'retained_sample', 'barcode_verification',
                'finishing_preference', 'die_owner', 'packing_instruction', 'quality_notes',
                'price_list_code', 'tax_profile', 'credit_check_enabled', 'credit_rating',
                'tax_validated', 'kyc_status',
            ]);
        });
    }
};
