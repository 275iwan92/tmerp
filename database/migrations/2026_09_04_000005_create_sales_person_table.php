<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Master PIC Sales (permintaan user 2026-09-04: "nama sales diambil dari
// login, lalu ada icon utk ubah, diambil dari list nama sales -- ada yang
// employee internal, ada yang external"). User menyebut dulu ada tabel
// `s_co_salesname` di sistem lama -- SUDAH DICEK, tidak ada di dump tm2old
// migrasi kita maupun di manapun di repo ini, jadi skema di sini dirancang
// baru (bukan migrasi data lama). Prefix `sales_` BARU (belum ada di daftar
// prefixlist.md) -- domain "sales person" tidak cocok masuk emp_/org_/ref_
// yang sudah ada (emp_=employee internal only, tidak ada slot utk sales
// external freelance/agent).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->create('sales_person', function (Blueprint $table): void {
            $table->id('id_sales_person');
            $table->string('sales_name', 150);
            $table->enum('sales_type', ['INTERNAL', 'EXTERNAL'])->default('INTERNAL');
            $table->unsignedBigInteger('employee_id')->nullable()
                ->comment('FK logis -> emp_employee, opsional, hanya relevan utk INTERNAL');
            $table->string('phone', 50)->nullable();
            $table->boolean('activate')->default(true);
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('sales_person');
    }
};
