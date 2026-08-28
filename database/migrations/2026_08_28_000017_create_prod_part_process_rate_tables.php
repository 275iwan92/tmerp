<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// docs/production_scheduling_wo_generation_delta.md §4.2/4.3 -- target rate
// default per produk+part+proses+MESIN atau per produk+part+proses+PEGAWAI
// (2 tabel terpisah, keputusan user 2026-08-28). Dikunci ke product_id+
// part_code (bukan prod_part yang tak pernah diisi -- lihat delta doc §3).
// Semua FK di sini FISIK -- product_id/process_id/machine_id/employee_id
// semua sama-sama tm2mst (CLAUDE.md §2, emp_employee juga tm2mst bukan tm2hrm).
return new class extends Migration
{
    public function up(): void
    {
        $mst = Schema::connection('tm2mst');

        $mst->create('prod_part_process_machine_rate', function (Blueprint $table) {
            $table->id('rate_id');
            $table->foreignId('product_id')->constrained('prod_product', 'id_product');
            $table->string('part_code', 50);
            $table->foreignId('process_id')->constrained('prod_process', 'id_process');
            $table->foreignId('machine_id')->constrained('prod_machine', 'machine_id');
            $table->decimal('target_qty_per_hour', 18, 4);
            $table->boolean('activate')->default(true);
            $table->string('created_by', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('updated_by', 50)->nullable();
            $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
            $table->unique(['product_id', 'part_code', 'process_id', 'machine_id'], 'uk_prod_part_proc_machine_rate');
        });

        $mst->create('prod_part_process_employee_rate', function (Blueprint $table) {
            $table->id('rate_id');
            $table->foreignId('product_id')->constrained('prod_product', 'id_product');
            $table->string('part_code', 50);
            $table->foreignId('process_id')->constrained('prod_process', 'id_process');
            $table->foreignId('employee_id')->constrained('emp_employee', 'employee_id');
            $table->decimal('target_qty_per_hour', 18, 4);
            $table->boolean('activate')->default(true);
            $table->string('created_by', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('updated_by', 50)->nullable();
            $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
            $table->unique(['product_id', 'part_code', 'process_id', 'employee_id'], 'uk_prod_part_proc_employee_rate');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('prod_part_process_employee_rate');
        Schema::connection('tm2mst')->dropIfExists('prod_part_process_machine_rate');
    }
};
