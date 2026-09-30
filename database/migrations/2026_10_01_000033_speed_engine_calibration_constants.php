<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// User 2026-10-01: "target produksi bisa di koreksi dengan data di data legacy lama agar didapat estimasi target yang
// mendekati realita" -> faktor efisiensi spek mesin dikalibrasi dari riwayat nyata (ModelSpecSource); dua saklar ini
// supaya user bisa mengatur/mematikannya di Settings > Constants.
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['SPEED_EFF_CALIBRATE', 1, 'Speed engine: 1 = efficiency for maker-spec estimates is calibrated from real history (median actual/max of machines with the same drive); 0 = use SPEED_EFF_MANUAL/AUTO'],
            ['SPEED_EFF_CALIBRATE_MIN', 2, 'Speed engine: minimum machines with history (same drive) before the calibrated efficiency replaces SPEED_EFF_MANUAL/AUTO'],
        ];
        foreach ($rows as [$key, $num, $desc]) {
            if (! DB::connection('tm2sys')->table('sys_constant')->where('constant_key', $key)->exists()) {
                DB::connection('tm2sys')->table('sys_constant')->insert([
                    'constant_key' => $key, 'category_code' => 'PRODUCTION', 'constant_group' => 'SPEED_ENGINE',
                    'constant_number' => $num, 'description' => $desc, 'activate' => true,
                    'created_at' => now(), 'updated_at' => now(), 'created_by' => 'system',
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->whereIn('constant_key', ['SPEED_EFF_CALIBRATE', 'SPEED_EFF_CALIBRATE_MIN'])->delete();
    }
};
