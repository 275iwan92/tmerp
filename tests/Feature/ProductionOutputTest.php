<?php

/*
 * REMARK
 * Tujuan       : Menjaga layar Production Output: dua timer (satu jalan sekali, Stop mengunci), simpan hasil akumulatif, log aksi lengkap, dan turunan crew -> antrean.
 * Sumber hulu  : OperatorTimerService, OutputActionLog, ProductionOutput::desk, prd_crew_assignment, antrean CONFIRMED PPIC Planning.
 * Input        : Operasi CONFIRMED yang belum punya timer di database aktif (transaksi di-rollback).
 * Output       : Lulus/gagal; tidak ada data tersisa.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif; skip bila bukan database dummy (ddt*) atau tidak ada operasi CONFIRMED yang bebas.
 */

namespace Tests\Feature;

use App\Services\Scheduling\Operator\OperatorTimerService;
use App\Support\V2\Data\Ppic;
use App\Support\V2\Data\ProductionOutput;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionOutputTest extends TestCase
{
    private OperatorTimerService $svc;

    private array $op;

    protected function setUp(): void
    {
        parent::setUp();
        if (! str_starts_with((string) DB::connection('tm2prd')->getDatabaseName(), 'ddt')) {
            $this->markTestSkipped('Dummy database only.');
        }
        $busy = DB::connection('tm2prd')->table('prd_operation_time_log')->whereNull('ended_at')->pluck('machine_id')->all();
        $used = DB::connection('tm2prd')->table('prd_operation_time_log')->pluck('operation_id')->all();
        $free = collect(Ppic::operations())->first(fn ($o) => $o['plan_status'] === 'CONFIRMED' && $o['machine_id'] && $o['task_status'] === 'ASSIGNED'
            && ! in_array($o['machine_id'], $busy, true) && ! in_array($o['op_id'], $used, true));
        if (! $free) {
            $this->markTestSkipped('No free confirmed operation.');
        }
        $this->op = $free;
        $this->svc = app(OperatorTimerService::class);
        DB::connection('tm2prd')->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (DB::connection('tm2prd')->transactionLevel() > 0) {
            DB::connection('tm2prd')->rollBack();
        }
        parent::tearDown();
    }

    public function test_one_timer_runs_at_a_time_and_stop_locks(): void
    {
        $id = $this->op['op_id'];
        $t = $this->svc->act($id, 'setup', 'play', 'tester');
        $this->assertSame(['run', 'idle'], [$t['setup']['state'], $t['prod']['state']]);

        $t = $this->svc->act($id, 'prod', 'play', 'tester');   // Play prod pauses setup
        $this->assertSame(['pause', 'run'], [$t['setup']['state'], $t['prod']['state']]);
        $this->assertSame(1, DB::connection('tm2prd')->table('prd_operation_time_log')->where('machine_id', $this->op['machine_id'])->whereNull('ended_at')->count());

        $t = $this->svc->act($id, 'prod', 'pause', 'tester');
        $this->assertSame('pause', $t['prod']['state']);

        $t = $this->svc->act($id, 'prod', 'stop', 'tester');
        $this->assertSame('stop', $t['prod']['state']);
        $this->assertNotNull($t['prod']['stop']);
        $this->assertSame('DONE', DB::connection('tm2prd')->table('prd_wo_operation')->where('operation_id', $id)->value('task_status'));

        $this->expectException(\DomainException::class);
        $this->svc->act($id, 'prod', 'play', 'tester');
    }

    public function test_result_is_accumulative_and_every_action_is_logged(): void
    {
        $id = $this->op['op_id'];
        $this->svc->act($id, 'setup', 'play', 'tester');
        $this->svc->saveResult($id, 100, 4, 'tester');
        $sum = $this->svc->saveResult($id, 50, 0, 'tester');
        $this->assertSame(['good' => 150.0, 'bad' => 4.0], $sum);

        $actions = DB::connection('tm2prd')->table('prd_output_action_log')->where('operation_id', $id)->orderBy('log_id')->get();
        $this->assertSame(['SETUP_PLAY', 'SAVE_RESULT', 'SAVE_RESULT'], $actions->pluck('action')->all());
        foreach ($actions as $a) {
            $this->assertSame('tester', $a->acted_by);
            $this->assertNotNull($a->acted_at);
            $this->assertNotNull($a->client_ip);
            $this->assertNotNull($a->detail);
        }
    }

    public function test_empty_result_is_refused(): void
    {
        $this->expectException(\DomainException::class);
        $this->svc->saveResult($this->op['op_id'], 0, 0, 'tester');
    }

    public function test_desk_follows_the_crew_and_lists_confirmed_jobs_of_that_machine_and_process(): void
    {
        $employee = 987654321;
        DB::connection('tm2prd')->table('prd_crew_assignment')->insert(['operation_id' => $this->op['op_id'], 'employee_id' => $employee, 'crew_role' => 'OPERATOR_UTAMA', 'created_by' => 'test']);

        $desk = ProductionOutput::desk((object) ['employee_id' => $employee, 'is_admin' => false]);
        $this->assertArrayNotHasKey('empty', $desk);
        $this->assertSame('PRODUCTION PROCESS : '.$this->op['process'], $desk['title']);
        $jobs = collect($desk['jobs']);
        $this->assertTrue($jobs->contains('current', true));
        $this->assertGreaterThanOrEqual(1, $jobs->count());
        $this->assertSame((float) $this->op['qty'], $desk['target']['qty']);

        // a user without a crew sees nothing; an administrator without a crew first gets the "Enter as" picker, then the form of the pick
        $this->assertArrayHasKey('empty', ProductionOutput::desk((object) ['employee_id' => 123456789, 'is_admin' => false]));
        $picker = ProductionOutput::desk((object) ['employee_id' => null, 'is_admin' => true]);
        $this->assertNotEmpty($picker['picker'] ?? []);
        $picked = ProductionOutput::desk((object) ['employee_id' => null, 'is_admin' => true], ['machine' => $this->op['machine_id'], 'process' => $this->op['process']]);
        $this->assertArrayNotHasKey('picker', $picked);
        $this->assertArrayNotHasKey('empty', $picked);
    }

    public function test_stop_before_start_is_refused(): void
    {
        $this->expectException(\DomainException::class);
        $this->svc->act($this->op['op_id'], 'prod', 'stop', 'tester');
    }

    public function test_a_paused_job_does_not_block_the_next_job_on_the_machine(): void
    {
        $other = collect(Ppic::operations())->first(fn ($o) => $o['plan_status'] === 'CONFIRMED' && (int) $o['machine_id'] === (int) $this->op['machine_id'] && $o['op_id'] !== $this->op['op_id']);
        if (! $other) {
            $this->markTestSkipped('Needs a second confirmed job on the same machine.');
        }
        $this->svc->act($this->op['op_id'], 'prod', 'play', 'tester');
        $this->svc->act($this->op['op_id'], 'prod', 'pause', 'tester');
        $t = $this->svc->act($other['op_id'], 'prod', 'play', 'tester');   // job A is paused, so job B may start
        $this->assertSame('run', $t['prod']['state']);
        $this->expectException(\DomainException::class);                  // job B runs, so job A may not start now
        $this->svc->act($this->op['op_id'], 'prod', 'play', 'tester');
    }
}
