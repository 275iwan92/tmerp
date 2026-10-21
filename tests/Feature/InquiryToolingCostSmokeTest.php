<?php

namespace Tests\Feature;

use App\Models\Sdi\IqpInquiry;
use App\Models\Sys\SecUser;
use App\Support\V2\Data\Sales;
use Illuminate\Support\Facades\DB;
use Tests\Support\InquiryDriver;
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

        // Layar v2 belum punya isian diskon dokumen (dipertahankan dari dokumen lama), jadi diskon diset di payload; penulisnya tetap InquiryWriter.
        $this->inquiry = InquiryDriver::create('Box Test Tooling', [
            'toolings' => [['tooling_kind' => 'PLATE', 'tooling_status' => 'ADA', 'is_new_charge' => 1, 'cost' => 2400000, 'remark' => 'CTP baru']],
            'external_costs' => [['description' => 'Trucking ke gudang customer', 'cost_kind' => 'TRUCKING', 'amount' => 350000, 'tax_treatment' => 'TAXABLE']],
        ], function (array $p) {
            $p['header'] = ['discount_doc_type' => 'PERCENT', 'discount_doc_value' => '2.5', 'discount_qty_value' => '3', 'discount_qty_min_ton' => '5'] + $p['header'];

            return $p;
        });

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
        $doc = Sales::inquiryDoc($this->inquiry->id_inquiry);
        $this->assertSame('PLATE', $doc['toolings'][0]['tooling_kind']);
        $this->assertEquals(2400000, $doc['toolings'][0]['cost']);
        $this->assertSame('Trucking ke gudang customer', $doc['external_costs'][0]['description']);
    }
}
