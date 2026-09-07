<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// [DELTA-CONFIRM] Multiplier default generik mengikuti pola umum UU
// Ketenagakerjaan RI (lembur hari kerja 1.5x jam pertama, lembur hari
// libur/Minggu 2.0x) -- BUKAN angka yang sudah dikonfirmasi Telma/Jo utk
// CV Telasih Makmur spesifik. Data-driven (tabel `pyr_overtime_rate`),
// jadi gampang diralat tanpa ubah kode kalau kebijakan riil berbeda.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2pyr')->table('pyr_overtime_rate')->insert([
            ['overtime_type' => 'WORKDAY', 'multiplier' => 1.5, 'effective_from' => '2026-01-01', 'effective_to' => null],
            ['overtime_type' => 'EARLY', 'multiplier' => 1.5, 'effective_from' => '2026-01-01', 'effective_to' => null],
            ['overtime_type' => 'HOLIDAY', 'multiplier' => 2.0, 'effective_from' => '2026-01-01', 'effective_to' => null],
            ['overtime_type' => 'SUNDAY', 'multiplier' => 2.0, 'effective_from' => '2026-01-01', 'effective_to' => null],
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2pyr')->table('pyr_overtime_rate')->truncate();
    }
};
