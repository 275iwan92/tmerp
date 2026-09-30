<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// User 2026-10-01: kompresor & forklift "tetap sebagai mesin tapi berikan FLAG untuk non production, just only supported;
// tetap jadi bagian MAINTENANCE module" (penjadwalan Preventive & Breakdown Maintenance). Flag = is_process_machine = 0
// (keluar dari penjadwalan PPIC & cek kapasitas; tetap di Master Mesin, proses & riwayat tidak dihapus).
return new class extends Migration
{
    public function up(): void
    {
        $note = '[2026-10-01] Alat penunjang (non-produksi): tidak dijadwalkan PPIC, tetap dikelola modul Maintenance.';
        foreach (DB::connection('tm2mst')->table('prod_machine')->whereIn('machine_code', ['CMP-01', 'FKL-01'])->get() as $m) {
            DB::connection('tm2mst')->table('prod_machine')->where('machine_id', $m->machine_id)->update([
                'is_process_machine' => false, 'speed_target_per_hour' => null,
                'notes' => str_contains((string) $m->notes, $note) ? $m->notes : trim($m->notes."\n".$note),
                'updated_by' => 'system', 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2mst')->table('prod_machine')->whereIn('machine_code', ['CMP-01', 'FKL-01'])->update(['is_process_machine' => true]);
    }
};
