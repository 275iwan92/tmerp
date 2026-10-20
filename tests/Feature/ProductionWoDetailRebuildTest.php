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
use App\Support\V2\Grid\Prod\MachineRateLookup;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\WoFixture;
use Tests\TestCase;

// WO Detail dirombak 2026-08-28 -- docs/production_scheduling_wo_generation_delta.md §5 revisi: picklist Plan Delivery jadi modal,
// panel Master Card (vw_master_card), Bill of Process/Material jadi grid dblclick-to-edit, dan layar production/process-rate.
// Tiap tes membangun Inquiry, CO, Plan Delivery dan WO sendiri lewat WoFixture (dihapus di tearDown).
class ProductionWoDetailRebuildTest extends TestCase
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

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    public function test_priority_updates_and_persists(): void
    {
        $this->actingAs($this->admin());
        $wo = $this->seedWo('DET1');
        $this->assertSame('NORMAL', $wo->priority);

        Livewire::test('pages::production.wo-entry', ['wo' => $wo->wo_id])
            ->call('updatePriority', 'SUPER_URGENT');

        $this->assertSame('SUPER_URGENT', $wo->fresh()->priority);
    }

    public function test_operator_dblclick_edit_assigns_and_clears_crew(): void
    {
        $this->actingAs($this->admin());
        $wo = $this->seedWo('DET2');
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
        $wo = $this->seedWo('DET3');
        $op = $wo->operations()->firstOrFail();

        Livewire::test('pages::production.wo-entry', ['wo' => $wo->wo_id])
            ->call('updateOperationDateTime', $op->operation_id, 'target_start', '2026-09-05T07:30');

        $this->assertSame('2026-09-05 07:30:00', $op->fresh()->target_start->format('Y-m-d H:i:s'));
    }

    public function test_master_card_tab_renders_from_vw_master_card(): void
    {
        $this->actingAs($this->admin());
        $wo = $this->seedWo('DET4');
        $card = DB::connection('tm2mst')->table('vw_master_card')->where('id_product', $wo->product_id)->first();
        $this->assertNotNull($card);

        $test = Livewire::test('pages::production.wo-entry', ['wo' => $wo->wo_id])
            ->assertSee('Master Card / Kartu Induk');
        if ($card->kode_item) {
            $test->assertSee($card->kode_item);
        }
    }

    public function test_bill_of_material_rows_include_live_warehouse_stock(): void
    {
        $this->actingAs($this->admin());
        $wo = $this->seedWo('DET5');

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
        $wo = $this->seedWo('DET6');
        $chain = $this->seedChain('DET6B');

        $test = Livewire::test('pages::production.wo-entry', ['wo' => $wo->wo_id])
            ->call('openDeliveryModal')
            ->assertSee($chain['co_no']);

        $test->call('pickPlanDelivery', $chain['delivery_id']);
        $newWo = PrdWo::where('source_delivery_id', $chain['delivery_id'])->first();
        $this->assertNotNull($newWo);
        $this->assertSame($chain['order_id'], (int) $newWo->source_co_id);
        $test->assertRedirect(route('production.wo.entry', ['wo' => $newWo->wo_id]));
    }

    public function test_process_rate_management_screen_crud(): void
    {
        $this->actingAs($this->admin());

        $product = ProdProduct::firstOrFail();
        $process = ProdProcess::firstOrFail();
        $machine = ProdMachine::firstOrFail();

        $this->get('/v2/s/production-process-rate')->assertOk();
        $grid = new MachineRateLookup;
        $grid->save([['product_id' => $product->id_product, 'part_code' => 'RATETEST', 'process_id' => $process->id_process, 'machine_id' => $machine->machine_id, 'target_qty_per_hour' => 999, 'activate' => true]]);

        $rate = ProdPartProcessMachineRate::where('part_code', 'RATETEST')->first();
        $this->assertNotNull($rate);
        $this->assertEquals(999, (float) $rate->target_qty_per_hour);

        $grid->save([['id' => $rate->rate_id, 'product_id' => $product->id_product, 'part_code' => 'RATETEST', 'process_id' => $process->id_process, 'machine_id' => $machine->machine_id, 'target_qty_per_hour' => 999, 'activate' => false]]);
        $rate->refresh();
        $this->assertFalse($rate->activate);

        try {
            $grid->save([['product_id' => $product->id_product, 'part_code' => 'RATETEST2', 'process_id' => $process->id_process, 'machine_id' => $machine->machine_id, 'target_qty_per_hour' => 0]]);
            $this->fail('target 0 harus ditolak');
        } catch (\Illuminate\Validation\ValidationException) {
            $this->assertNull(ProdPartProcessMachineRate::where('part_code', 'RATETEST2')->first());
        }

        $rate->delete();
    }
}
