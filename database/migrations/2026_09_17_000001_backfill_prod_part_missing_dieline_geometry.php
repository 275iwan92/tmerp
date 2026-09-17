<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Lengkapi geometri dieline prod_part yang masih kosong (instruksi user
// 2026-09-17). ProDev tidak bisa menghitung nesting tanpa ukuran blank:
// sumbernya berlapis die_w/h_cm -> printsize_p/l -> flat_w/h dari Inquiry,
// dan part yang ketiganya kosong tidak bisa dikerjakan sama sekali.
//
// Saat migration ini ditulis hanya ADA SATU baris yang kosong: id_part 2
// "Body Box (Folding Box Duplex Kosmetik)" (created_by 'system', seed master
// produk 2026-08-21) -- 33 part lain sudah terisi.
//
// Rumusnya BUKAN karangan baru: diambil dari baris seed yang sudah ada dan
// konsisten di semua part (dicek ke id 61/62/63/64):
//     die_w_cm       = cm_width  + 2      sheet_raw_w_cm = cm_width  + 4
//     die_h_cm       = cm_length + 2      sheet_raw_h_cm = cm_length + 4
// Artinya "blank = ukuran box + toleransi 2 cm per sisi".
//
// CATATAN JUJUR: itu angka pendekatan bawaan data seed, BUKAN bentangan
// karton sungguhan (bentangan folding box = 2L + 2W + lidah lem, butuh tinggi
// kotak). Untuk part produksi sungguhan, geometri semestinya datang dari
// dieline_spec/Inquiry, bukan rumus ini. cm_height part 2 juga masih kosong
// dan TIDAK diisi di sini -- tidak ada sumbernya, jangan dikarang.
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::connection('tm2mst')->table('prod_part')
            ->whereNull('die_w_cm')->whereNull('die_h_cm')
            ->whereNull('printsize_p')->whereNull('printsize_l')
            ->whereNotNull('cm_length')->whereNotNull('cm_width')
            ->get(['id_part', 'cm_length', 'cm_width']);

        foreach ($rows as $r) {
            DB::connection('tm2mst')->table('prod_part')->where('id_part', $r->id_part)->update([
                'die_w_cm' => round((float) $r->cm_width + 2, 2),
                'die_h_cm' => round((float) $r->cm_length + 2, 2),
                'sheet_raw_w_cm' => round((float) $r->cm_width + 4, 2),
                'sheet_raw_h_cm' => round((float) $r->cm_length + 4, 2),
                'updated_by' => 'claude_code',
            ]);
        }
    }

    // SENGAJA tidak menghapus apa pun. Rumus di atas juga cocok dengan 33 baris
    // seed yang sudah terisi sejak sebelumnya, jadi rollback yang "mengembalikan
    // ke kosong" berdasarkan kecocokan rumus akan ikut mengosongkan data yang
    // BUKAN buatan migration ini -- risiko kehilangan data master lebih besar
    // daripada manfaat rollback-nya. Kalau angka part 2 perlu dibatalkan,
    // kosongkan baris itu saja secara eksplisit.
    public function down(): void
    {
        // no-op (lihat alasan di atas)
    }
};
