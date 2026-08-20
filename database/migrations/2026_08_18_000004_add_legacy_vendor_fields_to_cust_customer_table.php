<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Engine migrasi tm2old.mst_vendor -> cust_customer/sup_supplier. Field yang
// belum punya rumah di cust_customer dipromosikan jadi kolom asli (bukan
// JSON arsip) atas keputusan eksplisit Jo — semua nullable/non-breaking,
// tidak mengubah kolom existing. legacy_vendor_code dipisah dari
// customer_code (yang boleh diedit user) supaya re-run engine tetap
// idempotent walau customer_code diganti nanti.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->table('cust_customer', function (Blueprint $table) {
            $table->string('legacy_vendor_code', 20)->nullable()->unique()
                ->comment('Trace ke tm2old.mst_vendor.vendor_code — kunci idempotent engine migrasi, TIDAK dipakai user.');
            $table->string('nickname', 10)->nullable();
            $table->string('alias_code', 50)->nullable();
            $table->string('category', 30)->nullable()->comment('Kategori tracking pembayaran (LANCAR/KURANG LANCAR/dst) — lihat cust_category');
            $table->boolean('is_blacklisted')->default(false);
            $table->string('phone_2', 20)->nullable();
            $table->string('fax', 20)->nullable();
            $table->decimal('tax_purchase_value', 5, 2)->unsigned()->nullable()->default(0)->comment('Tarif pajak pembelian (%)');
            $table->decimal('tax_service_value', 5, 2)->unsigned()->nullable()->default(0)->comment('Tarif pajak jasa (%)');
            $table->text('note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('cust_customer', function (Blueprint $table) {
            $table->dropColumn([
                'legacy_vendor_code', 'nickname', 'alias_code', 'category', 'is_blacklisted',
                'phone_2', 'fax', 'tax_purchase_value', 'tax_service_value', 'note',
            ]);
        });
    }
};
