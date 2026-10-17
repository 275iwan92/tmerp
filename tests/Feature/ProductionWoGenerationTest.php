<?php

namespace Tests\Feature;

use App\Models\Master\ProdMachine;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoOperation;
use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sys\SecUser;
use App\Services\Scheduling\RateBandResolver;
use App\Services\Scheduling\WoGenerationService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

// Screen #1 (Generate dari Plan Delivery) + Screen #2 (Bill of Process
// interaktif) + Screen #3 (Monitoring Kartu) + Print WO --
// docs/production_scheduling_wo_generation_delta.md. Dummy chain (CO2600001
// order_id=2 / inquiry #5 / part #1 "BODY") disiapkan migration
// 2026_08_28_000018_seed_wo_generation_dummy_chain.php -- SUDAH menghasilkan
// WO2600261 (dipakai test read-only di sini). Test yang butuh delivery BARU
// (double-generate guard, qty calc) bikin+hapus baris co_order_delivery
// sendiri supaya tak bentrok dgn WO2600261 yang dipakai test lain.
class ProductionWoGenerationTest extends TestCase
{
    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function seededWo(): PrdWo
    {
        return PrdWo::where('wo_no', 'WO2600261')->firstOrFail();
    }

    public function test_generate_creates_wo_with_operations_and_correct_qty(): void
    {
        $itemId = DB::connection('tm2sdi')->table('co_order_item')
            ->where('order_id', 2)->where('inquiry_id', 5)->value('id_item');

        $deliveryId = DB::connection('tm2sdi')->table('co_order_delivery')->insertGetId([
            'order_id' => 2, 'item_id' => $itemId, 'shipment_type' => 'PARTIAL',
            'ship_date' => now()->addDays(20)->toDateString(), 'ship_qty' => 200, 'status' => 'PLANNED',
            'created_by' => 'test', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Guard 2026-09-18: WO hanya lahir dari Inquiry yang costing-nya dipublish. Costing
        // dummy IQ2600001 masih DRAFT -- dipublish sementara, dikembalikan persis di akhir.
        $costing = DB::connection('tm2sdi')->table('iqp_costing')->where('inquiry_id', 5)
            ->first(['id_costing', 'published_flag']);
        DB::connection('tm2sdi')->table('iqp_costing')->where('id_costing', $costing->id_costing)->update(['published_flag' => 1]);

        $wo = null;

        try {
            $created = app(WoGenerationService::class)->generateFromDelivery(CoOrderDelivery::findOrFail($deliveryId));
            $wo = $created->first();

            $this->assertCount(1, $created);
            $this->assertSame('BODY', $wo->part_code);
            $this->assertEquals(200, (float) $wo->qty);
            $this->assertSame($deliveryId, $wo->source_delivery_id);
            $this->assertCount(3, $wo->operations);

            // Backward-schedule hanya dipersist bila SEMUA task punya rate band. Sejak Master
            // Mesin dibersihkan (2026-09-22/23) routing dummy IQ2600001 Laminasi & Plong tak
            // bermesin -> resolver melempar & generate sengaja tetap jalan (catch di
            // WoGenerationService), jadi rate Cetak tetap bootstrap. Bila jadwal terisi,
            // rate-nya wajib = RateBandResolver (bukan bootstrap).
            $cetak = $wo->operations->firstWhere('process_name', 'Cetak');
            $this->assertGreaterThan(0, (float) $cetak->rate_band_value);
            if ($cetak->target_start) {
                $this->assertEquals(app(RateBandResolver::class)->resolve($cetak->machine_id, 'Cetak'), (float) $cetak->rate_band_value);
                $this->assertNotNull($cetak->target_finish);
            }
        } finally {
            DB::connection('tm2sdi')->table('iqp_costing')->where('id_costing', $costing->id_costing)->update(['published_flag' => $costing->published_flag]);
            if ($wo) {
                DB::connection('tm2prd')->table('prd_wo_material')->where('wo_id', $wo->wo_id)->delete(); // generate() writes the BOM rows too
                $wo->operations()->delete();
                $wo->delete();
            }
            DB::connection('tm2sdi')->table('co_order_delivery')->where('id_delivery', $deliveryId)->delete();
        }
    }

    public function test_generate_guards_against_double_generation(): void
    {
        $wo = $this->seededWo();

        $this->expectException(\RuntimeException::class);
        app(WoGenerationService::class)->generateFromDelivery(
            CoOrderDelivery::findOrFail($wo->source_delivery_id)
        );
    }

    // Screen #1 (picklist berdiri sendiri, wo-generate.blade.php) DIHAPUS
    // 2026-08-28 -- fungsinya digabung jadi modal dalam WO Detail
    // (production.wo.new / tombol "Pilih Plan Delivery Lain"), lihat
    // ProductionWoDetailRebuildTest::test_plan_delivery_modal_generates_wo_and_redirects.
    public function test_delivery_picklist_modal_excludes_already_generated_delivery(): void
    {
        $this->actingAs($this->admin());
        $wo = $this->seededWo();

        // delivery id yg SUDAH generate WO tak boleh muncul lagi di modal,
        // meski baris co_order_delivery LAIN utk CO yang sama masih eligible
        // (migration 000021 sengaja nambah 1 partial shipment lagi utk demo
        // picklist tak kosong -- jadi assert per delivery_id, bukan per no. CO).
        $test = Livewire::test('pages::production.wo-entry')->call('openDeliveryModal');
        $html = $test->html();
        $this->assertStringNotContainsString('pickPlanDelivery('.$wo->source_delivery_id.')', $html);
    }

    public function test_screen2_bill_of_process_is_editable_while_draft(): void
    {
        $this->actingAs($this->admin());
        $wo = $this->seededWo();
        $this->assertSame('DRAFT', $wo->wo_status);

        $test = Livewire::test('pages::production.wo-entry', ['wo' => $wo->wo_id])
            ->assertSee('Bill of Material')
            ->assertSee('Tambah Proses')
            ->call('addProcessRow');

        $newOp = PrdWoOperation::where('wo_id', $wo->wo_id)->orderByDesc('operation_id')->first();
        $test->call('updateOperationField', $newOp->operation_id, 'process_name', 'Cetak');
        $newOp->refresh();
        $this->assertSame('Cetak', $newOp->process_name);

        // CETAK-01 sudah tidak ada: kode mesin dibakukan (KOMORI-GL440 sejak
        // 2026-09-17, lalu OFF-02 sejak daftar aset 2026-09-21). Mesin yang
        // punya baris prod_part_process_machine_rate = machine_id 1 yang sama.
        $machineId = ProdMachine::where('machine_code', 'OFF-02')->value('machine_id');
        $test->call('updateOperationField', $newOp->operation_id, 'machine_id', $machineId)
            ->call('refillTargetDefault', $newOp->operation_id);
        $newOp->refresh();
        $this->assertEquals(6000, (float) $newOp->rate_band_value);

        $test->call('removeProcessRow', $newOp->operation_id);
        $this->assertNull(PrdWoOperation::find($newOp->operation_id));
    }

    public function test_wo_print_form_renders_bop_and_bom(): void
    {
        $this->actingAs($this->admin());
        $wo = $this->seededWo();

        Livewire::test('pages::production.wo-print', ['wo' => $wo->wo_id])
            ->assertSee($wo->wo_no)
            ->assertSee('Bill of Process')
            ->assertSee('Bill of Material');
    }
}
