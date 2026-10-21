<?php

namespace Tests\Feature;

use App\Models\Master\ProdLayoutGroup;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpCostingPart;
use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;
use App\Services\ProDev\ProdevWorkbenchService;
use Tests\TestCase;

// ProDev baru tidak punya unlock (workbench lama old_prodev diarsipkan ke ZIP 2026-10-21):
// layout CONFIRMED yang sudah ditarik Costing tidak boleh ikut berubah saat job
// disimpan lagi -- saveLayout() wajib bikin layout DRAFT baru.
class ProdevConfirmedLayoutImmutableTest extends TestCase
{
    protected ?int $productId = null;

    protected ?int $partId = null;

    protected ?int $costingPartId = null;

    protected function tearDown(): void
    {
        if ($this->costingPartId) {
            IqpCostingPart::whereKey($this->costingPartId)->delete();
        }
        if ($this->partId) {
            $mst = DB::connection('tm2mst');
            foreach (['prod_part_process', 'prod_part_material', 'prod_part_ink'] as $table) {
                $mst->table($table)->where('part_id', $this->partId)->delete();
            }
            ProdLayoutGroup::where('product_id', $this->productId)->get()->each(function ($g) {
                $g->parts()->detach();
                $g->delete();
            });
            ProdPart::whereKey($this->partId)->delete();
        }
        if ($this->productId) {
            ProdProduct::whereKey($this->productId)->delete();
        }

        parent::tearDown();
    }

    public function test_save_after_confirm_creates_new_draft_and_keeps_costing_layout_intact(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());

        $product = ProdProduct::create([
            'product_code' => 'TEST-PDV-IMMUT', 'product_name' => 'Test ProDev Immutable', 'activate' => true,
        ]);
        $this->productId = $product->id_product;
        $part = ProdPart::create([
            'product_id' => $product->id_product, 'part_code' => 'TEST-PDV-IMMUT-P', 'part_name' => 'Test ProDev Immutable Part',
            'cm_width' => 20, 'cm_length' => 15, 'flap_side' => 'TOP', 'activate' => true,
        ]);
        $this->partId = $part->id_part;

        $confirmed = ProdLayoutGroup::create([
            'product_id' => $product->id_product, 'plano_size_id' => 1, 'plano_w_mm' => 650, 'plano_h_mm' => 1000,
            'method' => 'TEGAK', 'up_count' => 4, 'efficiency_pct' => 60, 'waste_pct' => 40,
            'status' => 'CONFIRMED', 'confirmed_by' => 'test', 'confirmed_at' => now(),
            'created_by' => 'test', 'updated_by' => 'test',
        ]);
        $confirmed->update(['mastercard_code' => "MC-{$confirmed->id_layout_group}"]);
        $confirmed->parts()->attach($part->id_part, ['qty_up_in_group' => 4]);

        $costingPart = IqpCostingPart::create([
            'costing_id' => IqpCosting::firstOrFail()->id_costing, 'part_name' => 'Test ProDev Immutable Ref',
            'part_type_code' => 'DC', 'data_source' => 'PRODEV', 'prod_layout_group_id' => $confirmed->id_layout_group,
        ]);
        $this->costingPartId = $costingPart->id_costing_part;

        $state = ['partId' => $part->id_part, 'upCount' => 6, 'canvasL' => 70, 'canvasW' => 100, 'efficiencyPct' => 75, 'method' => 'AUTO_GRID'];
        (new ProdevWorkbenchService($part->id_part))->saveLayout($state);

        $confirmed->refresh();
        $this->assertSame('CONFIRMED', $confirmed->status);
        $this->assertSame(4, (int) $confirmed->up_count);
        $this->assertEqualsWithDelta(60, (float) $confirmed->efficiency_pct, 0.001);
        $this->assertSame($confirmed->id_layout_group, (int) $costingPart->refresh()->prod_layout_group_id);

        $draft = $part->refresh()->layoutGroups()->where('prod_layout_group.id_layout_group', '<>', $confirmed->id_layout_group)->first();
        $this->assertNotNull($draft, 'Simpan setelah Confirm harus bikin layout baru');
        $this->assertSame('DRAFT', $draft->status);
        $this->assertSame(6, (int) $draft->up_count);
    }
}
