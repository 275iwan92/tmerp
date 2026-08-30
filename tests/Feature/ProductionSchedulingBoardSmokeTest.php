<?php

namespace Tests\Feature;

use App\Models\Master\EmpEmployee;
use App\Models\Prd\PrdCrewAssignment;
use App\Models\Prd\PrdDecisionFeedbackLog;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoOperation;
use App\Models\Sys\SecUser;
use Livewire\Livewire;
use Tests\TestCase;

// Scheduling Board (mockup_scheduling_board.html / production_scheduling_tier4.md §3)
// -- verifikasi data dummy 2026_08_28_000002_seed_prd_scheduling_board_dummy.php
// render dgn benar & mutasi drag/resize/reassign persist ke tm2prd sungguhan.
class ProductionSchedulingBoardSmokeTest extends TestCase
{
    private array $restoreOps = [];

    protected function tearDown(): void
    {
        foreach ($this->restoreOps as $operationId => $attrs) {
            PrdWoOperation::whereKey($operationId)->update($attrs);
        }

        parent::tearDown();
    }

    private function snapshot(PrdWoOperation $op): void
    {
        $this->restoreOps[$op->operation_id] ??= [
            'target_start' => $op->target_start,
            'target_finish' => $op->target_finish,
            'resource_type' => $op->resource_type,
            'machine_id' => $op->machine_id,
            'process_name' => $op->process_name,
        ];
    }

    public function test_board_renders_seeded_operations(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $html = Livewire::test('pages::production.scheduling-board')
            ->set('date', '2026-08-28')
            ->html();

        $this->assertStringContainsString('WO2600231', $html);
        $this->assertStringContainsString('Plong 1', $html);
        $this->assertStringContainsString('Budi Santoso', $html);
        $this->assertStringContainsString('SITI FATIMAH', $html);
    }

    public function test_move_operation_persists_new_start_and_keeps_duration(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $op = PrdWoOperation::whereHas('wo', fn ($q) => $q->where('wo_no', 'WO2600231'))->firstOrFail();
        $this->snapshot($op);

        Livewire::test('pages::production.scheduling-board')
            ->set('date', '2026-08-28')
            ->call('moveOperation', $op->operation_id, 9.5);

        $op->refresh();
        $this->assertSame('2026-08-28 09:30:00', $op->target_start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-28 13:30:00', $op->target_finish->format('Y-m-d H:i:s'));
    }

    public function test_resize_operation_updates_finish_only(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $op = PrdWoOperation::whereHas('wo', fn ($q) => $q->where('wo_no', 'WO2600228'))->firstOrFail();
        $this->snapshot($op);
        $originalStart = $op->target_start->copy();

        Livewire::test('pages::production.scheduling-board')
            ->set('date', '2026-08-28')
            ->call('resizeOperation', $op->operation_id, 2.5);

        $op->refresh();
        $this->assertTrue($op->target_start->equalTo($originalStart));
        $this->assertEqualsWithDelta(150, $op->target_start->diffInMinutes($op->target_finish), 0.01);
    }

    public function test_reassign_operation_switches_machine_to_manual_and_assigns_crew(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $op = PrdWoOperation::whereHas('wo', fn ($q) => $q->where('wo_no', 'WO2600245'))->firstOrFail();
        $this->snapshot($op);

        $employeeId = EmpEmployee::where('full_name', 'RIZTA HARIF SHABRINA')->value('employee_id');

        Livewire::test('pages::production.scheduling-board')
            ->set('date', '2026-08-28')
            ->call('reassignOperation', $op->operation_id, 'e'.$employeeId, 'Packing');

        $op->refresh();
        $this->assertSame('MANUAL', $op->resource_type);
        $this->assertNull($op->machine_id);
        $this->assertSame('Packing', $op->process_name);

        $this->assertTrue(
            PrdCrewAssignment::where('operation_id', $op->operation_id)
                ->where('crew_role', 'OPERATOR_UTAMA')
                ->where('employee_id', $employeeId)
                ->exists()
        );

        PrdCrewAssignment::where('operation_id', $op->operation_id)->where('employee_id', $employeeId)->delete();
    }

    public function test_qc_hold_operation_cannot_be_dragged(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $op = PrdWoOperation::whereHas('wo', fn ($q) => $q->where('wo_no', 'WO2600227'))->firstOrFail();
        $this->assertSame('QC_HOLD', $op->task_status);
        $originalStart = $op->target_start->copy();

        Livewire::test('pages::production.scheduling-board')
            ->set('date', '2026-08-28')
            ->call('moveOperation', $op->operation_id, 9.0);

        $op->refresh();
        $this->assertTrue($op->target_start->equalTo($originalStart));
    }

    public function test_unscheduled_stat_counts_wo2600250(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $unscheduledWoExists = PrdWo::where('wo_no', 'WO2600250')->exists();
        $this->assertTrue($unscheduledWoExists);

        $html = Livewire::test('pages::production.scheduling-board')
            ->set('date', '2026-08-28')
            ->html();

        // Stat card wajib menampilkan angka > 0 (bukan 0 statis) selama WO-0250 (target_start NULL) ada.
        $this->assertMatchesRegularExpression('/Belum Terjadwal.*?font-extrabold[^>]*>([1-9]\d*)</s', $html);
    }

    // Interruption Dialog (Tier4 §4) -- alur penuh: pilih WO belum terjadwal (WO2600250),
    // generate opsi lewat PdrEngine/InterruptionOptionGenerator sungguhan, terapkan opsi
    // "geser" (bukan subkontrak), verifikasi commit ke DB nyata (bukan mock).
    public function test_hot_job_dialog_generates_and_applies_displace_option(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $hotWo = PrdWo::where('wo_no', 'WO2600250')->firstOrFail();
        $hotOp = PrdWoOperation::where('wo_id', $hotWo->wo_id)->firstOrFail();
        $displacedOp = PrdWoOperation::whereHas('wo', fn ($q) => $q->where('wo_no', 'WO2600260'))->firstOrFail();

        $this->assertFalse($hotWo->is_hot_job);
        $this->assertSame('PENDING', $hotOp->task_status);
        $originalDisplacedStart = $displacedOp->target_start->copy();
        $originalDisplacedFinish = $displacedOp->target_finish->copy();

        // try/finally -- data dummy ini dipakai ulang oleh test lain & demo manual
        // di browser, jadi restore WAJIB jalan walau assertion di bawah gagal
        // partway (assertion gagal = exception = restore lama ke-skip, WO/operation
        // ini tertinggal setengah ter-hot-job selamanya sampai di-reset manual).
        try {
            $component = Livewire::test('pages::production.scheduling-board')
                ->set('hotWoId', $hotWo->wo_id)
                ->call('generateHotJobOptions');

            $options = $component->get('hotOptions');
            $this->assertNotEmpty($options);

            $displaceIndex = collect($options)->search(fn ($o) => ($o['displaced_wo_no'] ?? null) === 'WO2600260');
            $this->assertNotFalse($displaceIndex, 'Opsi "geser WO2600260" tidak ditemukan -- '.json_encode($options));

            $component
                ->set('hotSource', 'DIREKSI')
                ->set('hotSelectedOption', $displaceIndex)
                ->call('applyHotJobOption');

            $hotWo->refresh();
            $hotOp->refresh();
            $displacedOp->refresh();

            $this->assertTrue($hotWo->is_hot_job);
            $this->assertSame('DIREKSI', $hotWo->hot_job_source);
            $this->assertTrue($hotOp->is_hot_job);
            $this->assertSame('ASSIGNED', $hotOp->task_status);
            $this->assertTrue($hotOp->target_start->equalTo($originalDisplacedStart));
            $this->assertTrue($hotOp->target_finish->equalTo($originalDisplacedFinish));
            $this->assertTrue($displacedOp->target_start->gt($originalDisplacedStart));
            $this->assertTrue($displacedOp->target_finish->gt($originalDisplacedFinish));

            $this->assertTrue(
                PrdDecisionFeedbackLog::where('wo_id', $hotWo->wo_id)->where('event_type', 'INTERRUPTION')->exists()
            );
        } finally {
            PrdDecisionFeedbackLog::where('wo_id', $hotWo->wo_id)->delete();
            $hotWo->update(['is_hot_job' => false, 'hot_job_source' => null, 'hot_job_customer_id' => null]);
            $hotOp->update(['is_hot_job' => false, 'task_status' => 'PENDING', 'target_start' => null, 'target_finish' => null]);
            $displacedOp->update(['target_start' => $originalDisplacedStart, 'target_finish' => $originalDisplacedFinish]);
        }
    }
}
