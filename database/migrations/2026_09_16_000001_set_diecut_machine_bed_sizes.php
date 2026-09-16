<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Ukuran meja mesin pond/plong dari user (2026-09-16): Pond #1 & #2 sama
// 70 x 102 cm, Pond #3 60 x 80 cm, Pond #4 (auto) 108 x 78 cm.
//
// Dibutuhkan ProDev: canvas wajib muat di meja mesin cetak DAN meja mesin
// plong. Sebelum ini max_paper_size seluruh mesin Potong kosong, jadi batas
// plong tidak pernah bisa ditegakkan.
//
// Pemetaan ke baris prod_machine dipilih berdasarkan bukti pemakaian di
// 7.423 baris prod_card_process_legacy, bukan tebakan nama:
//   - "PLONG 1" dipakai 361 kali  -> prod_machine 'PLONG-01' (Plong 1)
//   - "PLONG 2" 9 kali, "PLONG 3" 13 kali -> PLONG_2 / PLONG_3
//   - "PLONG 4" belum pernah muncul di kartu legacy, tapi user menyebutnya
//     sebagai mesin auto yang ada sekarang -> PLONG_4.
// Disimpan format "LxW" (sisi panjang dulu) mengikuti cara baca parseBed()
// di layar ProDev, sama seperti bed mesin cetak.
//
// TIDAK disentuh: PLONG (baris legacy tanpa nomor) dan PLONG 5 -- user cuma
// menyebut 4 mesin pond, tapi menonaktifkan baris master butuh konfirmasi
// tersendiri (lihat catatan di akhir sesi).
return new class extends Migration
{
    private const BEDS = [
        'PLONG-01' => '102x70',   // Pond #1
        'PLONG_2' => '102x70',    // Pond #2 (sama dengan #1)
        'PLONG_3' => '80x60',     // Pond #3
        'PLONG_4' => '108x78',    // Pond #4 (auto)
    ];

    public function up(): void
    {
        foreach (self::BEDS as $code => $size) {
            DB::connection('tm2mst')->table('prod_machine')
                ->where('machine_code', $code)
                ->update(['max_paper_size' => $size, 'updated_by' => 'claude_code']);
        }
    }

    public function down(): void
    {
        DB::connection('tm2mst')->table('prod_machine')
            ->whereIn('machine_code', array_keys(self::BEDS))
            ->update(['max_paper_size' => null, 'updated_by' => 'claude_code']);
    }
};
