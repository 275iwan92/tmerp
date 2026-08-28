<?php

namespace Tests\Feature;

use App\Models\Master\EmpEmployee;
use App\Models\Master\ProdMachine;
use App\Models\Master\ProdPartProcessMachineRate;
use App\Models\Master\ProdProcess;
use App\Models\Master\ProdProduct;
use App\Models\Prd\PrdCrewAssignment;
use App\Models\Prd\PrdWo;
use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

// WO Detail dirombak 2026-08-28 (sesi lanjutan, review mockup bolak-balik
// dgn user) -- docs/production_scheduling_wo_generation_delta.md §5 revisi:
// picklist Plan Delivery jadi modal (bukan grid permanen), panel Master
// Card (vw_master_card + prod_mastercard_signoff, SUDAH ADA di skema),
// Bill of Process/Material jadi grid identity-box dgn dblclick-to-edit +
// kolom baru (Operator/Est Mulai/Selesai/Priority/Qty Harus Dibeli). Juga
// layar baru production/process-rate.blade.php (kelola
// prod_part_process_machine_rate/_employee_rate, dulu cuma tinker).
class ProductionWoDetailRebuildTest extends TestCase
{
    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function seededWo(): PrdWo
    {
        return PrdWo::where('wo_no', 'WO2600261')->firstOrFail();
    }

    public function test_priority_updates_and_persists(): void
    {
        $this->actingAs($this->admin());
        $wo = $this->seededWo();
        $this->assertSame('NORMAL', $wo->priority);

        Livewire::test('pages::production.wo-entry', ['wo' => $wo->wo_id])
            ->call('updatePriority', 'SUPER_URGENT');

        $wo->refresh();
        $this->assertSame('SUPER_URGENT', $wo->priority);

        $wo->update(['priority' => 'NORMAL']);
    }

    public function test_operator_dblclick_edit_assigns_and_clears_crew(): void
    {
        $this->actingAs($this->admin());
        $wo = $this->seededWo();
        $op = $wo->operations()->firstOrFail();
        $employee = EmpEmployee::where('activate', true)->firstOrFail();

        $test = Livewire::test('pages::production.wo-entry', ['wo' => $wo->wo_id]);
        $test->call('updateOperator', $op->operation_id, $employee->employee_id);

        $crew = PrdCrewAssignment::where('operation_id', $op->operation_id)->where('crew_role', 'OPERATOR_UTAMA')->first();
        $this->assertNotNull($crew);
        $this->assertSame($employee->employee_id, $crew->employee_id);

        // pilih kosong -> hapus assignment
        $test->call('updateOperator', $op->operation_id, '');
        $this->assertNull(PrdCrewAssignment::where('operation_id', $op->operation_id)->where('crew_role', 'OPERATOR_UTAMA')->first());
    }

    public function test_schedule_datetime_inline_edit(): void
    {
        $this->actingAs($this->admin());
        $wo = $this->seededWo();
        $op = $wo->operations()->firstOrFail();
        $originalStart = $op->target_start;

        Livewire::test('pages::production.wo-entry', ['wo' => $wo->wo_id])
            ->call('updateOperationDateTime', $op->operation_id, 'target_start', '2026-09-05T07:30');

        $op->refresh();
        $this->assertSame('2026-09-05 07:30:00', $op->target_start->format('Y-m-d H:i:s'));

        $op->update(['target_start' => $originalStart]);
    }

    public function test_master_card_tab_renders_from_vw_master_card(): void
    {
        $this->actingAs($this->admin());
        $wo = $this->seededWo();

        Livewire::test('pages::production.wo-entry', ['wo' => $wo->wo_id])
            ->assertSee('Master Card / Kartu Induk')
            ->assertSee('FB-DUPLEX-001');
    }

    public function test_bill_of_material_rows_include_live_warehouse_stock(): void
    {
        $this->actingAs($this->admin());
        $wo = $this->seededWo();

        $rows = Livewire::test('pages::production.wo-entry', ['wo' => $wo->wo_id])
            ->instance()->billOfMaterialRows;

        $this->assertGreaterThan(0, $rows->count());
        $row = $rows->first();
        $this->assertObjectHasProperty('qty_kebutuhan', $row);
        $this->assertObjectHasProperty('qty_tersedia', $row);
        $this->assertSame(max(0, $row->qty_kebutuhan - $row->qty_tersedia), $row->qty_harus_dibeli);
    }

    public function test_plan_delivery_modal_generates_wo_and_redirects(): void
    {
        $this->actingAs($this->admin());
        $wo = $this->seededWo();

        $itemId = DB::connection('tm2sdi')->table('co_order_item')
            ->where('order_id', 2)->where('inquiry_id', 5)->value('id_item');
        $deliveryId = DB::connection('tm2sdi')->table('co_order_delivery')->insertGetId([
            'order_id' => 2, 'item_id' => $itemId, 'shipment_type' => 'PARTIAL',
            'ship_date' => now()->addDays(30)->toDateString(), 'ship_qty' => 150, 'status' => 'PLANNED',
            'created_by' => 'test', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $test = Livewire::test('pages::production.wo-entry', ['wo' => $wo->wo_id])
            ->call('openDeliveryModal')
            ->assertSee('CO2600001');

        $test->call('pickPlanDelivery', $deliveryId);
        $newWo = PrdWo::where('source_delivery_id', $deliveryId)->first();
        $this->assertNotNull($newWo);
        $test->assertRedirect(route('production.wo.entry', ['wo' => $newWo->wo_id]));

        $newWo->operations()->delete();
        $newWo->delete();
        DB::connection('tm2sdi')->table('co_order_delivery')->where('id_delivery', $deliveryId)->delete();
    }

    public function test_process_rate_management_screen_crud(): void
    {
        $this->actingAs($this->admin());

        $product = ProdProduct::firstOrFail();
        $process = ProdProcess::firstOrFail();
        $machine = ProdMachine::firstOrFail();

        $test = Livewire::test('pages::production.process-rate')
            ->assertSee('Total Rate')
            ->call('openNew')
            ->set('product_id', $product->id_product)
            ->set('part_code', 'RATETEST')
            ->set('process_id', $process->id_process)
            ->set('machine_id', $machine->machine_id)
            ->set('target_qty_per_hour', 999)
            ->call('save');

        $rate = ProdPartProcessMachineRate::where('part_code', 'RATETEST')->first();
        $this->assertNotNull($rate);
        $this->assertEquals(999, (float) $rate->target_qty_per_hour);

        $test->call('toggleActive', $rate->rate_id);
        $rate->refresh();
        $this->assertFalse($rate->activate);

        $rate->delete();
    }
}
