<?php

/*
 * REMARK
 * Tujuan       : Membuktikan pratinjau panel dampak (PlanChangeService::impact) sama dengan hasil Move into Draft: operasi yang bergeser dan posisi barunya.
 * Sumber hulu  : prd_wo_operation (Draft), AutoPlanRunner, PlanChangeService::impact dan move.
 * Input        : Satu operasi Draft; jam mulai digeser +2 jam di mesin yang sama; semuanya di transaksi yang di-rollback.
 * Output       : Lulus bila daftar operasi bergeser dan posisi barunya sama dengan keadaan sesudah move.
 * Dipakai oleh : php artisan test (DB_* diarahkan ke ddt*; skip di tm2*).
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Prd\PrdWoOperation;
use App\Services\Scheduling\Plan\AutoPlanRunner;
use App\Services\Scheduling\Plan\PlanChangeService;
use App\Support\V2\Data\Ppic;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WoImpactPreviewTest extends TestCase
{
    public function test_preview_equals_the_result_of_move_into_draft(): void
    {
        $prd = DB::connection('tm2prd');
        if (! str_starts_with($prd->getDatabaseName(), 'ddt')) {
            $this->markTestSkipped('Writes a plan inside a rolled-back transaction: only on the dummy database (ddt*).');
        }
        $prd->beginTransaction();
        try {
            app(AutoPlanRunner::class)->run('EVENT', 'test');   // a fresh plan: the stored Draft is what the engine would plan now
            $op = PrdWoOperation::where('plan_status', 'DRAFT')->where('draft_machine_id', '>', 0)->orderBy('operation_id')->first();
            if (! $op) {
                $this->markTestSkipped('No Draft operation on a machine.');
            }
            $snap = fn () => PrdWoOperation::whereNotNull('operation_id')->get()->mapWithKeys(fn ($o) => [$o->operation_id => [
                (int) ($o->plan_status === 'DRAFT' ? $o->draft_machine_id : $o->machine_id),
                (string) ($o->plan_status === 'DRAFT' ? $o->draft_start : $o->target_start)]])->all();
            $before = $snap();
            $svc = app(PlanChangeService::class);
            $machine = (int) $op->draft_machine_id;
            $start = CarbonImmutable::parse($op->draft_start)->addHours(2);

            $imp = $svc->impact($op->operation_id, $machine, $start);
            $this->assertTrue($imp['ok'], (string) ($imp['reason'] ?? ''));
            $svc->move($op->operation_id, $machine, $start, 'preview test', 'test');
            $after = $snap();

            $changed = [];
            foreach ($after as $id => $pos) {
                if ($id !== $op->operation_id && ($before[$id] ?? null) !== $pos) {
                    $changed[] = $id;
                }
            }
            $preview = array_column($imp['moves'], 'op');
            sort($changed);
            sort($preview);
            $this->assertSame($changed, $preview, 'the operations that move differ between preview and result');
            foreach ($imp['moves'] as $m) {
                $this->assertSame($after[$m['op']][0], $m['to']['machine']);
                $this->assertSame(CarbonImmutable::parse($after[$m['op']][1])->format('Y-m-d H:i'), $m['to']['start']->format('Y-m-d H:i'));
            }
        } finally {
            $prd->rollBack();
        }
    }

    public function test_same_place_is_no_change_on_a_fresh_plan(): void
    {
        $prd = DB::connection('tm2prd');
        if (! str_starts_with($prd->getDatabaseName(), 'ddt')) {
            $this->markTestSkipped('Dummy database only.');
        }
        $prd->beginTransaction();
        try {
            app(AutoPlanRunner::class)->run('EVENT', 'test');
            $op = PrdWoOperation::where('plan_status', 'DRAFT')->where('draft_machine_id', '>', 0)->orderBy('operation_id')->first();
            if (! $op) {
                $this->markTestSkipped('No Draft operation on a machine.');
            }
            $imp = app(PlanChangeService::class)->impact($op->operation_id, (int) $op->draft_machine_id, CarbonImmutable::parse($op->draft_start));
            $this->assertTrue($imp['ok']);
            $this->assertTrue($imp['no_change'] ?? false, 'dropping an operation where it is must say no change');
        } finally {
            $prd->rollBack();
        }
    }

    public function test_applied_work_order_is_locked_as_a_whole(): void
    {
        $prd = DB::connection('tm2prd');
        if (! str_starts_with($prd->getDatabaseName(), 'ddt')) {
            $this->markTestSkipped('Dummy database only.');
        }
        $prd->beginTransaction();
        try {
            app(AutoPlanRunner::class)->run('EVENT', 'test');
            $op = PrdWoOperation::where('plan_status', 'DRAFT')->where('draft_machine_id', '>', 0)->orderBy('operation_id')->first();
            if (! $op) {
                $this->markTestSkipped('No Draft operation on a machine.');
            }
            $svc = app(PlanChangeService::class);
            $start = CarbonImmutable::parse($op->draft_start)->addHours(2);
            $this->assertTrue($svc->impact($op->operation_id, (int) $op->draft_machine_id, $start)['ok']);

            // The Work Order is applied (status SCHEDULED) although this operation is still Draft: the move is refused.
            $prd->table('prd_wo')->where('wo_id', $op->wo_id)->update(['wo_status' => 'SCHEDULED']);
            $r = $svc->impact($op->operation_id, (int) $op->draft_machine_id, $start);
            $this->assertFalse($r['ok']);
            $this->assertStringContainsString('Applied WO is locked', $r['reason']);

            // and the simulation board shows every operation of that Work Order as locked
            Ppic::forget();
            $g = Ppic::simulation((int) $op->wo_id);
            $mine = collect($g['tasks'])->filter(fn ($t) => in_array($t['id'], $prd->table('prd_wo_operation')->where('wo_id', $op->wo_id)->pluck('operation_id')->all()));
            $this->assertNotEmpty($mine);
            $this->assertTrue($mine->every(fn ($t) => ! empty($t['locked'])), 'an applied Work Order has a draggable bar');
        } finally {
            $prd->rollBack();
            Ppic::forget();
        }
    }
}
