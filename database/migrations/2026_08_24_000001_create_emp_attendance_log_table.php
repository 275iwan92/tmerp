<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TIER_3A_HRM.sql Bagian 3B -- Time Attendance, dicabut dari status ditunda
// 2026-08-24 (lihat HRM_STATE.yaml -> time_attendance). 1 baris/employee/
// tanggal, hasil import Excel mesin fingerprint (parsing file dikerjakan
// terpisah). Jadwal & status (Telat/Alpha/dst) dihitung app-layer, tidak
// disimpan -- lihat TIER_2_HRM.md Part D.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->create('emp_attendance_log', function (Blueprint $table) {
            $table->id('attendance_id');
            $table->foreignId('employee_id')->constrained('emp_employee', 'employee_id')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->dateTime('clock_in_at')->nullable()->comment('Scan masuk aktual dari mesin fingerprint');
            $table->dateTime('clock_out_at')->nullable()->comment('Scan keluar aktual dari mesin fingerprint');
            $table->string('source', 15)->default('EXCEL_IMPORT')->comment('EXCEL_IMPORT|MANUAL');
            $table->string('import_batch_ref', 100)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('created_by', 100)->nullable();
            $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
            $table->string('updated_by', 100)->nullable();
            $table->unique(['employee_id', 'attendance_date'], 'uq_attendance_employee_date');
            $table->index('attendance_date', 'idx_attendance_date');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('emp_attendance_log');
    }
};
