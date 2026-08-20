<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Rekening bank per customer/supplier, dari tm2old.mst_vendor_bank (85
// baris). FK dalam-DB (sama tm2mst) ke cust_customer/sup_supplier -> fisik,
// cascade delete. legacy_vendor_bank_id trace ke sumber untuk idempotensi.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->create('cust_customer_bank', function (Blueprint $table) {
            $table->id('id_customer_bank');
            $table->foreignId('customer_id')->constrained('cust_customer', 'id_customer')->cascadeOnDelete();
            $table->string('bank_code', 20)->nullable();
            $table->string('bank_account_no', 50)->nullable();
            $table->string('bank_account_name', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_vendor_bank_id')->nullable()->unique()->comment('Trace ke tm2old.mst_vendor_bank.vendor_bank_id');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });

        Schema::connection('tm2mst')->create('sup_supplier_bank', function (Blueprint $table) {
            $table->id('id_supplier_bank');
            $table->foreignId('supplier_id')->constrained('sup_supplier', 'id_supplier')->cascadeOnDelete();
            $table->string('bank_code', 20)->nullable();
            $table->string('bank_account_no', 50)->nullable();
            $table->string('bank_account_name', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_vendor_bank_id')->nullable()->unique()->comment('Trace ke tm2old.mst_vendor_bank.vendor_bank_id');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('sup_supplier_bank');
        Schema::connection('tm2mst')->dropIfExists('cust_customer_bank');
    }
};
