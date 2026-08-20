<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TIER_3A_HRM.sql §2.6/2.6b — prod_shift/prod_shift_detail (skema pra-HRM)
// digantikan emp_shift/emp_shift_detail: department_id FK ke org_department
// (bukan teks bebas), day_of_week nama hari Inggris (konsisten legacy
// m_shifts_details) bukan integer ISO. Master Shift (shift.blade.php)
// di-update menyusul.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->dropIfExists('prod_shift_detail');
        Schema::connection('tm2mst')->dropIfExists('prod_shift');

        Schema::connection('tm2mst')->create('emp_shift', function (Blueprint $table) {
            $table->id('shift_id');
            $table->foreignId('department_id')->constrained('org_department', 'department_id')->restrictOnDelete();
            $table->string('shift_name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });

        Schema::connection('tm2mst')->create('emp_shift_detail', function (Blueprint $table) {
            $table->id('shift_detail_id');
            $table->foreignId('shift_id')->constrained('emp_shift', 'shift_id')->restrictOnDelete();
            $table->enum('day_of_week', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);
            $table->time('start_time')->nullable();
            $table->time('break_out')->nullable();
            $table->time('break_in')->nullable();
            $table->time('end_time')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->unique(['shift_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('emp_shift_detail');
        Schema::connection('tm2mst')->dropIfExists('emp_shift');
    }
};
