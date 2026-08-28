<?php

namespace Tests\Feature;

use App\Models\Sdi\IqpInquiry;
use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class InquiryToolingCostSmokeTest extends TestCase
{
    protected ?IqpInquiry $inquiry = null;

    protected function tearDown(): void
    {
        if ($this->inquiry) {
            DB::connection('tm2sdi')->table('iqp_tooling')->where('inquiry_id', $this->inquiry->id_inquiry)->delete();
            DB::connection('tm2sdi')->table('iqp_external_cost')->where('inquiry_id', $this->inquiry->id_inquiry)->delete();
            DB::connection('tm2sdi')->table('iqp_part_material')
                ->whereIn('part_id', DB::connection('tm2sdi')->table('iqp_part')->where('inquiry_id', $this->inquiry->id_inquiry)->pluck('id_part'))
                ->delete();
            DB::connection('tm2sdi')->table('iqp_part')->where('inquiry_id', $this->inquiry->id_inquiry)->delete();
            DB::connection('tm2sdi')->table('iqp_tier')->where('inquiry_id', $this->inquiry->id_inquiry)->delete();
            DB::connection('tm2sdi')->table('iqp_inquiry')->where('id_inquiry', $this->inquiry->id_inquiry)->delete();
        }

        parent::tearDown();
    }

    public function test_form_saves_tooling_discount_and_external_cost(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test('pages::inquiry.form', ['mode' => 'NEW'])
            ->set('inquiry_date', now()->toDateString())
            ->set('product_draft_name', 'Box Test Tooling')
            ->call('setProspect', true)
            ->set('prospect_name', 'Prospek Test Tooling')
            ->set('tiers.0.qty', 1000)
            ->set('parts.0.part_name', 'Body')
            ->call('addTooling')
            ->set('toolings.0.tooling_kind', 'PLATE')
            ->call('setToolingNewCharge', 0, true)
            ->set('toolings.0.cost', 2400000)
            ->set('toolings.0.remark', 'CTP baru')
            ->call('setDiscountDocType', 'PERCENT')
            ->set('discount_doc_value', 2.5)
            ->set('discount_qty_value', 3)
            ->set('discount_qty_min_ton', 5)
            ->call('addExternalCost')
            ->set('externalCosts.0.description', 'Trucking ke gudang customer')
            ->set('externalCosts.0.cost_kind', 'TRUCKING')
            ->set('externalCosts.0.amount', 350000)
            ->call('save')
            ->assertHasNoErrors();

        $this->inquiry = IqpInquiry::where('product_draft_name', 'Box Test Tooling')->latest('id_inquiry')->firstOrFail();

        $this->assertSame('PERCENT', $this->inquiry->discount_doc_type);
        $this->assertEquals(2.5, $this->inquiry->discount_doc_value);
        $this->assertEquals(3, $this->inquiry->discount_qty_value);
        $this->assertEquals(5, $this->inquiry->discount_qty_min_ton);

        $tooling = $this->inquiry->toolings()->firstOrFail();
        $this->assertSame('PLATE', $tooling->tooling_kind);
        $this->assertTrue((bool) $tooling->is_new_charge);
        $this->assertEquals(2400000, $tooling->cost);
        $this->assertSame('CTP baru', $tooling->remark);

        $ext = $this->inquiry->externalCosts()->firstOrFail();
        $this->assertSame('TRUCKING', $ext->cost_kind);
        $this->assertEquals(350000, $ext->amount);
        $this->assertSame('TAXABLE', $ext->tax_treatment);

        // Reload di layar edit: baris tooling/external cost harus ter-map balik.
        Livewire::test('pages::inquiry.form', ['id' => $this->inquiry->id_inquiry, 'mode' => 'NEW'])
            ->assertSet('toolings.0.tooling_kind', 'PLATE')
            ->assertSet('toolings.0.cost', '2400000')
            ->assertSet('externalCosts.0.description', 'Trucking ke gudang customer')
            ->assertSet('discount_doc_type', 'PERCENT');
    }
}
