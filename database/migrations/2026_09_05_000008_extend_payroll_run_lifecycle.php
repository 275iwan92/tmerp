<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Redesain pyr_payroll_run mengikuti alur bisnis lengkap yang ditulis user
// 2026-09-05 (bukan asumsi bulanan di migration 000006): periode WEEKLY atau
// MONTHLY (bukan cuma tahun+bulan), dan siklus status yang jauh lebih
// panjang dari DRAFT->CALCULATED->REVIEWED->FINALIZED semula --
// DRAFT (dibuat kosong) -> CALCULATED (bisa dijalankan ulang, estimasi H-1
// & proses ulang H) -> VERIFIED (cek jam masuk/keluar semua/random sample)
// -> CONFIRMED (kunci presensi, lanjut ke pembayaran) -> PAID (transfer
// bank/cash sudah dieksekusi, slip terkirim) -> CLOSED (masuk jurnal,
// periode berikutnya otomatis dibuat kosong).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2pyr')->table('pyr_payroll_run', function (Blueprint $table) {
            $table->dropUnique('uq_run_period');
            $table->dropColumn(['period_year', 'period_month', 'finalized_by', 'finalized_at']);
        });

        Schema::connection('tm2pyr')->table('pyr_payroll_run', function (Blueprint $table) {
            $table->string('period_type', 10)->after('id_run')->comment('WEEKLY|MONTHLY');
            $table->date('period_start')->after('period_type');
            $table->date('period_end')->after('period_start');
            $table->string('verification_method', 15)->nullable()->comment('FULL|RANDOM_SAMPLE');
            $table->json('verified_sample_employee_ids')->nullable()->comment('Diisi kalau verification_method=RANDOM_SAMPLE');
            $table->string('verified_by', 100)->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->string('confirmed_by', 100)->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->string('paid_by', 100)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('payslip_sent_at')->nullable();
            $table->string('closed_by', 100)->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->unsignedBigInteger('next_run_id')->nullable()->comment('Periode berikutnya, dibuat otomatis saat close()');
            $table->unique(['period_type', 'period_start'], 'uq_run_period_type_start');
        });

        // Pegawai mana ikut siklus WEEKLY vs MONTHLY (mis. buruh produksi
        // mingguan, staf kantor bulanan) -- default MONTHLY, HR isi manual
        // per pegawai kalau perlu WEEKLY.
        Schema::connection('tm2mst')->table('emp_employee', function (Blueprint $table) {
            $table->string('payment_frequency', 10)->default('MONTHLY')->after('default_shift_id')->comment('WEEKLY|MONTHLY -- menentukan pyr_payroll_run.period_type mana yang memproses pegawai ini');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('emp_employee', function (Blueprint $table) {
            $table->dropColumn('payment_frequency');
        });

        Schema::connection('tm2pyr')->table('pyr_payroll_run', function (Blueprint $table) {
            $table->dropUnique('uq_run_period_type_start');
            $table->dropColumn([
                'period_type', 'period_start', 'period_end',
                'verification_method', 'verified_sample_employee_ids', 'verified_by', 'verified_at',
                'confirmed_by', 'confirmed_at', 'paid_by', 'paid_at', 'payslip_sent_at',
                'closed_by', 'closed_at', 'next_run_id',
            ]);
        });

        Schema::connection('tm2pyr')->table('pyr_payroll_run', function (Blueprint $table) {
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->string('finalized_by', 100)->nullable();
            $table->dateTime('finalized_at')->nullable();
            $table->unique(['period_year', 'period_month'], 'uq_run_period');
        });
    }
};
