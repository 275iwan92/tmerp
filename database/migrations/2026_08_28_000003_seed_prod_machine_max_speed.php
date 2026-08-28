<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// RateBandResolver (app/Services/Scheduling) fallback ke tm2mst.prod_machine.
// max_speed_per_hour saat histori DONE/QC_PASSED belum cukup datapoint --
// kolomnya sudah ada di skema tapi belum pernah diisi mesin dummy manapun.
// Angka di sini estimasi wajar per jenis mesin (bukan hasil ukur asli),
// konsisten "Keep dummy data" (feedback_keep_dummy_data.md) supaya
// PdrEngine::backwardSchedule() bisa diuji nyata di Scheduling Board.
return new class extends Migration
{
    private array $speeds = [
        'CETAK-01' => 8000,
        'CETAK-02' => 8000,
        'COAT-01' => 5000,
        'LAM-01' => 4000,
        'PLONG-01' => 6000,
    ];

    public function up(): void
    {
        $mst = DB::connection('tm2mst');

        foreach ($this->speeds as $machineCode => $speed) {
            $mst->table('prod_machine')
                ->where('machine_code', $machineCode)
                ->update(['max_speed_per_hour' => $speed]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2mst')->table('prod_machine')
            ->whereIn('machine_code', array_keys($this->speeds))
            ->update(['max_speed_per_hour' => null]);
    }
};
