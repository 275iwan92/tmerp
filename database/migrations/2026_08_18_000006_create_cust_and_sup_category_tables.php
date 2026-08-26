<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lookup deskripsi kategori (nempel ke domain induknya, bukan ref_ generik —
// prefixlist.md: "lookup ber-domain nempel ke induk"). Diisi dari
// tm2old.mst_vendor_customer_category / mst_vendor_supplier_category oleh
// VendorMigrationService. cust_customer.category / sup_supplier.category
// SENGAJA teks bebas (bukan FK) — data legacy-nya sendiri tidak konsisten,
// FK ketat akan memblokir import baris yang kategorinya typo/kosong.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->create('cust_category', function (Blueprint $table) {
            $table->string('category_code', 30)->primary();
            $table->string('description', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });

        Schema::connection('tm2mst')->create('sup_category', function (Blueprint $table) {
            $table->string('category_code', 50)->primary();
            $table->string('description', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('sup_category');
        Schema::connection('tm2mst')->dropIfExists('cust_category');
    }
};
