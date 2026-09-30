<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// User 2026-10-01: DIG-05 "Taimes Heater" (pengering tambahan printer Taimes, bukan printer) "tetap dicatat sebagai
// mesin non produksi just for supported" — pola sama dengan kompresor/forklift (migration 000013): is_process_machine = 0,
// keluar dari penjadwalan PPIC & engine target kecepatan, tetap di Master Mesin untuk Maintenance.
return new class extends Migration
{
    public function up(): void
    {
        $note = '[2026-10-01] Support equipment (non-production): dryer for the Taimes printer; not scheduled by PPIC, kept for Maintenance.';
        foreach (DB::connection('tm2mst')->table('prod_machine')->where('machine_code', 'DIG-05')->get() as $m) {
            DB::connection('tm2mst')->table('prod_machine')->where('machine_id', $m->machine_id)->update([
                'is_process_machine' => false, 'speed_target_per_hour' => null,
                'notes' => str_contains((string) $m->notes, $note) ? $m->notes : trim($m->notes."\n".$note),
                'updated_by' => 'system', 'updated_at' => now(),
            ]);
            DB::connection('tm2mst')->table('prod_speed_suggestion')->where('machine_id', $m->machine_id)->where('status', 'PENDING')
                ->update(['status' => 'SUPERSEDED', 'updated_by' => 'system', 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2mst')->table('prod_machine')->where('machine_code', 'DIG-05')->update(['is_process_machine' => true]);
    }
};
