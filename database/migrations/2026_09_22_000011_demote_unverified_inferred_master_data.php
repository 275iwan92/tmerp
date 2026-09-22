<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// KOREKSI ATAS KESALAHAN SAYA SENDIRI (perintah user 2026-09-22):
// "NEXT kalau mau memasukkan DATA, TANYA DULU!!! JANGAN ASAL TANPA KAMU
//  ANALISA. DATA LAMA TIDAK 100% BENAR. BANYAK USER BUAT INPUT AN NYA TIDAK
//  SESUAI."
//
// Yang saya lakukan salah: 68 pasangan prod_process_machine saya turunkan dari
// pemetaan machine_type -> nama proses KARANGAN SAYA SENDIRI, lalu saya tandai
// is_certified=1 ("sudah terbukti produksi"). Padahal tidak ada satu pun yang
// pernah dikonfirmasi user. 12 aturan prod_process_precedence juga asumsi
// domain saya, dan 5 di antaranya bersifat BLOCK -- artinya tebakan saya bisa
// MENOLAK routing yang sebenarnya sah.
//
// Migration ini TIDAK menghapus usulannya (masih berguna sebagai titik awal),
// tapi menurunkan statusnya supaya tidak ada yang memperlakukannya sebagai
// fakta sebelum user memverifikasi:
//
//   1. is_certified = 0 pada semua pasangan hasil seed. Layar sudah punya
//      badge "Tervalidasi / Belum divalidasi" + tombol toggle per baris, jadi
//      user bisa mengesahkan satu-satu sambil jalan.
//   2. Aturan precedence BLOCK -> WARN + alasannya diberi penanda
//      "[USULAN SISTEM]". Sesudah ini tebakan saya hanya memperingatkan,
//      tidak pernah memblokir pekerjaan orang.
//
// data_ready SENGAJA tidak dipakai untuk ini: artinya sudah dipakai untuk
// "memenuhi syarat kolom wajib", beda urusan dengan "sudah diverifikasi user".
return new class extends Migration
{
    private const SEEDED_BY = [
        'migration:process_machine_capability',
        'migration:close_capability_gaps',
        'migration:purge_legacy_machine',
        'migration:enterprise_extensions',
    ];

    public function up(): void
    {
        $mst = DB::connection('tm2mst');

        $mst->table('prod_process_machine')
            ->whereIn('created_by', self::SEEDED_BY)
            ->update([
                'is_certified' => false,
                'note' => DB::raw("CONCAT('[USULAN SISTEM - belum diverifikasi user] ', COALESCE(note, ''))"),
                'updated_by' => 'migration:demote_unverified',
            ]);

        $mst->table('prod_process_precedence')
            ->whereIn('created_by', self::SEEDED_BY)
            ->update([
                'severity' => 'WARN',
                'reason' => DB::raw("CONCAT('[USULAN SISTEM - belum diverifikasi user] ', COALESCE(reason, ''))"),
                'updated_by' => 'migration:demote_unverified',
            ]);
    }

    public function down(): void
    {
        // Tidak dinaikkan kembali otomatis: status "terverifikasi" hanya boleh
        // datang dari user, bukan dari rollback migration.
    }
};
