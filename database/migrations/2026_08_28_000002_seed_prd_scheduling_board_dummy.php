<?php

use App\Models\Master\ProdMachine;
use App\Models\Prd\PrdCrewAssignment;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoOperation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Scheduling Board (mockup_scheduling_board.html / production_scheduling_tier4.md §3)
// -- iterasi pertama: Board + data nyata, drag-drop persist ke DB. TANPA engine
// backward-scheduling/PDR/Interruption Dialog (menyusul sesi berikutnya).
// Data dummy dijangkarkan ke tanggal hari ini (2026-08-28) supaya papan langsung
// terisi saat dibuka, konsisten "Keep dummy data" (feedback_keep_dummy_data.md).
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $anchor = \Carbon\Carbon::parse('2026-08-28');

        // ---- 1. Mesin Plong belum ada di tm2mst.prod_machine (cuma Cetak 1/2,
        // Coating, Laminasi) -- ditambah supaya alur Breakdown->Subkontrak (Tier1
        // §5A prinsip #4) bisa didemokan di board, konsisten mesin nyata lain.
        $offsetDivisionId = DB::connection('tm2mst')->table('org_division')->where('division_code', 'OFFSET')->value('division_id');

        $plongId = ProdMachine::firstOrCreate(
            ['machine_code' => 'PLONG-01', 'division_id' => $offsetDivisionId],
            [
                'machine_name' => 'Plong 1',
                'machine_type' => 'Potong',
                'activate' => true,
                'created_by' => 'system',
            ]
        )->machine_id;

        $cetak1 = ProdMachine::where('machine_code', 'CETAK-01')->value('machine_id');
        $laminasi = ProdMachine::where('machine_code', 'LAM-01')->value('machine_id');

        // ---- 2. Grant DIREKSI akses modul PROD (belum ada satupun grant, sama
        // temuan procurement_tier grant migration 2026-08-20).
        $prodModuleId = $sys->table('sec_module')->where('module_code', 'PROD')->value('id_module');
        $direksiId = $sys->table('sec_group')->where('group_code', 'DIREKSI')->value('id_group');

        if ($prodModuleId && $direksiId) {
            $sys->table('sec_group_permission')->updateOrInsert(
                ['group_id' => $direksiId, 'module_id' => $prodModuleId],
                ['can_access' => true, 'can_input' => true, 'can_approve' => true, 'can_export' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // ---- 3. Promosikan placeholder menu PROD_MPS (id lama, label "MPS (Master
        // Production Schedule)" TETAP -- DEC-NAMING, jangan direname) ke READY_FOR_QC.
        $sys->table('sys_app_menu')->where('code', 'PROD_MPS')->update([
            'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
        ]);

        // ---- 4. WO + Operation dummy, meniru dataset mockup_scheduling_board.html
        // (kode WO-0231/0245/0228/0230/0225/0227 dipertahankan sbg suffix wo_no
        // supaya papan visual konsisten dgn dokumen Tier4, meski format prefix
        // ikut konvensi project WOyy00000).
        $confirmedCoId = DB::connection('tm2sdi')->table('co_order')->where('doc_status', 'CONFIRMED')->value('id_order');
        $productA = DB::connection('tm2mst')->table('prod_product')->where('product_code', 'FB-DUPLEX-001')->value('id_product');
        $productB = DB::connection('tm2mst')->table('prod_product')->where('product_code', 'TAG-HNG-004')->value('id_product');

        $budi = DB::connection('tm2mst')->table('emp_employee')->where('full_name', 'Budi Santoso')->value('employee_id');
        $joko = DB::connection('tm2mst')->table('emp_employee')->where('full_name', 'JOKO')->value('employee_id');
        $rudi = DB::connection('tm2mst')->table('emp_employee')->where('full_name', 'RUDI')->value('employee_id');
        $siti = DB::connection('tm2mst')->table('emp_employee')->where('full_name', 'SITI FATIMAH')->value('employee_id');
        $rizta = DB::connection('tm2mst')->table('emp_employee')->where('full_name', 'RIZTA HARIF SHABRINA')->value('employee_id');

        $makeWo = function (string $suffix, array $attrs) use ($productA) {
            return PrdWo::create(array_merge([
                'wo_no' => 'WO26'.$suffix,
                'wo_type' => 'MTO',
                'source_type' => 'CO_CONFIRMED',
                'product_id' => $productA,
                'qty' => 1000,
                'due_date' => \Carbon\Carbon::parse('2026-09-05 17:00:00'),
                'created_by' => 'system',
            ], $attrs));
        };

        // WO-0231: Cetak, RUNNING, sedang berjalan (08:00-12:00, progress dihitung live)
        $wo231 = $makeWo('00231', ['source_co_id' => $confirmedCoId, 'wo_status' => 'IN_PROGRESS', 'schedule_layer' => 'COMMITTED', 'qty' => 800]);
        $op231 = PrdWoOperation::create([
            'wo_id' => $wo231->wo_id, 'seq_no' => 1, 'process_name' => 'Cetak', 'resource_type' => 'MACHINE',
            'machine_id' => $cetak1, 'qty' => 800,
            'target_start' => $anchor->copy()->setTime(8, 0), 'target_finish' => $anchor->copy()->setTime(12, 0),
            'task_status' => 'RUNNING', 'critical_ratio' => 2.4000, 'created_by' => 'system',
        ]);
        if ($budi) {
            PrdCrewAssignment::create(['operation_id' => $op231->operation_id, 'employee_id' => $budi, 'crew_role' => 'OPERATOR_UTAMA', 'created_by' => 'system']);
        }
        if ($joko) {
            PrdCrewAssignment::create(['operation_id' => $op231->operation_id, 'employee_id' => $joko, 'crew_role' => 'CO_OPERATOR', 'created_by' => 'system']);
        }

        // WO-0245: Hot Job, disisip Direksi, ASSIGNED (14:00-17:00) di mesin sama
        $wo245 = $makeWo('00245', [
            'source_co_id' => $confirmedCoId, 'wo_status' => 'SCHEDULED', 'schedule_layer' => 'COMMITTED',
            'qty' => 500, 'is_hot_job' => true, 'hot_job_source' => 'DIREKSI',
        ]);
        PrdWoOperation::create([
            'wo_id' => $wo245->wo_id, 'seq_no' => 1, 'process_name' => 'Cetak', 'resource_type' => 'MACHINE',
            'machine_id' => $cetak1, 'qty' => 500,
            'target_start' => $anchor->copy()->setTime(14, 0), 'target_finish' => $anchor->copy()->setTime(17, 0),
            'task_status' => 'ASSIGNED', 'is_hot_job' => true, 'critical_ratio' => 1.6000, 'created_by' => 'system',
        ]);

        // WO-0228: Laminasi, RUNNING (11:00-14:00)
        $wo228 = $makeWo('00228', ['source_co_id' => $confirmedCoId, 'wo_status' => 'IN_PROGRESS', 'schedule_layer' => 'COMMITTED', 'product_id' => $productB, 'qty' => 1200]);
        $op228 = PrdWoOperation::create([
            'wo_id' => $wo228->wo_id, 'seq_no' => 1, 'process_name' => 'Laminasi', 'resource_type' => 'MACHINE',
            'machine_id' => $laminasi, 'qty' => 1200,
            'target_start' => $anchor->copy()->setTime(11, 0), 'target_finish' => $anchor->copy()->setTime(14, 0),
            'task_status' => 'RUNNING', 'critical_ratio' => 1.9000, 'created_by' => 'system',
        ]);
        if ($rudi) {
            PrdCrewAssignment::create(['operation_id' => $op228->operation_id, 'employee_id' => $rudi, 'crew_role' => 'OPERATOR_UTAMA', 'created_by' => 'system']);
        }

        // WO-0230: Plong -- 2 operation (selesai lalu breakdown), dgn breakdown_event
        $wo230 = $makeWo('00230', ['source_co_id' => $confirmedCoId, 'wo_status' => 'ON_HOLD', 'schedule_layer' => 'COMMITTED', 'qty' => 600]);
        PrdWoOperation::create([
            'wo_id' => $wo230->wo_id, 'seq_no' => 1, 'process_name' => 'Plong', 'resource_type' => 'MACHINE',
            'machine_id' => $plongId, 'qty' => 300,
            'target_start' => $anchor->copy()->setTime(8, 0), 'target_finish' => $anchor->copy()->setTime(10, 0),
            'task_status' => 'QC_PASSED', 'critical_ratio' => 2.0000, 'created_by' => 'system',
        ]);
        $op230b = PrdWoOperation::create([
            'wo_id' => $wo230->wo_id, 'seq_no' => 2, 'process_name' => 'Plong', 'resource_type' => 'MACHINE',
            'machine_id' => $plongId, 'qty' => 300,
            'target_start' => $anchor->copy()->setTime(10, 0), 'target_finish' => $anchor->copy()->setTime(13, 0),
            'task_status' => 'BREAKDOWN', 'critical_ratio' => 0.6000, 'created_by' => 'system',
        ]);
        DB::connection('tm2prd')->table('prd_breakdown_event')->insert([
            'operation_id' => $op230b->operation_id, 'machine_id' => $plongId,
            'reported_by' => 'system', 'reported_at' => $anchor->copy()->setTime(10, 30),
            'downtime_estimate_minutes' => 180, 'created_at' => now(),
        ]);

        // WO-0225: Manual job (Packing), RUNNING (10:00-13:00)
        $wo225 = $makeWo('00225', ['source_co_id' => $confirmedCoId, 'wo_status' => 'IN_PROGRESS', 'schedule_layer' => 'COMMITTED', 'product_id' => $productB, 'qty' => 900]);
        $op225 = PrdWoOperation::create([
            'wo_id' => $wo225->wo_id, 'seq_no' => 1, 'process_name' => 'Packing', 'resource_type' => 'MANUAL',
            'qty' => 900,
            'target_start' => $anchor->copy()->setTime(10, 0), 'target_finish' => $anchor->copy()->setTime(13, 0),
            'task_status' => 'RUNNING', 'critical_ratio' => 1.8000, 'created_by' => 'system',
        ]);
        if ($siti) {
            PrdCrewAssignment::create(['operation_id' => $op225->operation_id, 'employee_id' => $siti, 'crew_role' => 'OPERATOR_UTAMA', 'created_by' => 'system']);
        }

        // WO-0227: Manual job (Packing), QC_HOLD, berisiko telat (12:00-16:00)
        $wo227 = $makeWo('00227', ['source_co_id' => $confirmedCoId, 'wo_status' => 'IN_PROGRESS', 'schedule_layer' => 'COMMITTED', 'product_id' => $productB, 'qty' => 700]);
        $op227 = PrdWoOperation::create([
            'wo_id' => $wo227->wo_id, 'seq_no' => 1, 'process_name' => 'Packing', 'resource_type' => 'MANUAL',
            'qty' => 700, 'qc_gate_required' => true,
            'target_start' => $anchor->copy()->setTime(12, 0), 'target_finish' => $anchor->copy()->setTime(16, 0),
            'task_status' => 'QC_HOLD', 'critical_ratio' => 0.8000, 'created_by' => 'system',
        ]);
        if ($rizta) {
            PrdCrewAssignment::create(['operation_id' => $op227->operation_id, 'employee_id' => $rizta, 'crew_role' => 'OPERATOR_UTAMA', 'created_by' => 'system']);
        }

        // WO-0250: contoh belum terjadwal sama sekali (target_start NULL) --
        // mengisi stat "Belum Terjadwal" (F17) dgn data nyata, bukan angka statis.
        $wo250 = $makeWo('00250', ['wo_status' => 'SIMULATED', 'schedule_layer' => 'SIMULATED', 'qty' => 400]);
        PrdWoOperation::create([
            'wo_id' => $wo250->wo_id, 'seq_no' => 1, 'process_name' => 'Cetak', 'resource_type' => 'MACHINE',
            'machine_id' => $cetak1, 'qty' => 400, 'task_status' => 'PENDING', 'created_by' => 'system',
        ]);
    }

    public function down(): void
    {
        $woIds = \App\Models\Prd\PrdWo::where('wo_no', 'like', 'WO26002%')
            ->orWhere('wo_no', 'like', 'WO26002%')
            ->pluck('wo_id');

        $opIds = DB::connection('tm2prd')->table('prd_wo_operation')->whereIn('wo_id', $woIds)->pluck('operation_id');
        DB::connection('tm2prd')->table('prd_breakdown_event')->whereIn('operation_id', $opIds)->delete();
        DB::connection('tm2prd')->table('prd_crew_assignment')->whereIn('operation_id', $opIds)->delete();
        DB::connection('tm2prd')->table('prd_wo_operation')->whereIn('wo_id', $woIds)->delete();
        DB::connection('tm2prd')->table('prd_wo')->whereIn('wo_id', $woIds)->delete();

        DB::connection('tm2mst')->table('prod_machine')->where('machine_code', 'PLONG-01')->delete();

        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PROD_MPS')->update([
            'status_code' => 'NOT_BUILT', 'status_updated_by' => null, 'status_updated_at' => null,
        ]);
    }
};
