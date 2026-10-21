<?php

namespace Tests\Feature;

use App\Models\Master\MatGrammage;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\ProCostDriver;
use Tests\TestCase;

/*
 * REMARK
 * Tujuan: menguji layar ProCost (pages::inquiry.costing) dengan ENGINE+ML: kotak terisi otomatis, isian kosong dipulihkan,
 *         "Memang tidak dipakai", Hitung mencatat bahan belajar dan Laporan Hitung tampil.
 * Sumber hulu: pages::inquiry.costing, App\Services\Costing\CostingAdvisor.
 * Input: inquiry dibuat lewat layar form (pola CostingEngineTest). Output: assert nilai kolom dan rekaman saran.
 * Dipakai oleh: php artisan test --filter=CostingAdvisorScreenTest. Data uji dibersihkan sendiri di tearDown.
 * Status: BARU 2026-10-16.
 */
class CostingAdvisorScreenTest extends TestCase
{
    protected ?IqpInquiry $inquiry = null;

    protected function tearDown(): void
    {
        if ($this->inquiry) {
            $sdi = DB::connection('tm2sdi');
            $costingIds = $sdi->table('iqp_costing')->where('inquiry_id', $this->inquiry->id_inquiry)->pluck('id_costing');
            $partIds = $sdi->table('iqp_costing_part')->whereIn('costing_id', $costingIds)->pluck('id_costing_part');
            $sdi->table('iqp_costing_recommendation')->whereIn('costing_id', $costingIds)->delete();
            $sdi->table('iqp_costing_part_coating')->whereIn('costing_part_id', $partIds)->delete();
            $sdi->table('iqp_costing_part')->whereIn('costing_id', $costingIds)->delete();
            $sdi->table('iqp_costing_price_tier')->whereIn('costing_id', $costingIds)->delete();
            $sdi->table('iqp_costing')->where('inquiry_id', $this->inquiry->id_inquiry)->delete();
            $sdi->table('iqp_part')->where('inquiry_id', $this->inquiry->id_inquiry)->delete();
            $sdi->table('iqp_tier')->where('inquiry_id', $this->inquiry->id_inquiry)->delete();
            $sdi->table('iqp_inquiry')->where('id_inquiry', $this->inquiry->id_inquiry)->delete();
        }

        parent::tearDown();
    }

    public function test_screen_prefills_restores_blank_accepts_zero_and_hitung_learns(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());

        Livewire::test('pages::inquiry.form', ['mode' => 'NEW'])
            ->set('inquiry_date', now()->toDateString())
            ->set('product_draft_name', 'Kalender Test Advisor')
            ->call('setProspect', true)
            ->set('prospect_name', 'Prospek Test Advisor')
            ->set('tiers.0.qty', 1000)
            ->set('parts.0.part_name', 'Body')
            ->call('save')
            ->assertHasNoErrors();
        $this->inquiry = IqpInquiry::where('product_draft_name', 'Kalender Test Advisor')->latest('id_inquiry')->firstOrFail();

        $component = ProCostDriver::open($this->inquiry->id_inquiry);
        $costing = IqpCosting::where('inquiry_id', $this->inquiry->id_inquiry)->firstOrFail();

        // 1) dokumen DRAFT dibuka: margin default 18% terisi di semua tier
        $this->assertTrue($costing->priceTiers()->get()->every(fn ($t) => (float) $t->margin_pct === 18.0));

        // 2) mode manual: nama part diketik -> template terdekat mengisi tarif
        $component->call('addPart');
        $part = $costing->parts()->firstOrFail();
        $component->call('updatePart', $part->id_costing_part, 'part_name', 'Kalender Majoin');
        $this->assertEquals(750000.0, (float) $part->fresh()->i_ongkos_cetak_rim);

        // 3) isian dikosongkan tidak sengaja -> dipulihkan + notifikasi
        $component->call('updatePart', $part->id_costing_part, 'i_ongkos_cetak_rim', '')
            ->assertSet('notice.field', 'i_ongkos_cetak_rim');
        $this->assertEquals(750000.0, (float) $part->fresh()->i_ongkos_cetak_rim);

        // 4) "Memang tidak dipakai" -> 0, tidak dipulihkan lagi
        $component->call('acceptZero');
        $this->assertEquals(0.0, (float) $part->fresh()->i_ongkos_cetak_rim);
        $component->call('updatePart', $part->id_costing_part, 'i_ongkos_cetak_rim', '')->assertSet('notice', []);
        $this->assertEquals(0.0, (float) $part->fresh()->i_ongkos_cetak_rim);

        // 5) Hitung mencatat isian final; Laporan Hitung tampil untuk login berhak
        $grammage = MatGrammage::where('grammage_name', 'Duplex 300gsm')->firstOrFail();
        $component->call('updatePart', $part->id_costing_part, 'grammage_id', $grammage->id_grammage)->call('hitung');
        $this->assertSame('', $component->get('engineError'));
        $this->assertGreaterThan(0, DB::connection('tm2sdi')->table('iqp_costing_recommendation')->where('costing_id', $costing->id_costing)->whereNotNull('learned_at')->count());
        // Laporan Hitung (accordion di tab Result layar v2) hanya untuk login berhak (PROCOST_FORMULA_LOGINS): admin uji johiwan.
        $html = $this->get('/v2/s/procost?inquiry='.$this->inquiry->id_inquiry)->assertOk()->getContent();
        $this->assertStringContainsString('Calculation report', $html);
        $this->assertStringContainsString('Price breakdown', $html);
    }
}
