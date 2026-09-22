<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Temuan audit matriks kapabilitas (2026-09-22): 6 mesin aktif tidak punya
// SATU pun proses yang bisa dijalankan di sana. Setelah dicek satu per satu
// (bukan asumsi -- dicari juga ejaan lain WAX/LILIN/CTP/PLATE/SEAM di
// prod_process, semua nihil), penyebabnya 2 macam:
//
//   a) 3 PROSES MASTER-nya memang belum pernah dibuat, padahal mesinnya ada
//      dan dipakai: WAX-01 (waxing), CTP-01 (pembuatan plate), SEA-01/02
//      (seaming). Ditambahkan di sini + langsung dipetakan ke mesinnya.
//      Kode & parameter ongkos DIBIARKAN NULL: belum ada angka resmi dari
//      lapangan, dan mengisi tebakan lebih berbahaya daripada kosong karena
//      CostingEngine akan memakainya sebagai HPP.
//
//   b) CMP-01 (kompresor) & FKL-01 (forklift) memang bukan mesin proses --
//      jenisnya "Support Equipment". Ditandai is_process_machine=0 supaya
//      tidak ikut muncul di picker mesin routing dan tidak terus-terusan
//      terhitung sebagai "mesin belum dipetakan" di audit berikutnya.
//
// is_manual pada prod_process memisahkan "proses tangan, memang tanpa mesin"
// (QC, HITUNG, JEMUR) dari "belum dipetakan" -- tanpa flag ini keduanya
// terlihat identik di layar dan audit tidak bisa dipercaya.
return new class extends Migration
{
    // [nama, stage, uom, manual?, jenis mesin yang mengerjakan]
    private const NEW_PROCESSES = [
        ['PLATE MAKING', 'PREPRESS', 'PCS', false, ['CTP Plate Setter']],
        ['WAXING', 'POSTPRESS', 'LEMBAR', false, ['Waxing']],
        ['SEAMING', 'FINISHING', 'PCS', false, ['Seaming']],
    ];

    private const MANUAL_PROCESSES = ['QC PACK', 'QC', 'QC HITUNG', 'HITUNG', 'JEMUR'];

    public function up(): void
    {
        Schema::connection('tm2mst')->table('prod_process', function (Blueprint $t) {
            $t->boolean('is_manual')->default(false)->after('allow_parallel')
                ->comment('1 = proses tangan, tidak butuh mesin -- bukan "belum dipetakan"');
        });

        Schema::connection('tm2mst')->table('prod_machine', function (Blueprint $t) {
            $t->boolean('is_process_machine')->default(true)->after('machine_status')
                ->comment('0 = alat penunjang (kompresor/forklift), tidak muncul di picker routing');
        });

        $db = DB::connection('tm2mst');
        $now = now();

        $db->table('prod_process')->whereIn('process_name', self::MANUAL_PROCESSES)
            ->update(['is_manual' => true, 'updated_by' => 'migration:close_capability_gaps']);

        $db->table('prod_machine')->where('machine_type', 'Support Equipment')
            ->update(['is_process_machine' => false, 'updated_by' => 'migration:close_capability_gaps']);

        foreach (self::NEW_PROCESSES as [$name, $stage, $uom, $manual, $types]) {
            if ($db->table('prod_process')->where('process_name', $name)->exists()) {
                continue;
            }

            $processId = $db->table('prod_process')->insertGetId([
                'process_name' => $name,
                'process_stage' => $stage,
                'output_uom' => $uom,
                'is_manual' => $manual,
                'inhouse' => true,
                'activate' => true,
                'requires_plate' => $name === 'PLATE MAKING',
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => 'migration:close_capability_gaps',
                'updated_by' => 'migration:close_capability_gaps',
                'data_origin' => 'LIVE',
                'data_ready' => true,
            ]);

            $machines = $db->table('prod_machine')->where('activate', 1)
                ->whereIn('machine_type', $types)->orderBy('machine_code')->get();

            foreach ($machines as $m) {
                $db->table('prod_process_machine')->insert([
                    'process_id' => $processId,
                    'machine_id' => $m->machine_id,
                    // Satu-satunya mesin untuk proses ini -> otomatis mesin utama.
                    'preference_rank' => $machines->count() === 1 ? 1 : 2,
                    'is_certified' => true,
                    'speed_per_hour' => $m->max_speed_per_hour ?: null,
                    'activate' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'created_by' => 'migration:close_capability_gaps',
                    'updated_by' => 'migration:close_capability_gaps',
                    'data_origin' => 'LIVE',
                    'data_ready' => true,
                ]);
            }
        }
    }

    public function down(): void
    {
        $db = DB::connection('tm2mst');
        $ids = $db->table('prod_process')
            ->whereIn('process_name', array_column(self::NEW_PROCESSES, 0))
            ->where('created_by', 'migration:close_capability_gaps')
            ->pluck('id_process');

        $db->table('prod_process_machine')->whereIn('process_id', $ids)->delete();
        $db->table('prod_process')->whereIn('id_process', $ids)->delete();

        Schema::connection('tm2mst')->table('prod_process', fn (Blueprint $t) => $t->dropColumn('is_manual'));
        Schema::connection('tm2mst')->table('prod_machine', fn (Blueprint $t) => $t->dropColumn('is_process_machine'));
    }
};
