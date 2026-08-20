<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TIER_3A_HRM.sql Bagian 2 — emp_employee (core, sumber kebenaran identitas
// orang lintas modul) + tabel pendukung Master Employee (kontrak, keluarga,
// dokumen, rekening, pendidikan, BPJS, kedisiplinan). FK ke geo_village
// (alamat KTP/domisili) & tm2sys.users sengaja LOGICAL (lintas-DB / belum
// digarap modul lain, CLAUDE.md §2) — tanpa constraint MySQL.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->create('emp_employee', function (Blueprint $table) {
            $table->id('employee_id');
            $table->string('employee_code', 20)->unique()->comment('Auto: {company_code}-{seq 5 digit}, mis. TM-00001');
            $table->foreignId('company_id')->constrained('org_company', 'company_id')->restrictOnDelete();
            $table->foreignId('position_id')->constrained('org_position', 'position_id')->restrictOnDelete();
            $table->string('full_name', 150);
            $table->string('nickname', 50)->nullable();
            $table->string('gender', 1)->comment('L|P');
            $table->string('birth_place', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('nik_ktp', 20)->nullable();
            $table->string('npwp_number', 30)->nullable();
            $table->string('religion', 20)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email_personal', 100)->nullable();
            $table->unsignedBigInteger('village_id_ktp')->nullable()->comment('Logical -> tm2mst.geo_village');
            $table->string('address_detail_ktp', 255)->nullable();
            $table->unsignedBigInteger('village_id_domisili')->nullable()->comment('NULL = sama dgn KTP');
            $table->string('address_detail_domisili', 255)->nullable();
            $table->date('join_date');
            $table->date('resign_date')->nullable();
            $table->string('resign_reason', 255)->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->string('work_status', 15)->default('WORKING')->comment('WORKING|DIRUMAHKAN|CUTI');
            $table->date('work_status_since')->nullable();
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_relation', 50)->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->foreignId('default_machine_id')->nullable()->constrained('prod_machine', 'machine_id')->nullOnDelete();
            $table->foreignId('default_shift_id')->nullable()->constrained('emp_shift', 'shift_id')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });

        Schema::connection('tm2mst')->create('emp_contract', function (Blueprint $table) {
            $table->id('contract_id');
            $table->foreignId('employee_id')->constrained('emp_employee', 'employee_id')->cascadeOnDelete();
            $table->string('contract_no', 50)->nullable();
            $table->string('employment_type', 10)->comment('TETAP|KONTRAK|HARIAN|BORONGAN');
            $table->date('start_date');
            $table->date('end_date')->nullable()->comment('NULL hanya utk TETAP');
            $table->string('salary_type', 10)->comment('PER_MONTH|PER_DAY|PER_UNIT');
            $table->decimal('salary_amount', 18, 4)->default(0);
            $table->boolean('is_probation')->default(false);
            $table->date('probation_end_date')->nullable();
            $table->boolean('is_active')->default(true)->comment('Hanya 1 aktif per employee, enforced app-level');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->index(['employee_id', 'is_active']);
        });

        Schema::connection('tm2mst')->create('emp_family', function (Blueprint $table) {
            $table->id('family_id');
            $table->foreignId('employee_id')->constrained('emp_employee', 'employee_id')->cascadeOnDelete();
            $table->string('relation', 15)->comment('SUAMI|ISTRI|ANAK|ORANG_TUA');
            $table->string('full_name', 150);
            $table->date('birth_date')->nullable();
            $table->string('nik', 20)->nullable();
            $table->boolean('is_deceased')->default(false);
            $table->date('deceased_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });

        Schema::connection('tm2mst')->create('emp_document', function (Blueprint $table) {
            $table->id('document_id');
            $table->foreignId('employee_id')->constrained('emp_employee', 'employee_id')->cascadeOnDelete();
            $table->string('document_type', 20)->comment('KTP|NPWP|SIM|KK|SKCK|PASSPORT|IJAZAH|KONTRAK|LAINNYA');
            $table->string('document_no', 50)->nullable();
            $table->string('file_path', 255)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('uploaded_by_role', 4)->default('HR')->comment('SELF|HR');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });

        Schema::connection('tm2mst')->create('emp_bank_account', function (Blueprint $table) {
            $table->id('bank_account_id');
            $table->foreignId('employee_id')->constrained('emp_employee', 'employee_id')->cascadeOnDelete();
            $table->string('bank_name', 100);
            $table->string('account_no', 50);
            $table->string('account_holder_name', 150);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });

        Schema::connection('tm2mst')->create('emp_education', function (Blueprint $table) {
            $table->id('education_id');
            $table->foreignId('employee_id')->constrained('emp_employee', 'employee_id')->cascadeOnDelete();
            $table->string('education_type', 20)->comment('SERTIFIKASI|SEMINAR|PELATIHAN|PENDIDIKAN_FORMAL');
            $table->string('activity_name', 200);
            $table->string('organizer', 150)->nullable();
            $table->date('activity_date');
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->decimal('cost', 18, 4)->default(0);
            $table->string('funded_by', 15)->comment('PERUSAHAAN|PRIBADI|SHARING');
            $table->string('certificate_path', 255)->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_bonding')->default(false);
            $table->string('bonding_note', 255)->nullable();
            $table->string('uploaded_by_role', 4)->default('SELF')->comment('SELF|HR');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::connection('tm2mst')->create('emp_bpjs_kesehatan', function (Blueprint $table) {
            $table->foreignId('employee_id')->primary()->constrained('emp_employee', 'employee_id')->cascadeOnDelete();
            $table->string('no_kepesertaan', 30)->nullable();
            $table->string('kelas_rawat', 5)->nullable()->comment('I|II|III');
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('dependents_count')->default(0);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('updated_by', 100)->nullable();
        });

        Schema::connection('tm2mst')->create('emp_bpjs_ketenagakerjaan', function (Blueprint $table) {
            $table->foreignId('employee_id')->primary()->constrained('emp_employee', 'employee_id')->cascadeOnDelete();
            $table->string('no_kepesertaan', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('program_jkk')->default(true);
            $table->boolean('program_jkm')->default(true);
            $table->boolean('program_jht')->default(true);
            $table->boolean('program_jp')->default(true);
            $table->boolean('program_jkp')->default(true);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('updated_by', 100)->nullable();
        });

        Schema::connection('tm2mst')->create('emp_disciplinary', function (Blueprint $table) {
            $table->id('disciplinary_id');
            $table->foreignId('employee_id')->constrained('emp_employee', 'employee_id')->cascadeOnDelete();
            $table->string('type', 20)->comment('TEGURAN_VERBAL|SP1|SP2|SP3|ALPHA');
            $table->date('incident_date');
            $table->string('reason', 255);
            $table->unsignedBigInteger('related_leave_request_id')->nullable()->comment('Logical -> emp_leave_request');
            $table->string('sanksi_referensi', 255)->nullable()->comment('Teks referensi SOP, bukan kolom dihitung aplikasi');
            $table->string('issued_by', 100);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['employee_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('emp_disciplinary');
        Schema::connection('tm2mst')->dropIfExists('emp_bpjs_ketenagakerjaan');
        Schema::connection('tm2mst')->dropIfExists('emp_bpjs_kesehatan');
        Schema::connection('tm2mst')->dropIfExists('emp_education');
        Schema::connection('tm2mst')->dropIfExists('emp_bank_account');
        Schema::connection('tm2mst')->dropIfExists('emp_document');
        Schema::connection('tm2mst')->dropIfExists('emp_family');
        Schema::connection('tm2mst')->dropIfExists('emp_contract');
        Schema::connection('tm2mst')->dropIfExists('emp_employee');
    }
};
