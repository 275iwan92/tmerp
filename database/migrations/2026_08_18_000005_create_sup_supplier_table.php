<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Master supplier BELUM PERNAH ADA di tm2mst (TIER_3A_PREFIX_LIST.md belum
// alokasikan prefix sup_). Bentuk tabel ini mirror cust_customer (kolom
// alamat/kontak/pajak yang sama) + field khusus supplier (is_subcon,
// category dari legacy supplier_category). Sumber isi: engine migrasi
// tm2old.mst_vendor (as_supplier=1) — lihat App\Services\Migration\VendorMigrationService.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->create('sup_supplier', function (Blueprint $table) {
            $table->id('id_supplier');
            $table->string('supplier_code', 50)->nullable();
            $table->string('supplier_name', 200)->nullable();
            $table->string('nickname', 10)->nullable();
            $table->string('alias_code', 50)->nullable();
            $table->string('category', 50)->nullable()->comment('Kategori jenis supplier (ATK/BAHAN BAKU/JASA/dst) — lihat sup_category');
            $table->boolean('is_subcon')->default(false);
            $table->string('contact_name', 200)->nullable();
            $table->text('default_address')->nullable();
            $table->unsignedBigInteger('default_area_id')->nullable()->comment('FK logis -> geo_city/geo_district/geo_village, level di default_area_level');
            $table->enum('default_area_level', ['CITY', 'DISTRICT', 'VILLAGE'])->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('phone_2', 50)->nullable();
            $table->string('fax', 20)->nullable();
            $table->string('website', 150)->nullable();
            $table->string('payment_term', 50)->nullable()->comment('FK -> fin_payment_term (menyusul), teks bebas untuk sekarang');
            $table->string('tax_code', 25)->nullable();
            $table->decimal('tax_purchase_value', 5, 2)->unsigned()->nullable()->default(0);
            $table->decimal('tax_service_value', 5, 2)->unsigned()->nullable()->default(0);
            $table->boolean('is_blacklisted')->default(false);
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('legacy_vendor_code', 20)->nullable()->unique()->comment('Trace ke tm2old.mst_vendor.vendor_code — kunci idempotent engine migrasi.');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->unique('supplier_code');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('sup_supplier');
    }
};
