<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cabang/lokasi & kontak tambahan per customer/supplier — sebelumnya cuma
// bisa 1 alamat & 1 PIC (menempel langsung di cust_customer/sup_supplier).
// Pola sama seperti cust_customer_bank/sup_supplier_bank (migration
// 2026_08_18_000007): tabel anak, FK dalam-DB fisik, cascade delete.
// Alamat/kontak di tabel induk (default_address, contact_name, dst) TETAP
// jadi "default/utama" — tabel ini isinya TAMBAHAN. is_default di sini
// murni penanda UI (misal ingin promosikan cabang lain jadi favorit tampil),
// exclusivity "cuma 1 default aktif" ditegakkan di Livewire, bukan DB.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->create('cust_customer_branch', function (Blueprint $table) {
            $table->id('id_customer_branch');
            $table->foreignId('customer_id')->constrained('cust_customer', 'id_customer')->cascadeOnDelete();
            $table->string('branch_name', 100)->nullable()->comment('mis. Gudang Surabaya, Kantor Cabang Sidoarjo');
            $table->text('address')->nullable();
            $table->unsignedBigInteger('area_id')->nullable();
            $table->enum('area_level', ['CITY', 'DISTRICT', 'VILLAGE'])->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('phone', 50)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });

        Schema::connection('tm2mst')->create('sup_supplier_branch', function (Blueprint $table) {
            $table->id('id_supplier_branch');
            $table->foreignId('supplier_id')->constrained('sup_supplier', 'id_supplier')->cascadeOnDelete();
            $table->string('branch_name', 100)->nullable();
            $table->text('address')->nullable();
            $table->unsignedBigInteger('area_id')->nullable();
            $table->enum('area_level', ['CITY', 'DISTRICT', 'VILLAGE'])->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('phone', 50)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });

        Schema::connection('tm2mst')->create('cust_customer_contact', function (Blueprint $table) {
            $table->id('id_customer_contact');
            $table->foreignId('customer_id')->constrained('cust_customer', 'id_customer')->cascadeOnDelete();
            $table->string('contact_name', 200);
            $table->string('position', 100)->nullable()->comment('Jabatan');
            $table->string('phone', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });

        Schema::connection('tm2mst')->create('sup_supplier_contact', function (Blueprint $table) {
            $table->id('id_supplier_contact');
            $table->foreignId('supplier_id')->constrained('sup_supplier', 'id_supplier')->cascadeOnDelete();
            $table->string('contact_name', 200);
            $table->string('position', 100)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('sup_supplier_contact');
        Schema::connection('tm2mst')->dropIfExists('cust_customer_contact');
        Schema::connection('tm2mst')->dropIfExists('sup_supplier_branch');
        Schema::connection('tm2mst')->dropIfExists('cust_customer_branch');
    }
};
