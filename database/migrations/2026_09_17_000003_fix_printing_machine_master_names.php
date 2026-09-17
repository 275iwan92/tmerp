<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Betulkan master mesin cetak (instruksi user 2026-09-17: "Nama machine yang
// benar KOMORI GL 440 ... sistem yang dahulu itu jadul, coba di betulkan").
//
// Keadaan sebelumnya: baris master V2 dinamai placeholder "Cetak 1"/"Cetak 2"
// dengan brand tebakan (Heidelberg SM 74 / Komori L528), sementara nama mesin
// sebenarnya ada di data legacy. Bukti dari 7.423 baris prod_card_process_legacy:
//     KOMORI_GL440  1005 job   <- mesin cetak utama
//     KOMORI_L40     126 job   <- mesin cetak kedua
//     FUJI            76 job
//     SAKURA          44 job
//
// DI-RENAME DI TEMPAT, bukan dihapus/diganti baris: id 1 & 2 sudah dipakai
// transaksi V2 nyata (prd_wo_operation, prod_part_process, rate mesin, roster,
// work center). Mengganti barisnya akan memutus referensi itu.
//
// Ukuran meja diisi dari spesifikasi pabrikan mesin yang bersangkutan: Komori
// Lithrone GL-440 dan Lithrone 40 sama-sama mesin 40 inci, lembar maksimum
// 720 x 1030 mm = 103 x 72 cm. Angka ini cocok dengan meja Pond #1/#2 yang
// disebut user (70 x 102 cm) -- mesin plong memang dibuat sepadan dgn lembar
// cetak, jadi saling menguatkan. Bed lama (74 x 53) berasal dari brand tebakan
// dan itulah yang selama ini membatasi hasil optimasi.
//
// CATATAN: identitas id 1 (KOMORI GL 440) ditegaskan user. Identitas id 2
// disimpulkan dari peringkat pemakaian legacy (KOMORI_L40 = mesin cetak kedua)
// -- kalau ternyata bukan, tinggal ganti nama/brand-nya, tidak ada data lain
// yang ikut berubah.
//
// Baris legacy ber-nama-orang (KOMORI_GL440_FANDIK/_AGUS/_DIMAS/_YANTO) sudah
// dinonaktifkan di migration 2026_09_14_000001 dan tetap dibiarkan nonaktif.
return new class extends Migration
{
    private const FIX = [
        1 => [
            'machine_code' => 'KOMORI-GL440',
            'machine_name' => 'KOMORI GL 440',
            'brand_model' => 'Komori Lithrone GL-440',
            'max_paper_size' => '103x72',
        ],
        2 => [
            'machine_code' => 'KOMORI-L40',
            'machine_name' => 'KOMORI L 40',
            'brand_model' => 'Komori Lithrone 40',
            'max_paper_size' => '103x72',
        ],
    ];

    private const BEFORE = [
        1 => ['machine_code' => 'CETAK-01', 'machine_name' => 'Cetak 1', 'brand_model' => 'Heidelberg SM 74', 'max_paper_size' => '74x53'],
        2 => ['machine_code' => 'CETAK-02', 'machine_name' => 'Cetak 2', 'brand_model' => 'Komori L528', 'max_paper_size' => '72x52'],
    ];

    public function up(): void
    {
        foreach (self::FIX as $id => $data) {
            DB::connection('tm2mst')->table('prod_machine')
                ->where('machine_id', $id)
                ->update($data + ['updated_by' => 'claude_code']);
        }
    }

    public function down(): void
    {
        foreach (self::BEFORE as $id => $data) {
            DB::connection('tm2mst')->table('prod_machine')
                ->where('machine_id', $id)
                ->update($data + ['updated_by' => 'claude_code']);
        }
    }
};
