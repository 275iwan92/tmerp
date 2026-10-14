<?php

/*
 * REMARK
 * Tujuan       : Menjaga aturan Jo 14 Okt: job Confirmed yang belum mulai tetapi bermasalah (gate BLOCK atau material datang setelah slotnya) tidak menahan mesin; engine menarik job berikutnya.
 * Sumber hulu  : PlanLoader::op (held), ScheduleSolver.
 * Input        : Operasi CONFIRMED / belum mulai di database dummy; transaksi di-rollback.
 * Output       : Lulus/gagal.
 * Dipakai oleh : php artisan test (skip di tm2*).
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Prd\PrdWoOperation;
use App\Services\Scheduling\Plan\PlanLoader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlanHeldJobTest extends TestCase
{
    private $prd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prd = DB::connection('tm2prd');
        if (! str_starts_with($this->prd->getDatabaseName(), 'ddt')) {
            $this->markTestSkipped('Dummy database only.');
        }
        $this->prd->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->prd?->rollBack();
        parent::tearDown();
    }

    private function fixedOf(int $opId): ?array
    {
        foreach (app(PlanLoader::class)->wos(CarbonImmutable::parse('2026-10-13 08:00')) as $wo) {
            foreach ($wo->ops as $op) {
                if ($op->id === $opId) {
                    return $op->fixed;
                }
            }
        }

        return null;
    }

    public function test_confirmed_job_with_a_blocked_gate_releases_its_slot(): void
    {
        $op = PrdWoOperation::where('plan_status', 'CONFIRMED')->where('is_locked', 1)->whereIn('task_status', ['PENDING', 'ASSIGNED'])
            ->whereNotNull('target_start')->whereNotNull('machine_id')->first();
        if (! $op) {
            $this->markTestSkipped('No confirmed, not started operation.');
        }
        $op->forceFill(['gate_material' => 'READY', 'material_ready_at' => null])->save();
        $this->assertNotNull($this->fixedOf($op->operation_id), 'a healthy confirmed job keeps its slot');

        $op->forceFill(['gate_material' => 'BLOCK'])->save();
        $this->assertNull($this->fixedOf($op->operation_id), 'a blocked job gives the machine to the next job');
    }
}
