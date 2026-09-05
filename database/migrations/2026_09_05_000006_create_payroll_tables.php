<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// docs/attendancepayroll_tier3.md §4 + docs/payroll_encryption_decision.md --
// tm2pyr masih 0 tabel (modul Payroll belum dibangun sama sekali). employee_id
// di sini LINTAS-DB ke tm2mst.emp_employee -- BUKAN foreignId/constrained
// (FK MySQL tidak bisa lintas-database, CLAUDE.md §2), validasi keberadaan
// dilakukan di Laravel (exists:tm2mst.emp_employee,employee_id).
//
// pyr_employee_salary adalah tabel BARU yang tidak ada di tier3.md maupun
// paket referensi docs/payroll_timeattendance/ -- gap yang ditemukan saat
// implementasi: formula prorata (Tier 2 §3) butuh nilai gaji_pokok/tunjangan
// PER PEGAWAI sebagai dasar, dan itu belum punya rumah tabel di dokumen
// manapun. Ditaruh di tm2pyr (bukan tm2mst) karena berisi basic_salary_monthly
// yang wajib terenkripsi -- konsisten dgn alasan tm2pyr dipisah DB (CLAUDE.md
// §2: "Payroll system with Encrypted data").
//
// Kolom terenkripsi (AES-256-GCM via App\Casts\PayrollEncrypted, key
// VERBA_SANCTA di tm2sys.sys_constant) disimpan sbg text: basic_salary_monthly
// (pyr_employee_salary), total_pokok & total_gaji_bersih (pyr_payslip) --
// scope PERSIS sesuai docs/payroll_encryption_decision.md §1 (gaji pokok +
// take-home), kolom lain tetap plaintext.
return new class extends Migration
{
    public function up(): void
    {
        $pyr = Schema::connection('tm2pyr');

        $pyr->create('pyr_employee_salary', function (Blueprint $table) {
            $table->id('id_salary');
            $table->unsignedBigInteger('employee_id')->comment('Lintas-DB ke tm2mst.emp_employee, validasi di Laravel');
            $table->text('basic_salary_monthly')->comment('ENCRYPTED (PayrollEncrypted cast) -- gaji pokok bulanan');
            $table->decimal('tj_keahlian_monthly', 14, 2)->default(0);
            $table->decimal('tj_loyalitas_monthly', 14, 2)->default(0);
            $table->decimal('tj_prestasi_monthly', 14, 2)->default(0);
            $table->decimal('bonus_pagi_amount', 14, 2)->default(0);
            $table->decimal('bonus_hadir_amount', 14, 2)->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable()->comment('NULL = masih berlaku, histori disimpan bukan di-overwrite (prinsip rate versioned CLAUDE.md §4)');
            $table->boolean('activate')->default(true)->comment('1 baris activate=1 per pegawai = rate berlaku saat ini');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->index(['employee_id', 'activate']);
        });

        $pyr->create('pyr_status_rule', function (Blueprint $table) {
            $table->id('id_rule');
            $table->string('status_code', 10);
            $table->string('component_code', 20)->comment('POKOK|TJ_KEAHLIAN|TJ_LOYALITAS|TJ_PRESTASI|BONUS_PAGI|BONUS_HADIR');
            $table->string('effect', 10)->comment('FULL|ZERO|PRORATA');
            $table->string('condition_note', 255)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable()->comment('NULL = masih berlaku; histori perubahan kebijakan disimpan, bukan di-overwrite');
            $table->unique(['status_code', 'component_code', 'effective_from'], 'uq_rule_status_component_effdate');
        });

        $pyr->create('pyr_denda_rule', function (Blueprint $table) {
            $table->id('id_denda');
            $table->string('status_code', 10);
            $table->decimal('denda_amount', 12, 2);
            $table->boolean('per_event')->default(true)->comment('1=per kejadian (mis. F/FF), 0=flat per periode');
            $table->string('condition_note', 255)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
        });

        $pyr->create('pyr_payroll_run', function (Blueprint $table) {
            $table->id('id_run');
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->string('run_status', 20)->default('DRAFT')->comment('DRAFT|CALCULATED|REVIEWED|FINALIZED');
            $table->string('triggered_by', 100);
            $table->timestamp('triggered_at')->useCurrent();
            $table->string('finalized_by', 100)->nullable();
            $table->dateTime('finalized_at')->nullable();
            $table->unique(['period_year', 'period_month'], 'uq_run_period');
        });

        $pyr->create('pyr_payslip', function (Blueprint $table) {
            $table->id('id_payslip');
            $table->foreignId('id_run')->constrained('pyr_payroll_run', 'id_run')->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id')->comment('Lintas-DB ke tm2mst.emp_employee, validasi di Laravel');
            $table->text('total_pokok')->comment('ENCRYPTED (PayrollEncrypted cast)');
            $table->decimal('total_tj_keahlian', 14, 2)->default(0);
            $table->decimal('total_tj_loyalitas', 14, 2)->default(0);
            $table->decimal('total_tj_prestasi', 14, 2)->default(0);
            $table->decimal('total_bonus_pagi', 14, 2)->default(0);
            $table->decimal('total_bonus_hadir', 14, 2)->default(0);
            $table->decimal('total_uang_roti', 14, 2)->default(0);
            $table->decimal('total_denda', 14, 2)->default(0);
            $table->text('total_gaji_bersih')->comment('ENCRYPTED (PayrollEncrypted cast) -- net take-home');
            $table->unique(['id_run', 'employee_id'], 'uq_payslip_run_emp');
        });

        $pyr->create('pyr_payslip_detail', function (Blueprint $table) {
            $table->id('id_detail');
            $table->foreignId('id_payslip')->constrained('pyr_payslip', 'id_payslip')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('status_code', 10);
            $table->string('component_code', 20);
            $table->decimal('amount', 14, 2)->comment('Nilai +/- terhadap komponen, bisa negatif utk potongan -- plaintext (breakdown audit, di luar scope 2 kolom enkripsi)');
            $table->string('note', 255)->nullable();
            $table->index(['id_payslip', 'attendance_date']);
        });
    }

    public function down(): void
    {
        $pyr = Schema::connection('tm2pyr');
        $pyr->dropIfExists('pyr_payslip_detail');
        $pyr->dropIfExists('pyr_payslip');
        $pyr->dropIfExists('pyr_payroll_run');
        $pyr->dropIfExists('pyr_denda_rule');
        $pyr->dropIfExists('pyr_status_rule');
        $pyr->dropIfExists('pyr_employee_salary');
    }
};
