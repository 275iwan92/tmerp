<?php

/*
 * REMARK
 * Tujuan       : Menjaga Live Schedule (Ppic::live): hanya operasi CONFIRMED / berjalan, status sah, good/bad tidak negatif, KPI sama dengan jumlah baris.
 * Sumber hulu  : prd_wo_operation, prd_wo_operation_output(_defect), prd_operation_time_log.
 * Input        : Baca saja (database aktif).
 * Output       : Lulus/gagal.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif; skip bila tidak ada operasi terkonfirmasi. Gantt hanya menggambar baris yang selesai setelah Ppic::origin() (kemarin 00:00).
 */

namespace Tests\Feature;

use App\Support\V2\Data\Ppic;
use Tests\Support\WoFixture;
use Tests\TestCase;

class LiveScheduleTest extends TestCase
{
    use WoFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWoFixture();
    }

    protected function tearDown(): void
    {
        $this->tearDownWoFixture();
        parent::tearDown();
    }

    // A confirmed operation inside the Gantt window (tomorrow morning) must be drawn as one locked task.
    public function test_a_confirmed_operation_inside_the_window_is_drawn_as_a_locked_task(): void
    {
        $wo = $this->seedWo('LIVE1');
        $op = $wo->operations()->firstOrFail();
        $start = Ppic::now()->addDay()->setTime(8, 0);
        $op->update(['plan_status' => 'CONFIRMED', 'target_start' => $start, 'target_finish' => $start->addHours(2), 'task_status' => 'ASSIGNED']);

        $live = Ppic::live();
        $row = collect($live['rows'])->firstWhere('id', $op->operation_id);
        $this->assertNotNull($row);
        $task = collect($live['gantt']['tasks'])->firstWhere('id', $op->operation_id);
        $this->assertNotNull($task);
        $this->assertTrue($task['locked']);
    }

    public function test_live_rows_are_confirmed_or_running_with_valid_status_and_matching_kpi(): void
    {
        $live = Ppic::live();
        if ($live['rows'] === []) {
            $this->markTestSkipped('No confirmed operation in this database.');
        }

        $byOp = collect(Ppic::operations())->keyBy('op_id');
        foreach ($live['rows'] as $r) {
            $o = $byOp[$r['id']];
            $this->assertTrue($o['plan_status'] === 'CONFIRMED' || $o['task_status'] === 'RUNNING');
            $this->assertContains($r['status'], ['Running', 'Paused', 'Late', 'Locked', 'Scheduled', 'Done']);
            $this->assertGreaterThanOrEqual(0, $r['good']);
            $this->assertGreaterThanOrEqual(0, $r['bad']);
        }
        $this->assertSame(count($live['rows']), $live['kpi']['total']);
        $this->assertSame(count($live['rows']), $live['kpi']['running'] + $live['kpi']['paused'] + $live['kpi']['late'] + $live['kpi']['scheduled'] + $live['kpi']['done']);
        // The Gantt clock starts at midnight of yesterday (Ppic::origin): a live row that finished before that is listed in the table but not drawn.
        $drawn = array_filter($live['rows'], fn ($r) => $r['plan_start'] && $r['plan_finish'] && $r['plan_finish']->greaterThan(Ppic::origin()));
        $this->assertCount(count($drawn), $live['gantt']['tasks']);
        $this->assertSame('', $live['gantt']['linked']['process']);
        foreach ($live['gantt']['tasks'] as $t) {
            $this->assertTrue($t['locked'], 'Live Schedule is read only');
        }
    }
}
