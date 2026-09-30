<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Waktu setting nyata per mesin (lanjutan PPIC baseline 2026_10_01_000011): median durasi proses "SETTING ..." di
// tm2old.prd_output 2021-2024 (3-480 menit), HANYA mesin dengan >= 20 catatan. Diisi ke kapabilitas proses x mesin
// (prod_process_machine.setup_minutes) yang masih kosong — dipakai MachineCapabilityService::estimateHours().
// Waste % sengaja TIDAK diisi: scrap di sistem lama tercatat ±0,07% (tidak lengkap), menyesatkan bila dipakai.
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2mst');
        $setup = ['DIE-01' => [60, 'PLONG 1', 1236], 'DIE-02' => [60, 'PLONG 2', 1029], 'DIE-03' => [60, 'PLONG 3', 558],
            'OFF-01' => [90, 'SAKURA', 21], 'FGL-03' => [45, 'FOLDER GLUER', 34], 'FGL-04' => [30, 'FOLDER GLUER 2', 73], 'LAM-04' => [15, 'LAM_YOUBOND', 1044]];
        foreach ($setup as $code => [$min, $legacy, $n]) {
            $id = $db->table('prod_machine')->where('machine_code', $code)->value('machine_id');
            if (! $id) {
                continue;
            }
            $note = "[PPIC 2026-10-01] setup {$min} mnt = median {$n} catatan SETTING mesin lama \"{$legacy}\" (tm2old.prd_output 2021-2024)";
            $db->table('prod_process_machine')->where('machine_id', $id)->where('activate', true)->whereNull('setup_minutes')
                ->update(['setup_minutes' => $min, 'note' => DB::raw("TRIM(CONCAT(COALESCE(note,''), ' ', ".$db->getPdo()->quote($note).'))'),
                    'updated_by' => 'system', 'updated_at' => now()]);
        }
    }

    public function down(): void {}
};
