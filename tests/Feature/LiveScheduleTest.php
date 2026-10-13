<?php

/*
 * REMARK
 * Tujuan       : Menjaga Live Schedule (Ppic::live): hanya operasi CONFIRMED / berjalan, status sah, good/bad tidak negatif, KPI sama dengan jumlah baris.
 * Sumber hulu  : prd_wo_operation, prd_wo_operation_output(_defect), prd_operation_time_log.
 * Input        : Baca saja (database aktif).
 * Output       : Lulus/gagal.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif; skip bila tidak ada operasi terkonfirmasi.
 */

namespace Tests\Feature;

use App\Support\V2\Data\Ppic;
use Tests\TestCase;

class LiveScheduleTest extends TestCase
{
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
        $this->assertCount(count($live['rows']), $live['gantt']['tasks']);
        $this->assertSame('', $live['gantt']['linked']['process']);
        foreach ($live['gantt']['tasks'] as $t) {
            $this->assertTrue($t['locked'], 'Live Schedule is read only');
        }
    }
}
