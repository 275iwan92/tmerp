<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// docs/attendancepayroll_tier3.md §3 -- att_* awalnya didesain utk tm2hrm,
// dipindah ke tm2mst (keputusan user 2026-09-05) supaya nempel langsung ke
// emp_employee/emp_shift/emp_shift_roster yang sudah live di sana (tm2hrm
// fisik ada tapi 0 tabel dipakai, lihat db_hrm.md). emp_overtime_request
// ditambahkan (belum ada di live schema) -- sumber kebenaran durasi lembur
// per Tier 2 §4.4 (bukan re-derive dari selisih jam finger).
return new class extends Migration
{
    public function up(): void
    {
        $mst = Schema::connection('tm2mst');

        $mst->create('att_finger_import', function (Blueprint $table) {
            $table->id('id_import');
            $table->date('import_date');
            $table->string('file_name', 255);
            $table->string('imported_by', 100);
            $table->unsignedInteger('row_count')->default(0);
            $table->string('status', 20)->default('PENDING')->comment('PENDING|PROCESSED|FAILED');
            $table->text('error_note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
        });

        $mst->create('att_finger_raw', function (Blueprint $table) {
            $table->id('id_finger_raw');
            $table->foreignId('id_import')->constrained('att_finger_import', 'id_import')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('emp_employee', 'employee_id')->cascadeOnDelete();
            $table->dateTime('scan_datetime');
            $table->string('scan_type', 10)->default('UNKNOWN')->comment('IN|OUT|UNKNOWN');
            $table->string('raw_device_code', 50)->nullable();
            $table->index(['employee_id', 'scan_datetime']);
        });

        $mst->create('emp_overtime_request', function (Blueprint $table) {
            $table->id('id_overtime');
            $table->foreignId('employee_id')->constrained('emp_employee', 'employee_id')->cascadeOnDelete();
            $table->date('overtime_date');
            $table->string('ot_type', 10)->comment('AWAL|AKHIR|KEDUANYA');
            $table->unsignedInteger('duration_minutes')->comment('Total durasi lembur hari itu, sumber kebenaran (Tier 2 §4.4)');
            $table->string('status', 20)->default('DRAFT')->comment('DRAFT|SUBMITTED|APPROVED|REJECTED');
            $table->string('approved_by', 100)->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->unique(['employee_id', 'overtime_date']);
        });

        $mst->create('att_daily_status', function (Blueprint $table) {
            $table->id('id_daily');
            $table->foreignId('employee_id')->constrained('emp_employee', 'employee_id')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('status_code', 10)->comment('H,T-5,T+5,T+15,PA,PAT-5,PAT+5,KJK,I,IS,C,CL,S,A,F,FF');
            $table->dateTime('finger_in')->nullable();
            $table->dateTime('finger_out')->nullable();
            $table->time('shift_start')->nullable()->comment('Snapshot dari emp_shift_detail pada tanggal tsb, untuk audit histori');
            $table->time('shift_end')->nullable();
            $table->unsignedInteger('minutes_late')->default(0);
            $table->unsignedInteger('minutes_early_leave')->default(0);
            $table->unsignedInteger('minutes_out_during_shift')->default(0)->comment('Akumulasi KJK, Tier 2 §3');
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->boolean('is_uang_roti')->default(false)->comment('Lembur>2jam & role eligible, Tier 2 §4.4');
            $table->unsignedInteger('late_violation_seq_in_period')->nullable()->comment('Urutan pelanggaran T+5 dlm periode berjalan, cek >3x (Tier 2 §4.2)');
            $table->string('source', 20)->default('AUTO')->comment('AUTO|MANUAL_OVERRIDE');
            $table->string('override_by', 100)->nullable();
            $table->string('override_reason', 255)->nullable();
            $table->timestamp('calculated_at')->useCurrent();
            $table->unique(['employee_id', 'attendance_date']);
            $table->index('status_code');
        });
    }

    public function down(): void
    {
        $mst = Schema::connection('tm2mst');
        $mst->dropIfExists('att_daily_status');
        $mst->dropIfExists('emp_overtime_request');
        $mst->dropIfExists('att_finger_raw');
        $mst->dropIfExists('att_finger_import');
    }
};
