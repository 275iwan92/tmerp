<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Permintaan user 2026-09-03: default periode berulang (accounting/stock
// opname/payroll) sbg konstanta di tm2sys.sys_constant -- murni konfigurasi
// NILAI default, BUKAN membangun engine/PeriodService yang memakainya
// (itu item terpisah, masih 0% di BACKLOG.md §12.7 "Period Manager").
// constant_key harus unik GLOBAL (SysConstant::text()/number() query tanpa
// scope category), bukan cuma unik per kategori.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->insert([
            // Periode Accounting tahunan: 1 Jan - 31 Des (kalender = tahun buku).
            [
                'constant_key' => 'FISCAL_YEAR_START_MONTH', 'category_code' => 'FINANCE', 'constant_group' => 'PERIODE',
                'constant_number' => 1, 'constant_text' => null,
                'description' => 'Bulan mulai tahun buku accounting (1 = Januari)', 'activate' => 1,
            ],
            [
                'constant_key' => 'FISCAL_YEAR_START_DAY', 'category_code' => 'FINANCE', 'constant_group' => 'PERIODE',
                'constant_number' => 1, 'constant_text' => null,
                'description' => 'Tanggal mulai tahun buku accounting', 'activate' => 1,
            ],
            [
                'constant_key' => 'FISCAL_YEAR_END_MONTH', 'category_code' => 'FINANCE', 'constant_group' => 'PERIODE',
                'constant_number' => 12, 'constant_text' => null,
                'description' => 'Bulan akhir tahun buku accounting (12 = Desember)', 'activate' => 1,
            ],
            [
                'constant_key' => 'FISCAL_YEAR_END_DAY', 'category_code' => 'FINANCE', 'constant_group' => 'PERIODE',
                'constant_number' => 31, 'constant_text' => null,
                'description' => 'Tanggal akhir tahun buku accounting', 'activate' => 1,
            ],

            // Periode Stock Opname: tiap tanggal 1 (setiap bulan).
            [
                'constant_key' => 'STOCK_OPNAME_DAY_OF_MONTH', 'category_code' => 'INVENTORY', 'constant_group' => 'PERIODE',
                'constant_number' => 1, 'constant_text' => null,
                'description' => 'Tanggal pelaksanaan stock opname tiap bulan (1 = tanggal 1)', 'activate' => 1,
            ],

            // Periode gajian bulanan: tanggal akhir bulan (28/29/30/31, tergantung
            // bulan) -- BUKAN angka tetap, jadi disimpan sbg flag teks, bukan number.
            [
                'constant_key' => 'PAYROLL_MONTHLY_CUTOFF_DAY', 'category_code' => 'PAYROLL', 'constant_group' => 'PERIODE',
                'constant_number' => 0, 'constant_text' => 'END_OF_MONTH',
                'description' => 'Cutoff gajian bulanan = hari TERAKHIR tiap bulan (28-31, dinamis per bulan -- bukan angka tetap, kode harus hitung via Carbon endOfMonth(), bukan baca constant_number)', 'activate' => 1,
            ],

            // Periode akhir hitung gajian mingguan: tiap hari Jumat. Carbon
            // dayOfWeek (Min=0..Sab=6) DAN dayOfWeekIso (Sen=1..Min=7) sama-sama
            // Jumat=5 -- tidak ambigu, dicatat eksplisit di description.
            [
                'constant_key' => 'PAYROLL_WEEKLY_CUTOFF_DOW', 'category_code' => 'PAYROLL', 'constant_group' => 'PERIODE',
                'constant_number' => 5, 'constant_text' => null,
                'description' => 'Hari cutoff gajian mingguan = Jumat (5, sama di Carbon dayOfWeek maupun dayOfWeekIso)', 'activate' => 1,
            ],
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->whereIn('constant_key', [
            'FISCAL_YEAR_START_MONTH', 'FISCAL_YEAR_START_DAY', 'FISCAL_YEAR_END_MONTH', 'FISCAL_YEAR_END_DAY',
            'STOCK_OPNAME_DAY_OF_MONTH', 'PAYROLL_MONTHLY_CUTOFF_DAY', 'PAYROLL_WEEKLY_CUTOFF_DOW',
        ])->delete();
    }
};
