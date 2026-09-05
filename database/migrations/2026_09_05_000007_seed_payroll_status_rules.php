<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Seed pyr_status_rule + pyr_denda_rule dari docs/attendancepayroll_tier2.md
// §4.1-4.3 (rule matrix data-driven, prinsip: nominal & threshold ADALAH data,
// bukan hardcode kode -- lihat Tier 2 §1).
//
// PENTING -- 2 hal yang MASIH [DELTA-CONFIRM]/[Open Item] di dokumen sumber,
// diseed dgn asumsi terbaik dokumen (bukan keputusan final bisnis):
// 1. PAT-5/PAT+5 pakai prorata (bukan ZERO total) -- Tier 2 §4.2 catatan,
//    ditandai wajib dikonfirmasi Telma/Jo sebelum Tier 3 dikunci.
// 2. Tidak-finger ke-3+ dlm periode -- BELUM diseed (Open Item, blm ada
//    aturan), akan default reklasifikasi ke A oleh AttendanceCalculationService
//    sampai ada keputusan eksplisit.
//
// SP1/SP2/SP3/SKR/DRM SENGAJA TIDAK diseed di sini -- efeknya (potongan 50%
// x N hari kerja berturut, atau zero-total) dihitung langsung oleh
// PayrollEngineService dari emp_disciplinary aktif (Tier 2 §6), bukan lewat
// rule matrix status harian.
return new class extends Migration
{
    private const FULL_ALL = ['POKOK' => 'FULL', 'TJ_KEAHLIAN' => 'FULL', 'TJ_LOYALITAS' => 'FULL', 'TJ_PRESTASI' => 'FULL', 'BONUS_PAGI' => 'FULL', 'BONUS_HADIR' => 'FULL'];

    private const ZERO_ALL = ['POKOK' => 'ZERO', 'TJ_KEAHLIAN' => 'ZERO', 'TJ_LOYALITAS' => 'ZERO', 'TJ_PRESTASI' => 'ZERO', 'BONUS_PAGI' => 'ZERO', 'BONUS_HADIR' => 'ZERO'];

    private const POKOK_ONLY = ['POKOK' => 'FULL', 'TJ_KEAHLIAN' => 'ZERO', 'TJ_LOYALITAS' => 'ZERO', 'TJ_PRESTASI' => 'ZERO', 'BONUS_PAGI' => 'ZERO', 'BONUS_HADIR' => 'ZERO'];

    private const TOLERANSI_FINGER = ['POKOK' => 'FULL', 'TJ_KEAHLIAN' => 'FULL', 'TJ_LOYALITAS' => 'FULL', 'TJ_PRESTASI' => 'FULL', 'BONUS_PAGI' => 'ZERO', 'BONUS_HADIR' => 'FULL'];

    private const TIME_VARIANT_LATE = ['POKOK' => 'PRORATA', 'TJ_KEAHLIAN' => 'PRORATA', 'TJ_LOYALITAS' => 'FULL', 'TJ_PRESTASI' => 'FULL', 'BONUS_PAGI' => 'ZERO', 'BONUS_HADIR' => 'FULL'];

    private const TIME_VARIANT_PA = ['POKOK' => 'PRORATA', 'TJ_KEAHLIAN' => 'PRORATA', 'TJ_LOYALITAS' => 'FULL', 'TJ_PRESTASI' => 'FULL', 'BONUS_PAGI' => 'ZERO', 'BONUS_HADIR' => 'ZERO'];

    private const KJK = ['POKOK' => 'PRORATA', 'TJ_KEAHLIAN' => 'PRORATA', 'TJ_LOYALITAS' => 'FULL', 'TJ_PRESTASI' => 'FULL', 'BONUS_PAGI' => 'FULL', 'BONUS_HADIR' => 'ZERO'];

    public function up(): void
    {
        $effectiveFrom = '2026-01-01';
        $rows = [];

        $matrix = [
            'H' => self::FULL_ALL,
            'S' => ['POKOK' => 'FULL', 'TJ_KEAHLIAN' => 'ZERO', 'TJ_LOYALITAS' => 'ZERO', 'TJ_PRESTASI' => 'ZERO', 'BONUS_PAGI' => 'ZERO', 'BONUS_HADIR' => 'ZERO'],
            'I' => self::ZERO_ALL,
            'IS' => self::POKOK_ONLY,
            'A' => self::ZERO_ALL,
            'F' => self::TOLERANSI_FINGER,
            'FF' => ['POKOK' => 'FULL', 'TJ_KEAHLIAN' => 'FULL', 'TJ_LOYALITAS' => 'FULL', 'TJ_PRESTASI' => 'FULL', 'BONUS_PAGI' => 'ZERO', 'BONUS_HADIR' => 'ZERO'],
            'C' => self::POKOK_ONLY,
            'CL' => self::POKOK_ONLY,
            'DRM' => self::POKOK_ONLY,
            'SKR' => self::ZERO_ALL,
            'T-5' => self::TIME_VARIANT_LATE,
            'T+5' => self::TIME_VARIANT_LATE,
            'T+15' => self::TIME_VARIANT_LATE,
            'PA' => self::TIME_VARIANT_PA,
            'PAT-5' => self::TIME_VARIANT_PA,
            'PAT+5' => self::TIME_VARIANT_PA,
            'KJK' => self::KJK,
        ];

        foreach ($matrix as $statusCode => $components) {
            foreach ($components as $componentCode => $effect) {
                $rows[] = [
                    'status_code' => $statusCode,
                    'component_code' => $componentCode,
                    'effect' => $effect,
                    'condition_note' => null,
                    'effective_from' => $effectiveFrom,
                    'effective_to' => null,
                ];
            }
        }

        DB::connection('tm2pyr')->table('pyr_status_rule')->insert($rows);

        DB::connection('tm2pyr')->table('pyr_denda_rule')->insert([
            ['status_code' => 'I', 'denda_amount' => 25000, 'per_event' => true, 'condition_note' => null, 'effective_from' => $effectiveFrom, 'effective_to' => null],
            ['status_code' => 'A', 'denda_amount' => 100000, 'per_event' => true, 'condition_note' => null, 'effective_from' => $effectiveFrom, 'effective_to' => null],
            ['status_code' => 'T+5', 'denda_amount' => 25000, 'per_event' => true, 'condition_note' => 'Hanya jika pelanggaran ke-4 dst dalam periode (late_violation_seq_in_period > 3)', 'effective_from' => $effectiveFrom, 'effective_to' => null],
            ['status_code' => 'T+15', 'denda_amount' => 25000, 'per_event' => true, 'condition_note' => 'Selalu, tanpa syarat akumulasi 3x', 'effective_from' => $effectiveFrom, 'effective_to' => null],
            ['status_code' => 'PAT+5', 'denda_amount' => 25000, 'per_event' => true, 'condition_note' => null, 'effective_from' => $effectiveFrom, 'effective_to' => null],
            ['status_code' => 'F', 'denda_amount' => 10000, 'per_event' => true, 'condition_note' => null, 'effective_from' => $effectiveFrom, 'effective_to' => null],
            ['status_code' => 'FF', 'denda_amount' => 10000, 'per_event' => true, 'condition_note' => null, 'effective_from' => $effectiveFrom, 'effective_to' => null],
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2pyr')->table('pyr_denda_rule')->truncate();
        DB::connection('tm2pyr')->table('pyr_status_rule')->truncate();
    }
};
