<?php

/*
 * REMARK
 * Tujuan       : Menguji gerbang APPLY TO LIVE SCHEDULE: wajib PROCESS lulus dan semua bahan baku siap (aturan Jo 2026-10-14).
 * Sumber hulu  : GateService::materialShortages, Ppic::applyBlockedReason, PlanConfirmService::confirm, PpicController::applyAll.
 * Input        : WO DRAFT di database dummy dengan baris prd_wo_material; semuanya di transaksi yang di-rollback.
 * Output       : Lulus/gagal.
 * Dipakai oleh : php artisan test (DB_* diarahkan ke ddt*; skip di tm2*).
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoOperation;
use App\Models\Sys\SecUser;
use App\Services\Scheduling\Plan\AutoPlanRunner;
use App\Services\Scheduling\Plan\GateService;
use App\Services\Scheduling\Plan\PlanConfirmService;
use App\Support\V2\Data\Ppic;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WoApplyGateTest extends TestCase
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

    /** A Draft Work Order that has material lines, with every line made ready (Required 0 lines are ignored by the gate). */
    private function readyWo(): int
    {
        $wo = PrdWoOperation::where('plan_status', 'DRAFT')->where('draft_machine_id', '>', 0)
            ->whereHas('wo', fn ($q) => $q->whereIn('wo_status', ['DRAFT', 'SIMULATED']))
            ->whereIn('wo_id', $this->prd->table('prd_wo_material')->select('wo_id'))->orderBy('operation_id')->value('wo_id');
        $this->assertNotNull($wo, 'no Draft Work Order with material lines');
        $this->prd->table('prd_wo_material')->where('wo_id', $wo)->update(['qty_required' => 0]);
        PrdWo::whereKey($wo)->update(['process_hash' => Ppic::fingerprint((int) $wo)]);
        Ppic::forget();

        return (int) $wo;
    }

    private function makeShort(int $wo): void
    {
        $this->prd->table('prd_wo_material')->where('wo_id', $wo)->limit(1)->update(['qty_required' => 999999999, 'qty_release' => 0]);
        PrdWo::whereKey($wo)->update(['process_hash' => Ppic::fingerprint($wo)]);
        Ppic::forget();
    }

    public function test_ready_and_processed_work_order_may_apply(): void
    {
        $wo = $this->readyWo();
        $this->assertSame([], app(GateService::class)->materialShortages(PrdWo::find($wo)));
        $this->assertNull(Ppic::applyBlockedReason($wo));
        $res = app(PlanConfirmService::class)->confirm((int) Ppic::draftRun()->run_id, 'test', null, $wo);
        $this->assertGreaterThan(0, $res['confirmed']);
    }

    public function test_not_processed_or_changed_after_process_is_refused(): void
    {
        $wo = $this->readyWo();
        PrdWo::whereKey($wo)->update(['process_hash' => null]);
        $this->assertSame('Click PROCESS first.', Ppic::applyBlockedReason($wo));

        PrdWo::whereKey($wo)->update(['process_hash' => Ppic::fingerprint($wo)]);
        PrdWoOperation::where('wo_id', $wo)->orderBy('operation_id')->limit(1)->update(['rate_band_value' => 987.65]);
        $this->assertSame('Data changed, click PROCESS again.', Ppic::applyBlockedReason($wo));
    }

    public function test_short_material_refuses_apply_and_writes_nothing(): void
    {
        $wo = $this->readyWo();
        $this->makeShort($wo);
        $short = app(GateService::class)->materialShortages(PrdWo::find($wo));
        $this->assertNotEmpty($short);
        $this->assertStringContainsString('Material is not ready', (string) Ppic::applyBlockedReason($wo));

        try {
            app(PlanConfirmService::class)->confirm((int) Ppic::draftRun()->run_id, 'test', null, $wo);
            $this->fail('apply of a Work Order with short material must be refused');
        } catch (\DomainException $e) {
            $this->assertStringContainsString($short[0]['material'], $e->getMessage());
        }
        $this->assertSame(0, PrdWoOperation::where('wo_id', $wo)->where('plan_status', 'CONFIRMED')->count());
    }

    public function test_apply_all_skips_the_work_order_that_is_not_ready(): void
    {
        $this->actingAs(SecUser::where('is_admin', 1)->firstOrFail());
        $wo = $this->readyWo();
        $this->makeShort($wo);
        $op = PrdWoOperation::where('wo_id', $wo)->where('draft_machine_id', '>', 0)->firstOrFail();

        $res = $this->postJson(route('v2.ppic.apply-all'), ['process' => strtoupper($op->process_name), 'machine' => 'm'.$op->draft_machine_id])->assertOk()->json();
        $mine = collect($res['results'])->firstWhere('wo', PrdWo::find($wo)->wo_no);
        $this->assertNotNull($mine);
        $this->assertFalse($mine['ok']);
        $this->assertStringContainsString('Material is not ready', $mine['message']);
        $this->assertSame(0, PrdWoOperation::where('wo_id', $wo)->where('plan_status', 'CONFIRMED')->count());
    }
}
