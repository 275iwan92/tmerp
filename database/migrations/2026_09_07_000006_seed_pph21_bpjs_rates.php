<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Nominal diambil apa adanya dari docs/payroll_timeattendance/
// tm2hrm_laravel_php83.zip (Pph21TerService/BpjsCalculatorService) --
// itu sendiri sudah mengutip PP No. 58/2023 & PMK No. 168/2023 (PPh21 TER)
// dan tarif BPJS TK/Kes yang berlaku umum. BUKAN hasil konsultasi
// akuntan/konsultan pajak proyek ini -- tandai [DELTA-CONFIRM] kalau perlu
// diverifikasi ulang ke aturan resmi terbaru sebelum dipakai produksi nyata.
return new class extends Migration
{
    public function up(): void
    {
        $effectiveFrom = '2026-01-01';
        $brackets = [];

        // Kategori A (TK/0, TK/1, K/0)
        $categoryA = [
            5400000 => 0.0000, 5650000 => 0.0025, 5950000 => 0.0050, 6300000 => 0.0075,
            6750000 => 0.0100, 7500000 => 0.0125, 8550000 => 0.0150, 9650000 => 0.0175,
            10050000 => 0.0200, 10350000 => 0.0225, 10700000 => 0.0250, 11050000 => 0.0300,
        ];
        foreach ($categoryA as $upper => $rate) {
            $brackets[] = ['category' => 'A', 'bruto_upper_bound' => $upper, 'rate' => $rate, 'effective_from' => $effectiveFrom, 'effective_to' => null];
        }
        $brackets[] = ['category' => 'A', 'bruto_upper_bound' => null, 'rate' => 0.0500, 'effective_from' => $effectiveFrom, 'effective_to' => null];

        // Kategori B (TK/2, TK/3, K/1, K/2)
        $categoryB = [6200000 => 0.0000, 6500000 => 0.0025, 6850000 => 0.0050, 7300000 => 0.0075, 9200000 => 0.0100, 10750000 => 0.0150];
        foreach ($categoryB as $upper => $rate) {
            $brackets[] = ['category' => 'B', 'bruto_upper_bound' => $upper, 'rate' => $rate, 'effective_from' => $effectiveFrom, 'effective_to' => null];
        }
        $brackets[] = ['category' => 'B', 'bruto_upper_bound' => null, 'rate' => 0.0300, 'effective_from' => $effectiveFrom, 'effective_to' => null];

        // Kategori C (K/3)
        $categoryC = [6600000 => 0.0000, 6950000 => 0.0025, 7350000 => 0.0050, 7800000 => 0.0075, 8850000 => 0.0100, 9800000 => 0.0125];
        foreach ($categoryC as $upper => $rate) {
            $brackets[] = ['category' => 'C', 'bruto_upper_bound' => $upper, 'rate' => $rate, 'effective_from' => $effectiveFrom, 'effective_to' => null];
        }
        $brackets[] = ['category' => 'C', 'bruto_upper_bound' => null, 'rate' => 0.0300, 'effective_from' => $effectiveFrom, 'effective_to' => null];

        DB::connection('tm2pyr')->table('pyr_pph21_ter_bracket')->insert($brackets);

        DB::connection('tm2pyr')->table('pyr_bpjs_rate')->insert([
            ['component' => 'JHT_EMPLOYEE', 'rate' => 0.0200, 'cap_amount' => null, 'effective_from' => $effectiveFrom, 'effective_to' => null],
            ['component' => 'JHT_COMPANY', 'rate' => 0.0370, 'cap_amount' => null, 'effective_from' => $effectiveFrom, 'effective_to' => null],
            ['component' => 'JP_EMPLOYEE', 'rate' => 0.0100, 'cap_amount' => 10547400.00, 'effective_from' => $effectiveFrom, 'effective_to' => null],
            ['component' => 'JP_COMPANY', 'rate' => 0.0200, 'cap_amount' => 10547400.00, 'effective_from' => $effectiveFrom, 'effective_to' => null],
            ['component' => 'JKK_COMPANY', 'rate' => 0.0024, 'cap_amount' => null, 'effective_from' => $effectiveFrom, 'effective_to' => null],
            ['component' => 'JKM_COMPANY', 'rate' => 0.0030, 'cap_amount' => null, 'effective_from' => $effectiveFrom, 'effective_to' => null],
            ['component' => 'KES_EMPLOYEE', 'rate' => 0.0100, 'cap_amount' => 12000000.00, 'effective_from' => $effectiveFrom, 'effective_to' => null],
            ['component' => 'KES_COMPANY', 'rate' => 0.0400, 'cap_amount' => 12000000.00, 'effective_from' => $effectiveFrom, 'effective_to' => null],
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2pyr')->table('pyr_bpjs_rate')->truncate();
        DB::connection('tm2pyr')->table('pyr_pph21_ter_bracket')->truncate();
    }
};
