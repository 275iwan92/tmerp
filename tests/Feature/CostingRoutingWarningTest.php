<?php

namespace Tests\Feature;

use App\Models\Master\ProdPart;
use App\Models\Master\ProdPartProcess;
use App\Models\Master\ProdProcess;
use App\Models\Master\ProdProduct;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpCostingPart;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sys\SecUser;
use App\Services\Costing\CostingEntryService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

// BACKLOG.md §3 "ProCost belum konsumsi urutan proses" -- costing.blade.php
// kini validasi silang i_ongkos_cetak_rim/plong_lb/lem_pc (DC) terhadap
// prod_part_process (routing ProDev sungguhan, bukan rate yang ditebak --
// lihat komentar DC_PROCESS_FIELD_MAP di costing.blade.php soal kenapa tidak
// auto-hitung angka).
class CostingRoutingWarningTest extends TestCase
{
    protected ?IqpInquiry $inquiry = null;

    protected ?int $prodPartId = null;

    protected ?int $productId = null;

    protected function tearDown(): void
    {
        if ($this->prodPartId) {
            ProdPartProcess::where('part_id', $this->prodPartId)->delete();
            ProdPart::whereKey($this->prodPartId)->delete();
        }
        if ($this->productId) {
            ProdProduct::whereKey($this->productId)->delete();
        }
        if ($this->inquiry) {
            $costingIds = DB::connection('tm2sdi')->table('iqp_costing')->where('inquiry_id', $this->inquiry->id_inquiry)->pluck('id_costing');
            DB::connection('tm2sdi')->table('iqp_costing_part')->whereIn('costing_id', $costingIds)->delete();
            DB::connection('tm2sdi')->table('iqp_costing_price_tier')->whereIn('costing_id', $costingIds)->delete();
            DB::connection('tm2sdi')->table('iqp_costing')->where('inquiry_id', $this->inquiry->id_inquiry)->delete();
            DB::connection('tm2sdi')->table('iqp_part')->where('inquiry_id', $this->inquiry->id_inquiry)->delete();
            DB::connection('tm2sdi')->table('iqp_tier')->where('inquiry_id', $this->inquiry->id_inquiry)->delete();
            DB::connection('tm2sdi')->table('iqp_inquiry')->where('id_inquiry', $this->inquiry->id_inquiry)->delete();
        }

        parent::tearDown();
    }

    public function test_warns_when_routing_step_exists_but_rate_field_empty_and_clears_after_fill(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $cetak = ProdProcess::where('process_name', 'CETAK')->firstOrFail();

        $product = ProdProduct::create(['product_code' => 'TEST-ROUTING-WARN', 'product_name' => 'Test Routing Warn', 'activate' => true]);
        $this->productId = $product->id_product;

        $part = ProdPart::create([
            'product_id' => $product->id_product, 'part_code' => 'TEST-ROUTING-PART', 'part_name' => 'Test Routing Part',
            'cm_width' => 20, 'cm_length' => 15, 'flap_side' => 'TOP', 'activate' => true,
        ]);
        $this->prodPartId = $part->id_part;

        ProdPartProcess::create([
            'part_id' => $part->id_part, 'branch_code' => 'MAIN', 'step_seq' => 10, 'flow_type' => 'LINEAR',
            'process_id' => $cetak->id_process, 'created_by' => 'test',
        ]);

        Livewire::test('pages::inquiry.form', ['mode' => 'NEW'])
            ->set('inquiry_date', now()->toDateString())
            ->set('product_draft_name', 'Test Routing Warn Inquiry')
            ->call('setProspect', true)
            ->set('prospect_name', 'Prospek Test Routing')
            ->set('tiers.0.qty', 1000)
            ->set('parts.0.part_name', 'Body')
            ->call('save')
            ->assertHasNoErrors();

        $this->inquiry = IqpInquiry::where('product_draft_name', 'Test Routing Warn Inquiry')->latest('id_inquiry')->firstOrFail();

        CostingEntryService::open($this->inquiry->id_inquiry);
        $costing = IqpCosting::where('inquiry_id', $this->inquiry->id_inquiry)->firstOrFail();

        $costingPart = IqpCostingPart::create([
            'costing_id' => $costing->id_costing, 'part_name' => 'Body', 'part_type_code' => 'DC',
            'data_source' => 'PRODEV', 'prodev_part_id' => $part->id_part,
            'i_ongkos_cetak_rim' => 0, 'i_ongkos_plong_lb' => 0, 'i_ongkos_lem_pc' => 0,
        ]);

        $names = CostingEntryService::routingNamesFor($costingPart);
        $this->assertTrue($names->contains('CETAK'));

        $warnings = CostingEntryService::routingWarningsFor($costingPart);
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('CETAK', $warnings[0]);
        $this->assertStringContainsString('Ongkos Cetak', $warnings[0]);

        // Isi rate cetak -- warning utk CETAK harus hilang.
        $costingPart->update(['i_ongkos_cetak_rim' => 200000]);
        $warningsAfterFill = CostingEntryService::routingWarningsFor($costingPart->refresh());
        $this->assertEmpty(array_filter($warningsAfterFill, fn ($w) => str_contains($w, 'CETAK')));

        // Sebaliknya: PLONG diisi manual tapi TIDAK ada di routing -> warning kebalikan.
        $costingPart->update(['i_ongkos_plong_lb' => 5000]);
        $warningsMismatch = CostingEntryService::routingWarningsFor($costingPart->refresh());
        $this->assertNotEmpty(array_filter($warningsMismatch, fn ($w) => str_contains($w, 'PLONG')));
    }
}
