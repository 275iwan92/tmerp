<?php

namespace Tests\Feature;

use App\Models\Master\ProdMachine;
use App\Models\Master\ProdPartProcessMachineRate;
use App\Models\Master\ProdProcess;
use App\Models\Master\ProdProduct;
use App\Models\Sys\SecUser;
use App\Services\Production\WoMaterialSubstitutionService;
use App\Support\V2\Grid\Prod\MachineRateLookup;
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

    // Isi Bill of Material (kebutuhan, stok gudang live, harus dibeli) kini dari WoMaterialSubstitutionService::rows (pindahan billOfMaterialRows v1).
    public function test_bill_of_material_rows_include_live_warehouse_stock(): void
    {
        $this->actingAs($this->admin());
        $wo = $this->seedWo('DET5');

        $rows = app(WoMaterialSubstitutionService::class)->rows($wo);

        $this->assertGreaterThan(0, $rows->count());
        $row = $rows->first();
        $this->assertSame(max(0, $row->need - $row->have), $row->to_buy);
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
