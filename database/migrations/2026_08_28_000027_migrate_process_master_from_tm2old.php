<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Permintaan user 2026-08-28: migrasi Master Proses dari tm2old.m_process (data
// riil, 20 baris) ke tm2mst.prod_process (LOCKED schema, sudah ada tapi baru
// isi 3 dummy seed placeholder Cetak/Laminasi/Plong).
//
// TIDAK bisa hapus-lalu-insert-ulang: id_process=1 (Cetak) sudah dipakai FK
// oleh prod_part_process_machine_rate (dummy Target Rate F15) -- dummy data,
// jangan dihapus (lihat memory feedback_keep_dummy_data). Jadi dipakai upsert
// by-name (case-insensitive): 3 dummy existing match nama ke data lama
// (CETAK/LAMINASI/PLONG) -> di-update di tempat (id_process tetap sama, FK
// aman), 17 sisanya di-insert baru. process_id lama TIDAK dipertahankan
// sebagai id_process (akan bentrok dgn id dummy 1/2/3 yang beda proses).
// Catatan: id 8 & 19 sama-sama "KETREK" (duplikat nama persis, tak ada data
// pembeda) -- otomatis nyatu jadi 1 baris lewat upsert ini, bukan didrop diam2.
return new class extends Migration
{
    public function up(): void
    {
        $old = DB::connection('tm2old')->table('m_process')->orderBy('process_id')->get();
        $mst = DB::connection('tm2mst');

        foreach ($old as $row) {
            $existing = $mst->table('prod_process')
                ->whereRaw('LOWER(process_name) = ?', [strtolower($row->process_name)])
                ->first();

            $payload = [
                'process_name' => $row->process_name,
                'gantt_color' => $row->gantt_color,
                'activate' => true,
                'updated_by' => 'migration:tm2old.m_process',
            ];

            if ($existing) {
                $mst->table('prod_process')->where('id_process', $existing->id_process)->update($payload);
            } else {
                $mst->table('prod_process')->insert($payload + [
                    'created_at' => $row->create_log ?? now(),
                    'updated_at' => $row->update_log ?? now(),
                    'created_by' => 'migration:tm2old.m_process',
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::connection('tm2mst')->table('prod_process')->where('created_by', 'migration:tm2old.m_process')->delete();
    }
};
