<?php

namespace Tests\Feature;

use App\Models\Prd\PrdWoOperation;
use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sys\SecUser;
use App\Services\Scheduling\RateBandResolver;
use App\Services\Scheduling\WoGenerationService;
use Livewire\Livewire;
use Tests\Support\WoFixture;
use Tests\TestCase;

// Screen #1 (Generate dari Plan Delivery) + Screen #2 (Bill of Process interaktif) + Print WO --
// docs/production_scheduling_wo_generation_delta.md. Tiap tes membangun Inquiry, CO, Plan Delivery
// dan WO sendiri lewat WoFixture (dihapus di tearDown), tidak menumpang data dummy.
class ProductionWoGenerationTest extends TestCase
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

    public function test_generate_creates_wo_with_operations_and_correct_qty(): void
    {
        $chain = $this->seedChain('GEN1', 200);

        $created = app(WoGenerationService::class)->generateFromDelivery(CoOrderDelivery::findOrFail($chain['delivery_id']));
        $wo = $created->first();

        $this->assertCount(1, $created);
        $this->assertSame('WOFX', $wo->part_code);
        $this->assertEquals(200, (float) $wo->qty);
        $this->assertSame($chain['delivery_id'], $wo->source_delivery_id);
        $this->assertCount(3, $wo->operations);

        // Rate Cetak datang dari prod_part_process_machine_rate (6000/jam, fixture). Bila jadwal terisi
        // (semua task punya rate band), rate-nya wajib = RateBandResolver.
        $cetak = $wo->operations->firstWhere('process_name', 'Cetak');
        $this->assertEquals(6000, (float) $cetak->rate_band_value);
        if ($cetak->target_start) {
            $this->assertEquals(app(RateBandResolver::class)->resolve($cetak->machine_id, 'Cetak'), (float) $cetak->rate_band_value);
            $this->assertNotNull($cetak->target_finish);
        }
    }

    public function test_generate_guards_against_double_generation(): void
    {
        $wo = $this->seedWo('GEN2');

        $this->expectException(\RuntimeException::class);
        app(WoGenerationService::class)->generateFromDelivery(CoOrderDelivery::findOrFail($wo->source_delivery_id));
    }

    // Picklist Plan Delivery = modal di WO Detail (production.wo.new); delivery yang sudah melahirkan WO tak boleh muncul lagi.
    public function test_delivery_picklist_modal_excludes_already_generated_delivery(): void
    {
        $this->actingAs($this->admin());
        $wo = $this->seedWo('GEN3');
        $open = $this->seedChain('GEN3B');

        $html = Livewire::test('pages::production.wo-entry')->call('openDeliveryModal')->html();

        $this->assertStringNotContainsString('pickPlanDelivery('.$wo->source_delivery_id.')', $html);
        $this->assertStringContainsString('pickPlanDelivery('.$open['delivery_id'].')', $html);
    }

    public function test_screen2_bill_of_process_is_editable_while_draft(): void
    {
        $this->actingAs($this->admin());
        $wo = $this->seedWo('GEN4');
        $this->assertSame('DRAFT', $wo->wo_status);

        $test = Livewire::test('pages::production.wo-entry', ['wo' => $wo->wo_id])
            ->assertSee('Bill of Material')
            ->assertSee('Tambah Proses')
            ->call('addProcessRow');

        $newOp = PrdWoOperation::where('wo_id', $wo->wo_id)->orderByDesc('operation_id')->first();
        $test->call('updateOperationField', $newOp->operation_id, 'process_name', 'Cetak');
        $newOp->refresh();
        $this->assertSame('Cetak', $newOp->process_name);

        // Mesin OFF-02 punya rate 6000/jam untuk produk + bagian fixture ini.
        $test->call('updateOperationField', $newOp->operation_id, 'machine_id', $this->fxMachineId())
            ->call('refillTargetDefault', $newOp->operation_id);
        $newOp->refresh();
        $this->assertEquals(6000, (float) $newOp->rate_band_value);

        $test->call('removeProcessRow', $newOp->operation_id);
        $this->assertNull(PrdWoOperation::find($newOp->operation_id));
    }

    public function test_wo_print_form_renders_bop_and_bom(): void
    {
        $this->actingAs($this->admin());
        $wo = $this->seedWo('GEN5');

        $this->get(route('v2.production.wo.print', $wo->wo_id))->assertOk()
            ->assertSee($wo->wo_no)
            ->assertSee('Bill of Process')
            ->assertSee('Bill of Material');
        $this->get('/production/wo/'.$wo->wo_id.'/print')->assertRedirect(route('v2.production.wo.print', $wo->wo_id));
    }
}
