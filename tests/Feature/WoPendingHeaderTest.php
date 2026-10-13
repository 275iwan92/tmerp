<?php

/*
 * REMARK
 * Tujuan       : Menguji Priority dan urutan BOP sebagai perubahan tertunda sampai PROCESS, Earliest start sebagai lantai waktu engine, dan setup masuk durasi operasi.
 * Sumber hulu  : prd_wo (pending_header, earliest_start), PlanChangeService (queuePriority, queueOrder, applyPendingHeader, discardPending), PlanLoader, ScheduleSolver.
 * Input        : WO DRAFT di database dummy; semuanya di transaksi yang di-rollback.
 * Output       : Lulus/gagal.
 * Dipakai oleh : php artisan test (DB_* diarahkan ke ddt*; skip di tm2*).
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoOperation;
use App\Services\Scheduling\Plan\AutoPlanRunner;
use App\Services\Scheduling\Plan\PlanChangeService;
use App\Support\V2\Data\Ppic;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WoPendingHeaderTest extends TestCase
{
    private $prd;

    private PlanChangeService $changes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prd = DB::connection('tm2prd');
        if (! str_starts_with($this->prd->getDatabaseName(), 'ddt')) {
            $this->markTestSkipped('Dummy database only (writes inside a rolled-back transaction).');
        }
        $this->prd->beginTransaction();
        $this->changes = app(PlanChangeService::class);
        Ppic::forget();
    }

    protected function tearDown(): void
    {
        $this->prd?->rollBack();
        Ppic::forget();
        parent::tearDown();
    }

    private function draftWo(): PrdWo
    {
        $wo = PrdWo::whereIn('wo_status', ['DRAFT', 'SIMULATED'])->whereHas('operations')->orderBy('wo_id')->first();
        $this->assertNotNull($wo, 'no Draft Work Order');

        return $wo;
    }

    public function test_priority_waits_for_process_and_discard_forgets_it(): void
    {
        $wo = $this->draftWo();
        $was = [$wo->is_hot_job, $wo->priority];

        $this->changes->queuePriority($wo->wo_id, 'HOT', 'Customer call', 'test');
        $wo->refresh();
        $this->assertSame($was, [$wo->is_hot_job, $wo->priority], 'a pending priority must not change the Work Order yet');
        $this->assertSame('HOT', Ppic::pendingHeader($wo->wo_id)['priority']['kind']);
        $this->assertTrue(Ppic::processState($wo->wo_id)['needed']);

        $this->assertSame(1, $this->changes->discardPending('test', $wo->wo_id));
        $this->assertSame([], Ppic::pendingHeader($wo->wo_id));

        $this->changes->queuePriority($wo->wo_id, 'HOT', 'Customer call', 'test');
        $this->changes->applyPendingHeader($wo->fresh(), 'test');
        $wo->refresh();
        $this->assertTrue((bool) $wo->is_hot_job);
        $this->assertSame('SUPER_URGENT', $wo->priority);
        $this->assertSame([], Ppic::pendingHeader($wo->wo_id));
    }

    public function test_bop_order_waits_for_process_then_renumbers_and_drops_drafts(): void
    {
        $wo = $this->draftWo();
        $ops = $wo->operations()->orderBy('seq_no')->pluck('operation_id')->map(fn ($i) => (int) $i)->all();
        if (count($ops) < 2) {
            $this->markTestSkipped('Work Order has one operation.');
        }
        $new = array_merge([$ops[1], $ops[0]], array_slice($ops, 2));

        $this->changes->queueOrder($wo->wo_id, $new, 'Officer order', 'test');
        $this->assertSame($ops, $wo->operations()->orderBy('seq_no')->pluck('operation_id')->map(fn ($i) => (int) $i)->all(), 'seq_no must not change before PROCESS');
        $this->assertSame($new, array_column(Ppic::bopSheet($wo->wo_id), 'id'), 'the sheet shows the pending order at once');

        $this->changes->applyPendingHeader($wo->fresh(), 'test');
        $this->assertSame($new, $wo->operations()->orderBy('seq_no')->pluck('operation_id')->map(fn ($i) => (int) $i)->all());
        $this->assertSame([], Ppic::pendingHeader($wo->wo_id));
    }

    public function test_earliest_start_is_a_floor_and_setup_is_part_of_the_duration(): void
    {
        $wo = $this->draftWo();
        $floor = CarbonImmutable::now()->addDays(4)->startOfHour();
        $wo->forceFill(['earliest_start' => $floor])->save();
        $this->assertTrue(Ppic::processState($wo->wo_id)['changed'], 'Earliest start is part of the PROCESS fingerprint');

        app(AutoPlanRunner::class)->run('MANUAL', 'test');
        Ppic::forget();

        $ops = PrdWoOperation::where('wo_id', $wo->wo_id)->where('plan_status', 'DRAFT')->whereNotNull('draft_start')->orderBy('seq_no')->get();
        $this->assertNotEmpty($ops);
        $withSetup = 0;
        foreach ($ops as $op) {
            $this->assertGreaterThanOrEqual($floor->getTimestamp(), CarbonImmutable::parse($op->draft_start)->getTimestamp(), 'operation starts before the Earliest start');
            // start -> setup -> run: the booked time is at least setup + run (a calendar break can only make it longer)
            if ($op->draft_machine_id) {
                $need = (int) $op->setup_base_min + (int) $op->setup_change_min + (int) $op->setup_tool_min + (int) $op->run_minutes;
                $have = (int) CarbonImmutable::parse($op->draft_start)->diffInMinutes(CarbonImmutable::parse($op->draft_finish));
                $this->assertGreaterThanOrEqual($need, $have, $op->process_name.': finish - start is shorter than setup + run');
                $withSetup += (int) $op->setup_base_min + (int) $op->setup_change_min + (int) $op->setup_tool_min > 0 ? 1 : 0;
            }
        }
        $this->assertGreaterThan(0, $withSetup, 'no operation carries setup minutes: the check above would be empty');
    }
}
