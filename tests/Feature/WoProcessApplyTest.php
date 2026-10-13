<?php

/*
 * REMARK
 * Tujuan       : Menguji PROCESS dan APPLY di Simulation: sidik perubahan (kapan PROCESS menyala), seret tertunda + discard, Apply mengunci SEMUA operasi WO, APPLY ALL.
 * Sumber hulu  : prd_wo (process_hash), prd_wo_operation (pending_move), PlanConfirmService, PlanChangeService, PpicController::applyAll.
 * Input        : WO DRAFT di database dummy; semuanya di transaksi yang di-rollback.
 * Output       : Lulus/gagal.
 * Dipakai oleh : php artisan test (DB_* diarahkan ke ddt*; skip di tm2*).
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoOperation;
use App\Models\Sys\SecUser;
use App\Services\Scheduling\Plan\AutoPlanRunner;
use App\Services\Scheduling\Plan\PlanChangeService;
use App\Services\Scheduling\Plan\PlanConfirmService;
use App\Support\V2\Data\Ppic;
use Carbon\CarbonImmutable;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WoProcessApplyTest extends TestCase
{
    private $prd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prd = DB::connection('tm2prd');
        if (! str_starts_with($this->prd->getDatabaseName(), 'ddt')) {
            $this->markTestSkipped('Dummy database only (writes inside a rolled-back transaction).');
        }
        $this->prd->beginTransaction();
        Ppic::forget();
        app(AutoPlanRunner::class)->run('EVENT', 'test');
        Ppic::forget();
    }

    protected function tearDown(): void
    {
        $this->prd?->rollBack();
        Ppic::forget();
        parent::tearDown();
    }

    private function draftOp(): PrdWoOperation
    {
        $op = PrdWoOperation::where('plan_status', 'DRAFT')->where('draft_machine_id', '>', 0)
            ->whereHas('wo', fn ($q) => $q->whereIn('wo_status', ['DRAFT', 'SIMULATED']))->orderBy('operation_id')->first();
        $this->assertNotNull($op, 'no Draft operation');

        return $op;
    }

    public function test_process_state_follows_the_inputs_and_pending_drags(): void
    {
        $op = $this->draftOp();
        $wo = (int) $op->wo_id;

        // never processed: PROCESS is needed
        $this->assertTrue(Ppic::processState($wo)['changed']);

        // processed = the stored fingerprint equals the current one -> not needed
        PrdWo::whereKey($wo)->update(['process_hash' => Ppic::fingerprint($wo)]);
        $this->assertFalse(Ppic::processState($wo)['needed']);

        // an input changed (target of an operation) -> needed again
        PrdWoOperation::whereKey($op->operation_id)->update(['rate_band_value' => 123.45]);
        $this->assertTrue(Ppic::processState($wo)['changed']);
        PrdWo::whereKey($wo)->update(['process_hash' => Ppic::fingerprint($wo)]);

        // a queued drag is a pending change (counts, lights PROCESS) and Discard puts the operation back
        $svc = app(PlanChangeService::class);
        $before = [(int) $op->draft_machine_id, $op->draft_start->format('Y-m-d H:i')];
        $n = $svc->queueMove($op->operation_id, (int) $op->draft_machine_id, CarbonImmutable::parse($op->draft_start)->addHours(3), 'test drag', 'test');
        $this->assertSame(1, $n);
        // the BOP sheet shows the same Start as the Simulation bar (both read the pending placement), and Discard brings it back
        $bopStart = function () use ($wo, $op) {
            Ppic::forget();

            return collect(Ppic::bopSheet($wo))->firstWhere('id', $op->operation_id)['start'];
        };
        $moved = PrdWoOperation::find($op->operation_id);
        $this->assertSame(Locale::dt($moved->draft_start), $bopStart());
        $this->assertNotSame(Locale::dt($before[1].':00'), $bopStart());
        $st = Ppic::processState($wo);
        $this->assertSame(1, $st['pending']);
        $this->assertTrue($st['needed']);
        $this->assertSame(1, $svc->discardPending('test'));
        $back = PrdWoOperation::find($op->operation_id);
        $this->assertSame($before, [(int) $back->draft_machine_id, $back->draft_start->format('Y-m-d H:i')]);
        $this->assertSame(0, Ppic::processState($wo)['pending']);
        $this->assertSame(Locale::dt($back->draft_start), $bopStart());
    }

    public function test_apply_fixes_every_operation_and_the_engine_does_not_move_them(): void
    {
        $op = $this->draftOp();
        $wo = (int) $op->wo_id;
        $run = Ppic::draftRun();
        app(PlanConfirmService::class)->confirm((int) $run->run_id, 'test', null, $wo);

        $ops = PrdWoOperation::where('wo_id', $wo)->get();
        $this->assertTrue($ops->every(fn ($o) => $o->plan_status === 'CONFIRMED' && $o->is_locked), 'an operation of the applied Work Order is not fixed');
        $pos = $ops->mapWithKeys(fn ($o) => [$o->operation_id => [(int) $o->machine_id, (string) $o->target_start]])->all();

        app(AutoPlanRunner::class)->run('EVENT', 'test');   // another event: the engine plans again
        $after = PrdWoOperation::where('wo_id', $wo)->get()->mapWithKeys(fn ($o) => [$o->operation_id => [(int) $o->machine_id, (string) $o->target_start]])->all();
        $this->assertSame($pos, $after);
        $this->assertSame('SCHEDULED', PrdWo::find($wo)->wo_status);
    }

    public function test_apply_all_applies_the_work_orders_of_a_process_and_machine(): void
    {
        $this->actingAs(SecUser::where('is_admin', 1)->firstOrFail());
        $op = $this->draftOp();
        $key = strtoupper($op->process_name);
        $expect = collect(Ppic::applyCandidates($key, 'm'.$op->draft_machine_id))->pluck('wo_no')->all();
        $this->assertContains(PrdWo::find($op->wo_id)->wo_no, $expect);

        $res = $this->postJson(route('v2.ppic.apply-all'), ['process' => $key, 'machine' => 'm'.$op->draft_machine_id])->assertOk()->json();
        $this->assertEqualsCanonicalizing($expect, array_column($res['results'], 'wo'));
        foreach ($res['results'] as $r) {
            $this->assertArrayHasKey('ok', $r);
        }
    }
}
