<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FK sekarang aman dipasang: dicek 2026-08-18, seluruh nilai
// cust_customer.category / sup_supplier.category yang sudah ada SUDAH cocok
// 1:1 dengan cust_category/sup_category (0 orphan) -- keduanya memang
// diseed dari sumber data yang sama. Sebelumnya sengaja tidak di-FK (lihat
// migration 2026_08_18_000006) karena waktu itu belum diverifikasi.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->table('cust_customer', function (Blueprint $table) {
            $table->foreign('category')->references('category_code')->on('cust_category')
                ->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::connection('tm2mst')->table('sup_supplier', function (Blueprint $table) {
            $table->foreign('category')->references('category_code')->on('sup_category')
                ->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('cust_customer', function (Blueprint $table) {
            $table->dropForeign(['category']);
        });
        Schema::connection('tm2mst')->table('sup_supplier', function (Blueprint $table) {
            $table->dropForeign(['category']);
        });
    }
};
