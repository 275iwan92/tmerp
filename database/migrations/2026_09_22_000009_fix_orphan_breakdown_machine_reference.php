<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Perbaikan atas cacat di migration 000008 (purge mesin legacy).
//
// APA YANG SALAH: saat memindahkan referensi, update
// prd_breakdown_event.machine_id -> NULL GAGAL karena kolomnya NOT NULL, dan
// blok catch di migration itu MENELAN error-nya. Akibatnya event_id=1
// tertinggal menunjuk machine_id=5 (ZZOLD-5 "Plong 1") yang sudah dihapus --
// referensi yatim yang tidak ketahuan kalau tidak diaudit ulang ke DB.
// Pelajarannya: jangan menelan exception pada operasi integritas data.
//
// KENAPA KOLOMNYA DIBUAT NULLABLE, bukan barisnya dihapus:
// prd_wo_operation.machine_id (tabel kakaknya, sumber operasi yang rusak)
// SUDAH nullable. prd_breakdown_event yang NOT NULL adalah ketidakkonsistenan
// skema, bukan aturan bisnis -- laporan kerusakan mesin tetap sah dicatat
// walau unit fisiknya belum dipastikan, persis seperti operasi WO-nya.
// Menghapus barisnya berarti membuang histori kerusakan produksi, dan itu
// tidak diminta user.
//
// ALTER lewat SQL mentah: doctrine/dbal tidak dipasang di proyek ini, jadi
// $table->...->change() tidak tersedia.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2prd')->statement(
            'ALTER TABLE prd_breakdown_event
             MODIFY machine_id BIGINT UNSIGNED NULL
             COMMENT "NULL = unit fisik belum dipastikan (mis. warisan data legacy yang tercampur kru)"'
        );

        $validIds = DB::connection('tm2mst')->table('prod_machine')->pluck('machine_id')->all();

        DB::connection('tm2prd')->table('prd_breakdown_event')
            ->whereNotNull('machine_id')
            ->whereNotIn('machine_id', $validIds)
            ->update(['machine_id' => null]);

        // alt_machine_id sudah nullable, tapi ikut diperiksa -- kolom ini
        // terlewat sama sekali di migration 000008.
        DB::connection('tm2prd')->table('prd_breakdown_event')
            ->whereNotNull('alt_machine_id')
            ->whereNotIn('alt_machine_id', $validIds)
            ->update(['alt_machine_id' => null]);
    }

    public function down(): void
    {
        // Tidak dikembalikan ke NOT NULL: baris yang machine_id-nya sudah NULL
        // akan membuat ALTER gagal, dan NOT NULL itu sendiri yang keliru.
    }
};
