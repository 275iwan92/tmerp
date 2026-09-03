<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// gap/gripper/margin dipakai HARDCODE (3.0/12.0/8.0) di 4 tempat berbeda di
// prodev.blade.php (runEngineTingkat1/2/BestSheet + pickCandidate) -- angka
// fisik mesin cetak yg sama, tersebar sbg magic number. Redesign form ProDev
// (mockup_FORM_prodev_2.png, panel "Machine Specifications") butuh nampilin
// angka ini sbg info NYATA (bukan dikarang) -- pola sama persis migration
// 2026_09_02_000020 (PRODEV_FLAP_MIN_MM dst): pindah ke sys_constant supaya
// (a) 1 sumber kebenaran, bukan disalin 4x, (b) admin bisa adjust nanti dari
// layar Configuration tanpa deploy kode.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $nextId = (int) $sys->table('sys_constant')->max('id_constant') + 1;

        $rows = [
            [
                'constant_key' => 'PRODEV_GAP_MM',
                'constant_number' => 3.0,
                'description' => 'Jarak antar-unit dalam 1 plano (mm) -- dipakai ImpositionEngine::run()/sweepFlap() dst sbg parameter gap. Sebelumnya hardcode 3.0 di 4 tempat prodev.blade.php.',
            ],
            [
                'constant_key' => 'PRODEV_GRIPPER_MM',
                'constant_number' => 12.0,
                'description' => 'Gripper margin mesin cetak sheetfed (mm, area cengkeram tepi plano yg tidak bisa dicetak). Sebelumnya hardcode 12.0.',
            ],
            [
                'constant_key' => 'PRODEV_MARGIN_MM',
                'constant_number' => 8.0,
                'description' => 'Margin tepi plano di luar gripper (mm). Sebelumnya hardcode 8.0.',
            ],
        ];

        foreach ($rows as $i => $row) {
            if ($sys->table('sys_constant')->where('constant_key', $row['constant_key'])->exists()) {
                continue;
            }

            $sys->table('sys_constant')->insert([
                'id_constant' => $nextId + $i,
                'constant_key' => $row['constant_key'],
                'category_code' => 'PRODUCTION',
                'constant_group' => 'PRODEV',
                'constant_number' => $row['constant_number'],
                'constant_text' => null,
                'description' => $row['description'],
                'activate' => true,
                'created_by' => 'claude_code',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->whereIn('constant_key', [
            'PRODEV_GAP_MM', 'PRODEV_GRIPPER_MM', 'PRODEV_MARGIN_MM',
        ])->delete();
    }
};
