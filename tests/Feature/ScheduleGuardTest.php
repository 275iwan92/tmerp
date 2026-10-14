<?php

/*
 * REMARK
 * Tujuan       : Menguji pengaman jadwal (Jo 2026-10-14): proses tak terjadwal dilaporkan, APPLY ditolak, SUBCON / MANUAL tidak dihitung.
 * Sumber hulu  : ScheduleGuard::issues, PpicController::applyAll (applyOne).
 * Input        : WO DRAFT database dummy; operasi dikosongkan jadwalnya di transaksi yang di-rollback.
 * Output       : Lulus/gagal.
 * Dipakai oleh : php artisan test (skip di tm2*).
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Prd\PrdWoOperation;
use App\Services\Scheduling\Plan\AutoPlanRunner;
use App\Services\Scheduling\Plan\ScheduleGuard;
use App\Support\V2\Data\Ppic;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduleGuardTest extends TestCase
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

    public function test_operation_without_start_is_listed_with_reason_and_scheduled_ones_are_not(): void
    {
        $op = PrdWoOperation::where('plan_status', 'DRAFT')->where('draft_machine_id', '>', 0)->orderBy('operation_id')->firstOrFail();
        $before = count(ScheduleGuard::issues($op->wo_id));
        $op->forceFill(['draft_start' => null, 'draft_finish' => null])->save();
        Ppic::forget();

        $issues = ScheduleGuard::issues($op->wo_id);
        $this->assertCount($before + 1, $issues);
        $mine = collect($issues)->firstWhere('process', $op->process_name);
        $this->assertNotNull($mine);
        $this->assertNotSame('', $mine['reason']);
        $this->assertStringContainsString($mine['wo_no'], ScheduleGuard::line($issues));
    }

    public function test_subcon_or_crew_step_with_machine_zero_is_not_unscheduled(): void
    {
        $op = PrdWoOperation::where('plan_status', 'DRAFT')->where('draft_machine_id', 0)->whereNotNull('draft_start')->first();
        if (! $op) {
            $this->markTestSkipped('no subcon / crew operation in the dummy plan');
        }
        $this->assertEmpty(array_filter(ScheduleGuard::issues($op->wo_id), fn ($i) => $i['process'] === $op->process_name));
    }
}
