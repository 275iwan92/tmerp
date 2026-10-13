<?php

/*
 * REMARK
 * Tujuan       : Menjaga data antrean per proses per mesin (Ppic::queue): setiap baris punya mesin yang ada di daftar proses itu, status sah, progres 0-100.
 * Sumber hulu  : prd_wo_operation (semua WO aktif), prd_wo_operation_output, prod_process_machine.
 * Input        : Baca saja (database aktif).
 * Output       : Lulus/gagal.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif; skip bila tidak ada operasi terjadwal.
 */

namespace Tests\Feature;

use App\Support\V2\Data\Ppic;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WoQueueTest extends TestCase
{
    public function test_queue_rows_follow_the_process_and_machine_lists(): void
    {
        $q = Ppic::queue();
        if ($q['rows'] === []) {
            $this->markTestSkipped('No scheduled operation in this database.');
        }

        $cancelled = DB::connection('tm2prd')->table('prd_wo')->where('wo_status', 'CANCELLED')->pluck('wo_id')->all();
        foreach ($q['rows'] as $r) {
            $this->assertArrayHasKey($r['process'], $q['processes']);
            $this->assertArrayHasKey($r['machine'], $q['machines'][$r['process']], 'machine missing in the list of '.$r['process']);
            $this->assertContains($r['status'], ['Running', 'Confirmed', 'Draft']);
            $this->assertGreaterThanOrEqual(0, $r['progress']);
            $this->assertLessThanOrEqual(100, $r['progress']);
            $this->assertNotContains($r['wo_id'], $cancelled, 'a cancelled Work Order is in the queue');
        }
        // Operations without a machine (SUBCON) that have a schedule are in the queue too, so no process drops out of it.
        $subcon = collect(Ppic::operations())->filter(fn ($o) => ! $o['machine_id'] && $o['start'] && $o['finish']
            && ! in_array($o['task_status'], Ppic::DONE, true));
        $this->assertSame($subcon->count(), collect($q['rows'])->where('machine', 'subcon')->count());
        $starts = array_column($q['rows'], 'start');
        $this->assertCount($q['queued'], $q['rows']);
        $this->assertNotEmpty($starts);
    }
}
