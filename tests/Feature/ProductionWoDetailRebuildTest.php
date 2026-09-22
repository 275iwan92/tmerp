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
    // Fixture Plan Delivery milik test ini sendiri. Sebelumnya test menumpang
    // baris hardcode (co_order#2 / iqp_inquiry#5) -- rapuh: sesi lain bisa
    // menggeser datanya, dan costing inquiry#5 masih DRAFT sehingga
    // WoGenerationService menolak generate WO (syarat "harga sudah final").
    // Marker tetap, supaya sisa run yang gagal di tengah bisa disapu ulang.
    private const FIXTURE_INQ_NO = 'IQTESTWO01';

    private const FIXTURE_CO_NO = 'COTESTWO01';

    private bool $planFixtureSeeded = false;

    protected function tearDown(): void
    {
        if ($this->planFixtureSeeded) {
            $this->purgePlanDeliveryFixture();
            $this->planFixtureSeeded = false;
        }

        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    /**
     * Inquiry (1 part, costing dipublish) + CO CONFIRMED + item + 1 Plan
     * Delivery PLANNED. Tanpa RefreshDatabase -- cleanup manual per tabel,
     * pola yang sama dgn tests/Feature/CostingEngineTest.php.
     *
     * @return array{inquiry_id: int, order_id: int, item_id: int, delivery_id: int}
     */
    private function seedPlanDeliveryFixture(): array
    {
        $this->purgePlanDeliveryFixture();

        $sdi = DB::connection('tm2sdi');
        $now = now();
        $productId = ProdProduct::firstOrFail()->id_product;

        $inquiryId = $sdi->table('iqp_inquiry')->insertGetId([
            'inquiry_no' => self::FIXTURE_INQ_NO,
            'inquiry_date' => $now->toDateString(),
            'is_prospect' => 1,
            'prospect_name' => 'Prospek Test WO Generation',
            'product_draft_name' => 'Box Test WO Generation',
            'inquiry_type' => 'NEW',
            'parent_product_id' => $productId,
            'sub_state' => 'CONVERTED',
            'created_by' => 'test', 'created_at' => $now, 'updated_at' => $now,
        ]);

        // 1 iqp_part = 1 prd_wo (production_scheduling_wo_generation_delta.md §2).
        $sdi->table('iqp_part')->insert([
            'inquiry_id' => $inquiryId, 'part_seq' => 1,
            'part_code' => 'WOTEST', 'part_name' => 'Body Test WO',
            'qty_per_sku' => 1,
            'created_by' => 'test', 'created_at' => $now, 'updated_at' => $now,
        ]);

        // Costing dipublish: WO hanya boleh lahir dari Inquiry yang harganya
        // sudah final (instruksi user 2026-09-18).
        $sdi->table('iqp_costing')->insert([
            'inquiry_id' => $inquiryId,
            'doc_status' => 'PUBLISHED', 'published_flag' => 1, 'is_approved' => 1,
            'approved_by' => 'test', 'approved_at' => $now,
            'created_by' => 'test', 'created_at' => $now, 'updated_at' => $now,
        ]);

        $orderId = $sdi->table('co_order')->insertGetId([
            'co_no' => self::FIXTURE_CO_NO, 'co_date' => $now->toDateString(),
            'doc_status' => 'CONFIRMED',
            'created_by' => 'test', 'created_at' => $now, 'updated_at' => $now,
        ]);

        $itemId = $sdi->table('co_order_item')->insertGetId([
            'order_id' => $orderId, 'line_seq' => 1, 'line_kind' => 'PRODUCT',
            'inquiry_id' => $inquiryId, 'product_id' => $productId,
            'description' => 'Box Test WO Generation', 'qty' => 500,
            'created_by' => 'test', 'created_at' => $now, 'updated_at' => $now,
        ]);

        $deliveryId = $sdi->table('co_order_delivery')->insertGetId([
            'order_id' => $orderId, 'item_id' => $itemId, 'shipment_type' => 'PARTIAL',
            'ship_date' => $now->copy()->addDays(30)->toDateString(), 'ship_qty' => 150,
            'status' => 'PLANNED',
            'created_by' => 'test', 'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->planFixtureSeeded = true;

        return [
            'inquiry_id' => (int) $inquiryId, 'order_id' => (int) $orderId,
            'item_id' => (int) $itemId, 'delivery_id' => (int) $deliveryId,
        ];
    }

    /** Hapus fixture + WO turunannya, anak dulu baru induk (FK dalam-DB tegak). */
    private function purgePlanDeliveryFixture(): void
    {
        $sdi = DB::connection('tm2sdi');
        $prd = DB::connection('tm2prd');

        $inquiryId = $sdi->table('iqp_inquiry')->where('inquiry_no', self::FIXTURE_INQ_NO)->value('id_inquiry');
        $orderId = $sdi->table('co_order')->where('co_no', self::FIXTURE_CO_NO)->value('id_order');

        if ($orderId) {
            $deliveryIds = $sdi->table('co_order_delivery')->where('order_id', $orderId)->pluck('id_delivery');

            if ($deliveryIds->isNotEmpty()) {
                $woIds = $prd->table('prd_wo')->whereIn('source_delivery_id', $deliveryIds)->pluck('wo_id');

                if ($woIds->isNotEmpty()) {
                    $prd->table('prd_wo_operation')->whereIn('wo_id', $woIds)->delete();
                    $prd->table('prd_wo_material')->whereIn('wo_id', $woIds)->delete();
                    $prd->table('prd_wo')->whereIn('wo_id', $woIds)->delete();
                }

                $sdi->table('co_order_delivery')->whereIn('id_delivery', $deliveryIds)->delete();
            }

            $sdi->table('co_order_item')->where('order_id', $orderId)->delete();
            $sdi->table('co_order')->where('id_order', $orderId)->delete();
        }

        if ($inquiryId) {
            $sdi->table('iqp_costing')->where('inquiry_id', $inquiryId)->delete();
            $sdi->table('iqp_part')->where('inquiry_id', $inquiryId)->delete();
            $sdi->table('iqp_inquiry')->where('id_inquiry', $inquiryId)->delete();
        }
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

        $fixture = $this->seedPlanDeliveryFixture();
        $deliveryId = $fixture['delivery_id'];

        $test = Livewire::test('pages::production.wo-entry', ['wo' => $wo->wo_id])
            ->call('openDeliveryModal')
            ->assertSee(self::FIXTURE_CO_NO);

        $test->call('pickPlanDelivery', $deliveryId);
        $newWo = PrdWo::where('source_delivery_id', $deliveryId)->first();
        $this->assertNotNull($newWo);
        $this->assertSame($fixture['order_id'], (int) $newWo->source_co_id);
        $test->assertRedirect(route('production.wo.entry', ['wo' => $newWo->wo_id]));
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
