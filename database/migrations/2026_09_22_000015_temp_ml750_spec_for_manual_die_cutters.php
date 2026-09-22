<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Spek SEMENTARA utk 3 die cutter manual (DIE-01 Ruian, DIE-02 Shanghai,
// DIE-03 Daxiang) -- ketiganya kosong speknya (BACKLOG 27.1). User
// konfirmasi 2026-09-22 (foto mesin "Pond Manual ML 750"): ketiganya
// sekelas mesin manual platen seperti itu, dan MENGIZINKAN pakai spek
// generik ML750 sbg placeholder sampai Sabtu (foto nameplate asli tiap
// mesin). BUKAN spek per-unit terverifikasi -- lihat kolom `notes`.
//
// Sumber (2 situs independen cocok persis, dicek 2026-09-22):
//   platen 750x520mm, speed 25+-2 strokes/min, motor 2.2kW, 380V/60Hz.
//   https://www.smart-equipments.com/postpress/die-cutting-machine/die-cutting-machine-ml750/
//   WebSearch ringkasan multi-listing (made-in-china.com, chinaguangya.com, dst).
//
// DIE-04 (Daxiang Auto) SENGAJA TIDAK disentuh -- kategori otomatis beda,
// tidak ada data spek yang diberikan user utk itu.
return new class extends Migration
{
    private const CODES = ['DIE-01', 'DIE-02', 'DIE-03'];

    private const NOTE = 'PLACEHOLDER 2026-09-22: spek generik ML750 (riset online, 2 sumber cocok), '
        .'BELUM diverifikasi ke nameplate mesin sungguhan. User akan foto nameplate asli Sabtu -- '
        .'update lagi begitu ada data real, jangan anggap final.';

    public function up(): void
    {
        DB::connection('tm2mst')->table('prod_machine')
            ->whereIn('machine_code', self::CODES)
            ->update([
                'max_sheet_length_mm' => 750,
                'max_sheet_width_mm' => 520,
                'max_speed_per_hour' => 1500, // 25 strokes/min x 60 (nominal pabrikan, bukan hasil ukur lapangan)
                'power_kw' => 2.2,
                'notes' => self::NOTE,
                'updated_by' => 'system',
            ]);
    }

    public function down(): void
    {
        DB::connection('tm2mst')->table('prod_machine')
            ->whereIn('machine_code', self::CODES)
            ->where('notes', self::NOTE)
            ->update([
                'max_sheet_length_mm' => null,
                'max_sheet_width_mm' => null,
                'max_speed_per_hour' => null,
                'power_kw' => null,
                'notes' => null,
            ]);
    }
};
