<?php

use App\Models\Master\ProdMachine;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoOperation;
use Illuminate\Database\Migrations\Migration;

// Interruption Dialog (production_scheduling_tier4.md §4) baru punya arti kalau
// ada task PENDING lain di resource yang sama utk "digeser" -- dummy sebelumnya
// (2026_08_28_000002) cuma punya WO2600250 sendirian PENDING di Cetak 1, jadi
// InterruptionOptionGenerator selalu jatuh ke opsi subkontrak saja (tak ada yang
// bisa didemokan/diuji utk opsi "geser job lain"). Tambah 1 WO PENDING lain yang
// SUDAH sempat dijadwal (target_start terisi dari backward-schedule sebelumnya,
// belum menang antrean/ASSIGNED) di mesin+proses sama -- state PENDING+target_start
// terisi valid sesuai Tier2 §2 (menunggu giliran, bukan berarti belum ada waktu).
return new class extends Migration
{
    public function up(): void
    {
        $cetak1 = ProdMachine::where('machine_code', 'CETAK-01')->value('machine_id');
        $anchor = \Carbon\Carbon::parse('2026-08-28');

        $wo = PrdWo::firstOrCreate(
            ['wo_no' => 'WO2600260'],
            [
                'wo_type' => 'MTO', 'source_type' => 'CO_CONFIRMED',
                'product_id' => \Illuminate\Support\Facades\DB::connection('tm2mst')->table('prod_product')->where('product_code', 'FB-DUPLEX-001')->value('id_product'),
                'qty' => 350, 'due_date' => $anchor->copy()->addDays(6)->setTime(17, 0),
                'wo_status' => 'SCHEDULED', 'schedule_layer' => 'COMMITTED', 'created_by' => 'system',
            ]
        );

        PrdWoOperation::firstOrCreate(
            ['wo_id' => $wo->wo_id, 'seq_no' => 1],
            [
                'process_name' => 'Cetak', 'resource_type' => 'MACHINE', 'machine_id' => $cetak1, 'qty' => 350,
                'target_start' => $anchor->copy()->setTime(17, 30), 'target_finish' => $anchor->copy()->setTime(19, 0),
                'task_status' => 'PENDING', 'critical_ratio' => 3.2000, 'created_by' => 'system',
            ]
        );
    }

    public function down(): void
    {
        $woId = PrdWo::where('wo_no', 'WO2600260')->value('wo_id');
        if ($woId) {
            PrdWoOperation::where('wo_id', $woId)->delete();
            PrdWo::whereKey($woId)->delete();
        }
    }
};
