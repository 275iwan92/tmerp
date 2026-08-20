<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TIER_3A_HRM.sql Bagian 2.7 (roster) + Bagian 3 (self-service/pengajuan).
// Dimigrasikan sekaligus dgn Master Employee (satu sumber DDL) meski layar
// Shift Roster v2 & Self-Service baru digarap fase berikutnya — menghindari
// migration susulan yang memecah satu domain jadi beberapa batch tanggung.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->create('emp_shift_roster_batch', function (Blueprint $table) {
            $table->id('batch_id');
            $table->foreignId('division_id')->constrained('org_division', 'division_id')->restrictOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->string('status', 10)->default('DRAFT')->comment('DRAFT|CONFIRMED');
            $table->string('confirmed_by', 100)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->index(['division_id', 'date_from', 'date_to']);
        });

        Schema::connection('tm2mst')->create('emp_shift_roster', function (Blueprint $table) {
            $table->id('roster_id');
            $table->foreignId('batch_id')->nullable()->constrained('emp_shift_roster_batch', 'batch_id')->nullOnDelete();
            $table->foreignId('employee_id')->constrained('emp_employee', 'employee_id')->restrictOnDelete();
            $table->foreignId('division_id')->constrained('org_division', 'division_id')->restrictOnDelete();
            $table->foreignId('machine_id')->nullable()->constrained('prod_machine', 'machine_id')->nullOnDelete();
            $table->foreignId('shift_id')->constrained('emp_shift', 'shift_id')->restrictOnDelete();
            $table->date('roster_date');
            $table->string('assigned_by_role', 10)->default('HR')->comment('PPIC|HR');
            $table->boolean('is_overtime')->default(false);
            $table->string('overtime_type', 15)->nullable()->comment('REGULER|HARI_MINGGU|HARI_BESAR');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->unique(['employee_id', 'roster_date', 'shift_id', 'machine_id'], 'uq_roster_exact');
            $table->index(['division_id', 'roster_date']);
            $table->index(['machine_id', 'shift_id', 'roster_date']);
        });

        Schema::connection('tm2mst')->create('emp_correction_request', function (Blueprint $table) {
            $table->id('request_id');
            $table->foreignId('employee_id')->constrained('emp_employee', 'employee_id')->cascadeOnDelete();
            $table->string('field_name', 100);
            $table->string('old_value', 255)->nullable();
            $table->string('new_value', 255);
            $table->string('reason', 255)->nullable();
            $table->string('attachment_path', 255)->nullable();
            $table->boolean('requires_hr_approval')->default(true);
            $table->string('status', 10)->default('PENDING')->comment('PENDING|APPROVED|REJECTED');
            $table->timestamp('requested_at')->useCurrent();
            $table->string('reviewed_by', 100)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 255)->nullable();
            $table->index('status');
        });

        Schema::connection('tm2mst')->create('emp_leave_balance', function (Blueprint $table) {
            $table->id('balance_id');
            $table->foreignId('employee_id')->constrained('emp_employee', 'employee_id')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('entitlement_days')->default(12);
            $table->unsignedTinyInteger('used_days')->default(0);
            $table->unsignedTinyInteger('carried_over_days')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['employee_id', 'year']);
        });

        Schema::connection('tm2mst')->create('emp_leave_request', function (Blueprint $table) {
            $table->id('request_id');
            $table->foreignId('employee_id')->constrained('emp_employee', 'employee_id')->cascadeOnDelete();
            $table->string('leave_type', 15)->comment('CUTI|SAKIT|IZIN|CUTI_KHUSUS');
            $table->string('khusus_event_type', 30)->nullable();
            $table->string('izin_category', 25)->nullable();
            $table->string('izin_relasi_pemohon', 30)->nullable();
            $table->string('izin_instansi_asal', 40)->nullable();
            $table->date('date_from');
            $table->date('date_to');
            $table->unsignedTinyInteger('total_days');
            $table->string('reason', 255)->nullable();
            $table->string('attachment_surat_cuti', 255)->nullable();
            $table->string('attachment_surat_dokter', 255)->nullable();
            $table->string('attachment_kwitansi_resep', 255)->nullable();
            $table->string('attachment_jkn_screenshot', 255)->nullable();
            $table->string('attachment_path', 255)->nullable();
            $table->time('sakit_lapor_jam')->nullable();
            $table->boolean('converted_to_izin')->default(false);
            $table->boolean('is_paid');
            $table->boolean('deducts_leave_balance');
            $table->string('status', 10)->default('PENDING')->comment('PENDING|APPROVED|REJECTED');
            $table->timestamp('requested_at')->useCurrent();
            $table->string('reviewed_by', 100)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 255)->nullable();
            $table->index('status');
            $table->index('leave_type');
        });

        Schema::connection('tm2mst')->create('emp_shift_swap_request', function (Blueprint $table) {
            $table->id('request_id');
            $table->foreignId('requester_employee_id')->constrained('emp_employee', 'employee_id')->restrictOnDelete();
            $table->foreignId('requester_roster_id')->constrained('emp_shift_roster', 'roster_id')->restrictOnDelete();
            $table->foreignId('target_employee_id')->constrained('emp_employee', 'employee_id')->restrictOnDelete();
            $table->foreignId('target_roster_id')->constrained('emp_shift_roster', 'roster_id')->restrictOnDelete();
            $table->string('reason', 255)->nullable();
            $table->boolean('submitted_on_time')->default(true);
            $table->string('partner_status', 10)->default('PENDING')->comment('PENDING|APPROVED|REJECTED');
            $table->string('ppic_status', 10)->default('PENDING')->comment('PENDING|APPROVED|REJECTED');
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('partner_reviewed_at')->nullable();
            $table->string('ppic_reviewed_by', 100)->nullable();
            $table->timestamp('ppic_reviewed_at')->nullable();
        });

        Schema::connection('tm2mst')->create('emp_out_permit_request', function (Blueprint $table) {
            $table->id('request_id');
            $table->foreignId('employee_id')->constrained('emp_employee', 'employee_id')->cascadeOnDelete();
            $table->date('permit_date');
            $table->time('planned_out_time');
            $table->time('planned_return_time')->nullable();
            $table->string('reason', 255);
            $table->timestamp('fingerprint_out_at')->nullable();
            $table->timestamp('fingerprint_in_at')->nullable();
            $table->string('atasan_approved_by', 100)->nullable();
            $table->timestamp('atasan_approved_at')->nullable();
            $table->string('hrd_approved_by', 100)->nullable();
            $table->timestamp('hrd_approved_at')->nullable();
            $table->string('status', 10)->default('PENDING')->comment('PENDING|APPROVED|REJECTED');
            $table->timestamp('requested_at')->useCurrent();
            $table->index('permit_date');
        });

        Schema::connection('tm2mst')->create('emp_family_request', function (Blueprint $table) {
            $table->id('request_id');
            $table->foreignId('employee_id')->constrained('emp_employee', 'employee_id')->cascadeOnDelete();
            $table->string('request_type', 12)->comment('LAHIR|MENINGGAL|PASANGAN');
            $table->foreignId('family_id')->nullable()->constrained('emp_family', 'family_id')->nullOnDelete();
            $table->string('relation', 15)->nullable();
            $table->string('full_name', 150)->nullable();
            $table->date('birth_date')->nullable();
            $table->date('deceased_date')->nullable();
            $table->string('attachment_path', 255)->nullable();
            $table->string('status', 10)->default('PENDING')->comment('PENDING|APPROVED|REJECTED');
            $table->timestamp('requested_at')->useCurrent();
            $table->string('reviewed_by', 100)->nullable();
            $table->timestamp('reviewed_at')->nullable();
        });

        Schema::connection('tm2mst')->create('emp_badge_photo_request', function (Blueprint $table) {
            $table->id('request_id');
            $table->foreignId('employee_id')->constrained('emp_employee', 'employee_id')->cascadeOnDelete();
            $table->string('photo_path', 255);
            $table->unsignedInteger('width_px')->nullable();
            $table->unsignedInteger('height_px')->nullable();
            $table->string('status', 10)->default('PENDING')->comment('PENDING|APPROVED|REJECTED');
            $table->timestamp('requested_at')->useCurrent();
            $table->string('reviewed_by', 100)->nullable();
            $table->timestamp('reviewed_at')->nullable();
        });

        Schema::connection('tm2mst')->create('emp_resign_request', function (Blueprint $table) {
            $table->id('request_id');
            $table->foreignId('employee_id')->constrained('emp_employee', 'employee_id')->cascadeOnDelete();
            $table->date('effective_date');
            $table->string('reason', 255)->nullable();
            $table->string('status', 12)->default('PENDING')->comment('PENDING|PROCESSED');
            $table->timestamp('requested_at')->useCurrent();
            $table->string('processed_by', 100)->nullable();
            $table->timestamp('processed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('emp_resign_request');
        Schema::connection('tm2mst')->dropIfExists('emp_badge_photo_request');
        Schema::connection('tm2mst')->dropIfExists('emp_family_request');
        Schema::connection('tm2mst')->dropIfExists('emp_out_permit_request');
        Schema::connection('tm2mst')->dropIfExists('emp_shift_swap_request');
        Schema::connection('tm2mst')->dropIfExists('emp_leave_request');
        Schema::connection('tm2mst')->dropIfExists('emp_leave_balance');
        Schema::connection('tm2mst')->dropIfExists('emp_correction_request');
        Schema::connection('tm2mst')->dropIfExists('emp_shift_roster');
        Schema::connection('tm2mst')->dropIfExists('emp_shift_roster_batch');
    }
};
