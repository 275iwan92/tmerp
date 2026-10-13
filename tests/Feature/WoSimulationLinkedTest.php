<?php

/*
 * REMARK
 * Tujuan       : Menguji dropdown bertaut Process lalu Machine di Simulation (hanya mesin mampu; operasi WO lain ikut dimuat).
 * Sumber hulu  : Ppic::linkedBoard, prod_process_machine.
 * Input        : WO DRAFT dan operasi yang ada (baca saja).
 * Output       : Lulus/gagal.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Support\V2\Data\Ppic;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Simulation Gantt of the Work Order form: Process -> Machine are linked. The machines offered for a process are exactly the
 * active machines able to run it (prod_process_machine). Read only.
 */
class WoSimulationLinkedTest extends TestCase
{
    public function test_machine_list_follows_the_chosen_process(): void
    {
        $wo = DB::connection('tm2prd')->table('prd_wo as w')->join('prd_wo_operation as o', 'o.wo_id', '=', 'w.wo_id')
            ->where('w.wo_status', 'DRAFT')->whereNotNull('o.process_id')->orderBy('w.wo_id')->value('w.wo_id');
        if (! $wo) {
            $this->markTestSkipped('No Draft Work Order with operations.');
        }

        $g = Ppic::linkedBoard((int) $wo, ['rows' => [], 'tasks' => []]);
        $this->assertNotEmpty($g['linked']['processes']);
        $this->assertArrayHasKey($g['linked']['process'], $g['linked']['processes']);   // default = first BOP process

        $ops = DB::connection('tm2prd')->table('prd_wo_operation')->where('wo_id', $wo)->get(['process_name', 'process_id'])
            ->keyBy(fn ($o) => strtoupper($o->process_name));
        foreach ($g['linked']['machines'] as $key => $rowKeys) {
            $able = DB::connection('tm2mst')->table('prod_process_machine as c')->join('prod_machine as m', 'm.machine_id', '=', 'c.machine_id')
                ->where('c.process_id', $ops[$key]->process_id)->where('c.activate', 1)->where('m.activate', 1)->pluck('m.machine_id')
                ->map(fn ($id) => 'm'.$id)->all();
            // every offered machine is capable (or the one the engine already placed the operation on)
            $placed = DB::connection('tm2prd')->table('prd_wo_operation')->where('wo_id', $wo)->whereRaw('UPPER(process_name) = ?', [$key])
                ->pluck('machine_id')->filter()->map(fn ($id) => 'm'.$id)->all();
            $this->assertEquals([], array_values(array_diff($rowKeys, $able, $placed)), "process $key offers a machine that cannot run it");
            $this->assertEquals([], array_values(array_diff($able, $rowKeys)), "process $key misses a capable machine");
        }
    }

    public function test_other_work_orders_on_the_same_process_and_machine_are_on_the_board(): void
    {
        $ops = collect(Ppic::operations())->filter(fn ($o) => $o['machine_id'] && $o['start'] && $o['finish']);
        $pair = $ops->groupBy(fn ($o) => $o['machine_id'].'|'.strtoupper($o['process']))->first(fn ($g) => $g->pluck('wo_id')->unique()->count() >= 2);
        if (! $pair) {
            $this->markTestSkipped('No machine runs the same process for two Work Orders.');
        }
        $woId = $pair->first()['wo_id'];
        $g = Ppic::linkedBoard($woId, Ppic::gantt($ops->all()));
        $row = 'm'.$pair->first()['machine_id'];
        $onRow = collect($g['tasks'])->where('row', $row)->pluck('id');

        foreach ($pair as $o) {
            $this->assertTrue($onRow->contains($o['op_id']), 'operation '.$o['op_id'].' of '.$o['wo_no'].' is missing on '.$row);
        }
        $this->assertStringContainsString($pair->first()['wo_no'], collect($g['tasks'])->firstWhere('id', $pair->first()['op_id'])['label']);
    }
}
