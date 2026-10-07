<?php

namespace Tests\Feature;

use App\Models\Acc\FinCloseTask;
use App\Models\Acc\FinPeriod;
use App\Models\Acc\FinPeriodLog;
use App\Models\Sys\SecUser;
use App\Services\Acc\CloseService;
use App\Support\V2\Data\ClosingDesk as Closing;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Period Closing v2 (C3c). Memakai tahun fiskal uji 2098 (periode 1) yang dibuat dan dihapus sendiri. Tes TIDAK mengunci periode nyata; kunci permanen hanya
// diuji sebagai penolakan (blocker) pada periode uji.
class ClosingDeskTest extends TestCase
{
    private const YEAR = 2098;

    private FinPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->period = FinPeriod::create(['fiscal_year' => self::YEAR, 'period_no' => 1, 'period_name' => 'ZZ Test Jan 2098', 'start_date' => '2098-01-01', 'end_date' => '2098-01-31',
            'is_adjustment' => false, 'status' => 'OPEN', 'created_by' => 'test']);
    }

    protected function tearDown(): void
    {
        if (isset($this->period)) {
            FinCloseTask::where('period_id', $this->period->period_id)->delete();
            FinPeriodLog::where('period_id', $this->period->period_id)->delete();
            $this->period->delete();
        }
        parent::tearDown();
    }

    public function test_start_creates_the_checklist_once_and_tasks_can_be_completed_and_reopened(): void
    {
        Closing::start($this->period, 'test');
        Closing::start($this->period, 'test');
        $tasks = Closing::tasks($this->period);
        $this->assertCount(count(CloseService::TASKS), $tasks);
        $this->assertSame(range(1, count($tasks)), $tasks->pluck('step_no')->all());

        Closing::setTask($tasks[0]->task_id, true, 'tester');
        $done = $tasks[0]->fresh();
        $this->assertSame('COMPLETE', $done->status);
        $this->assertSame('tester', $done->completed_by);
        $this->assertSame(1, Closing::completion(Closing::tasks($this->period), [])['done']);

        Closing::setTask($tasks[0]->task_id, false, 'tester');
        $this->assertSame('IN_PROGRESS', $tasks[0]->fresh()->status);
        $this->assertNull($tasks[0]->fresh()->completed_by);
    }

    public function test_status_change_needs_a_reason_and_is_logged(): void
    {
        try {
            Closing::changeStatus($this->period, 'ON_HOLD', 'no', 'test');
            $this->fail('A reason of at least 5 characters is required.');
        } catch (ValidationException) {
            $this->assertSame('OPEN', $this->period->fresh()->status);
        }

        Closing::changeStatus($this->period, 'ON_HOLD', 'Waiting for the bank statement', 'test');
        $this->assertSame('ON_HOLD', $this->period->fresh()->status);
        Closing::changeStatus($this->period->fresh(), 'OPEN', 'Statement received', 'test');
        $this->assertSame('OPEN', $this->period->fresh()->status);
        $this->assertSame(['ON_HOLD', 'OPEN'], FinPeriodLog::where('period_id', $this->period->period_id)->orderBy('log_id')->pluck('to_status')->all());

        $this->expectException(ValidationException::class);
        Closing::changeStatus($this->period, 'SOMEWHERE', 'Not a real status', 'test');
    }

    public function test_locking_is_refused_while_blockers_remain(): void
    {
        Closing::start($this->period, 'test');
        $blockers = Closing::blockers($this->period);
        $this->assertNotEmpty($blockers, 'open checklist items and the missing inventory period must block the lock');

        try {
            Closing::changeStatus($this->period, 'CLOSED', 'Trying to lock too early', 'test');
            $this->fail('The period must not lock while blockers remain.');
        } catch (ValidationException $e) {
            $this->assertSame('OPEN', $this->period->fresh()->status);
            $this->assertArrayHasKey('finance', $e->errors());
        }
    }

    public function test_period_selection_and_shapes(): void
    {
        $this->assertSame($this->period->period_id, Closing::period(self::YEAR, $this->period->period_id)->period_id);
        $this->assertSame($this->period->period_id, Closing::period(self::YEAR, null)->period_id);
        $this->assertContains(self::YEAR, Closing::years());
        $this->assertCount(1, Closing::periods(self::YEAR));

        $kpis = Closing::kpis($this->period, Closing::completion(collect(), []), null);
        $this->assertSame(['Closing Period', 'Completion', 'Open Exceptions', 'Previous Period'], array_column($kpis, 'label'));
        $this->assertSame(['cutoff', 'reconcile', 'schedules', 'review', 'lock'], array_column(Closing::flow(), 'key'));
        [$headers] = Closing::checklistCsv(collect());
        $this->assertSame(['Step', 'Control', 'Description', 'Status', 'Completed by', 'Completed at'], $headers);
    }

    public function test_screen_and_routes(): void
    {
        $admin = SecUser::where('is_admin', true)->firstOrFail();
        $scope = ['year' => self::YEAR, 'period' => $this->period->period_id];
        $screen = route('v2.screen', 'acc-closing').'?'.http_build_query($scope);
        $this->actingAs($admin)->get($screen)->assertOk()->assertSee('Start Closing')->assertSee('ZZ Test Jan 2098');
        $this->actingAs($admin)->get($screen.'&to=CLOSED')->assertOk()->assertSee('Lock Period Permanently');

        $this->actingAs($admin)->post(route('v2.acc.closing.start'), $scope)->assertRedirect()->assertSessionHas('v2_flash');
        $task = Closing::tasks($this->period)->first();
        $this->actingAs($admin)->post(route('v2.acc.closing.task', [$task->task_id, 'complete']), $scope)->assertRedirect();
        $this->assertSame('COMPLETE', $task->fresh()->status);
        $this->actingAs($admin)->get($screen)->assertOk()->assertSee('Approve & Lock');

        $this->actingAs($admin)->post(route('v2.acc.closing.status'), $scope + ['status' => 'ON_HOLD', 'reason' => 'x'])->assertSessionHasErrors();
        $this->actingAs($admin)->post(route('v2.acc.closing.status'), $scope + ['status' => 'ON_HOLD', 'reason' => 'Hold for review'])->assertRedirect()->assertSessionHas('v2_flash');
        $this->assertSame('ON_HOLD', $this->period->fresh()->status);
        $this->actingAs($admin)->post(route('v2.acc.closing.status'), $scope + ['status' => 'CLOSED', 'reason' => 'Lock attempt'])->assertSessionHasErrors('finance');
        $this->assertSame('ON_HOLD', $this->period->fresh()->status);

        $csv = $this->actingAs($admin)->get(route('v2.acc.closing.checklist-csv', $scope));
        $csv->assertOk();
        $this->assertStringContainsString('Step,Control', $csv->streamedContent());
    }
}
