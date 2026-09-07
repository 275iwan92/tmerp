<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// HIGH IMPROVEMENT payroll engine (2026-09-07), dipicu setelah mempelajari
// stored procedure sistem lama (`docs/ddl/tmhrm.sql`: sp_process_attlogs,
// sp_patch_att_summ_by_emp, sp_attendance_exception_report). 2 gap nyata
// yang ditemukan dibanding sistem lama tsb:
// 1. Lembur SELAMA INI cuma dapat "Uang Roti" flat Rp5.000 (Tier 2 §4.4) --
//    TIDAK ADA pembayaran lembur per jam sama sekali, padahal itu jelas
//    komponen gaji nyata. Sistem lama punya overtime_type (WORKDAY/HOLIDAY/
//    SUNDAY/EARLY) dgn jam berbeda -- diadopsi di sini + multiplier per
//    tipe DI TABEL (bukan hardcode), karena UU Ketenagakerjaan mewajibkan
//    tarif lembur beda utk hari kerja vs libur/Minggu.
// 2. Kalau `pyr_employee_salary` aktif tidak ada, payslip SEBELUMNYA diam-
//    diam TIDAK DIBUAT (pegawai "hilang" dari payroll run tanpa jejak) --
//    sistem lama punya `payroll_ready`/`anomaly_notes` (tma_attendance_summary)
//    supaya kasus begini KELIHATAN, bukan senyap. Diadopsi sbg `has_anomaly`/
//    `anomaly_note` di `pyr_payslip` + guard di confirm().
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->table('att_daily_status', function (Blueprint $table) {
            $table->string('overtime_type', 10)->nullable()->after('overtime_minutes')->comment('WORKDAY|HOLIDAY|SUNDAY|EARLY -- dasar lookup multiplier pyr_overtime_rate');
        });

        Schema::connection('tm2pyr')->create('pyr_overtime_rate', function (Blueprint $table) {
            $table->id('id_rate');
            $table->string('overtime_type', 10)->comment('WORKDAY|HOLIDAY|SUNDAY|EARLY');
            $table->decimal('multiplier', 4, 2)->comment('Pengali dari tarif per-jam normal, mis. 1.5x/2.0x (sesuai UU Ketenagakerjaan, ASUMSI DEFAULT -- lihat catatan seeder)');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unique(['overtime_type', 'effective_from']);
        });

        Schema::connection('tm2pyr')->table('pyr_payslip', function (Blueprint $table) {
            $table->decimal('total_overtime_pay', 14, 2)->default(0)->after('total_uang_roti')->comment('Lembur per jam x tarif/jam x multiplier tipe hari -- terpisah dari Uang Roti (bonus flat produksi, Tier 2 §4.4)');
            $table->boolean('has_anomaly')->default(false)->after('total_gaji_bersih')->comment('1 = ada masalah data (mis. belum ada pyr_employee_salary aktif) -- payslip tetap dibuat (angka 0) supaya pegawai tidak "hilang" senyap dari run');
            $table->string('anomaly_note', 255)->nullable()->after('has_anomaly');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2pyr')->table('pyr_payslip', function (Blueprint $table) {
            $table->dropColumn(['total_overtime_pay', 'has_anomaly', 'anomaly_note']);
        });

        Schema::connection('tm2pyr')->dropIfExists('pyr_overtime_rate');

        Schema::connection('tm2mst')->table('att_daily_status', function (Blueprint $table) {
            $table->dropColumn('overtime_type');
        });
    }
};
