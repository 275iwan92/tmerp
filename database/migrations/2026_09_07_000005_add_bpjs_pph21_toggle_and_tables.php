<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// BPJS & PPh21 -- formula diadopsi dari docs/payroll_timeattendance/
// tm2hrm_laravel_php83.zip (Pph21TerService/BpjsCalculatorService), TAPI
// nominal/persentase/plafon dipindah ke TABEL (pyr_pph21_ter_bracket,
// pyr_bpjs_rate) sesuai instruksi "DON'T HARDCODE" -- paket referensi
// aslinya hardcode match()/const, di sini jadi data versioned by
// effective_from/to spt rate lain (rate BPJS/bracket PPh21 memang direvisi
// pemerintah dari waktu ke waktu, PP 58/2023 contohnya revisi dari aturan
// sebelumnya).
//
// bpjs_active/pph21_active per pegawai (permintaan user 2026-09-07): "tidak
// semua aktif mengikuti aturan ini tetapi boleh dimudahkan agar kedepan
// tinggal di-set mana yang pakai mana yang tidak" -- default FALSE (opt-in),
// bukan otomatis semua kena potong begitu kolomnya ada.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2pyr')->table('pyr_employee_salary', function (Blueprint $table) {
            $table->string('ptkp_status', 10)->nullable()->after('bonus_hadir_amount')->comment('TK/0..TK/3, K/0..K/3 -- dasar kategori TER PPh21');
            $table->boolean('bpjs_active')->default(false)->after('ptkp_status')->comment('1 = pegawai ini ikut potongan BPJS TK+Kes');
            $table->boolean('pph21_active')->default(false)->after('bpjs_active')->comment('1 = pegawai ini kena potong PPh21 TER');
        });

        Schema::connection('tm2pyr')->table('pyr_payslip', function (Blueprint $table) {
            $table->decimal('total_bpjs_employee', 14, 2)->default(0)->after('total_denda')->comment('Potongan BPJS TK(JHT+JP)+Kes sisi karyawan -- 0 kalau bpjs_active=false');
            $table->decimal('total_pph21', 14, 2)->default(0)->after('total_bpjs_employee')->comment('Potongan PPh21 TER -- 0 kalau pph21_active=false');
        });

        Schema::connection('tm2pyr')->create('pyr_pph21_ter_bracket', function (Blueprint $table) {
            $table->id('id_bracket');
            $table->string('category', 1)->comment('A|B|C -- kategori TER PP 58/2023 & PMK 168/2023');
            $table->decimal('bruto_upper_bound', 14, 2)->nullable()->comment('Batas atas penghasilan bruto bulanan utk bracket ini; NULL = bracket tertinggi (tanpa batas atas)');
            $table->decimal('rate', 8, 6)->comment('Tarif TER, mis. 0.0025 = 0.25%');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->index(['category', 'bruto_upper_bound']);
        });

        Schema::connection('tm2pyr')->create('pyr_bpjs_rate', function (Blueprint $table) {
            $table->id('id_rate');
            $table->string('component', 20)->comment('JHT_EMPLOYEE|JHT_COMPANY|JP_EMPLOYEE|JP_COMPANY|JKK_COMPANY|JKM_COMPANY|KES_EMPLOYEE|KES_COMPANY');
            $table->decimal('rate', 8, 6);
            $table->decimal('cap_amount', 14, 2)->nullable()->comment('Plafon dasar perhitungan (mis. plafon BPJS Kes/JP) -- NULL = tanpa plafon');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unique(['component', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::connection('tm2pyr')->dropIfExists('pyr_bpjs_rate');
        Schema::connection('tm2pyr')->dropIfExists('pyr_pph21_ter_bracket');

        Schema::connection('tm2pyr')->table('pyr_payslip', function (Blueprint $table) {
            $table->dropColumn(['total_bpjs_employee', 'total_pph21']);
        });

        Schema::connection('tm2pyr')->table('pyr_employee_salary', function (Blueprint $table) {
            $table->dropColumn(['ptkp_status', 'bpjs_active', 'pph21_active']);
        });
    }
};
