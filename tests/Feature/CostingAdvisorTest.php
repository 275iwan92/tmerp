<?php

namespace Tests\Feature;

use App\Models\Master\MatGrammage;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpCostingPart;
use App\Models\Sdi\IqpCostingPriceTier;
use App\Models\Sdi\IqpCostingRecommendation;
use App\Models\Sys\SecUser;
use App\Services\Costing\CostingAdvisor;
use App\Services\Costing\CostingEngine;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
 * REMARK
 * Tujuan: menguji CostingAdvisor (isi otomatis, override, pemulihan isian kosong, statistik/ML, Laporan Hitung).
 * Sumber hulu: App\Services\Costing\CostingAdvisor, migration 2026_10_16_000500.
 * Input: costing standalone (tanpa inquiry) + part DC. Output: assert nilai, rekaman saran, laporan.
 * Dipakai oleh: php artisan test --filter=CostingAdvisorTest. Data uji dibersihkan sendiri di tearDown.
 * Status: BARU 2026-10-16.
 */
class CostingAdvisorTest extends TestCase
{
    private array $costingIds = [];

    protected function tearDown(): void
    {
        $sdi = DB::connection('tm2sdi');
        $partIds = $sdi->table('iqp_costing_part')->whereIn('costing_id', $this->costingIds)->pluck('id_costing_part');
        $sdi->table('iqp_costing_recommendation')->whereIn('costing_id', $this->costingIds)->delete();
        $sdi->table('iqp_costing_part_coating')->whereIn('costing_part_id', $partIds)->delete();
        $sdi->table('iqp_costing_part')->whereIn('costing_id', $this->costingIds)->delete();
        $sdi->table('iqp_costing_price_tier')->whereIn('costing_id', $this->costingIds)->delete();
        $sdi->table('iqp_costing')->whereIn('id_costing', $this->costingIds)->delete();

        parent::tearDown();
    }

    private function costing(string $partName = 'Kalender Test', int $qty = 1000, string $type = 'DC'): array
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $c = IqpCosting::create(['mode' => 'INSTANT', 'calc_type' => 'NEW', 'doc_status' => 'DRAFT', 'product_name_draft' => $partName, 'i_dim_panjang_mm' => 336, 'i_dim_lebar_mm' => 486, 'created_by' => 'test', 'updated_by' => 'test']);
        $this->costingIds[] = $c->id_costing;
        $t = IqpCostingPriceTier::create(['costing_id' => $c->id_costing, 'qty_pcs' => $qty, 'is_primary' => true, 'created_by' => 'test', 'updated_by' => 'test']);
        $p = IqpCostingPart::create(['costing_id' => $c->id_costing, 'no_urut' => 1, 'part_name' => $partName, 'part_type_code' => $type, 'data_source' => 'MANUAL', 'qty_per_set' => 1,
            'i_dim_panjang_mm' => 336, 'i_dim_lebar_mm' => 486, 'created_by' => 'test', 'updated_by' => 'test']);

        return [$c->fresh(), $t, $p];
    }

    public function test_prefill_fills_empty_boxes_with_margin_default_and_template_rates(): void
    {
        [$c, $t, $p] = $this->costing();
        (new CostingAdvisor)->prefill($c);

        $this->assertEquals(18.0, (float) $t->fresh()->margin_pct); // MARGIN_DEFAULT_PCT
        $this->assertEquals(5.0, (float) $t->fresh()->tolerance_pct); // TOLERANCE_DEFAULT_PCT (median tmhpp)
        $p = $p->fresh();
        $this->assertTrue((bool) MatGrammage::whereKey($p->grammage_id)->value('activate'), 'gramatur harus terisi dan aktif (template KALENDER: Duplex 250)');
        $this->assertEquals(5.3, (float) $p->i_dc_persen_override);
        $this->assertEquals(750000.0, (float) $p->i_ongkos_cetak_rim);   // template KALENDER (DC)
        $this->assertEquals(50.0, (float) $p->i_ongkos_qc_pack_pc);
        $this->assertEquals(0.25, (float) $p->i_film_faktor);            // konstanta, karena template menandai faktor 0 = default
        $this->assertGreaterThan(0, (float) $p->i_panjang_plano_cm);     // perkiraan geometri (layout ProDev tidak ada)
        $this->assertSame('ESTIMATE', IqpCostingRecommendation::where('costing_part_id', $p->id_costing_part)->where('field_key', 'i_isi_per_lb')->value('source'));
        $this->assertSame('TEMPLATE', IqpCostingRecommendation::where('costing_part_id', $p->id_costing_part)->where('field_key', 'i_ongkos_cetak_rim')->value('source'));
    }

    public function test_officer_override_and_zero_are_never_overwritten(): void
    {
        [$c, $t, $p] = $this->costing();
        $advisor = new CostingAdvisor;
        $advisor->prefill($c);

        // officer mengubah margin 18 -> 22 dan sengaja menolkan Ongkos Lem
        $t->update(['margin_pct' => 22]);
        $advisor->markTouched($c->id_costing, null, $t->id_price_tier, 'margin_pct', 22, 'DC');
        $p->update(['i_ongkos_cetak_rim' => 0]);
        $advisor->markTouched($c->id_costing, $p->id_costing_part, null, 'i_ongkos_cetak_rim', 0, 'DC');

        $advisor->prefill($c->fresh());
        $this->assertEquals(22.0, (float) $t->fresh()->margin_pct);
        $this->assertEquals(0.0, (float) $p->fresh()->i_ongkos_cetak_rim);
        $this->assertNull($advisor->valueToRestore($c->id_costing, $p->id_costing_part, null, 'i_ongkos_cetak_rim')); // 0 = sengaja, tidak dipulihkan
    }

    public function test_blank_box_is_restored_to_suggested_value(): void
    {
        [$c, , $p] = $this->costing();
        $advisor = new CostingAdvisor;
        $advisor->prefill($c);

        $this->assertEquals(750000.0, $advisor->valueToRestore($c->id_costing, $p->id_costing_part, null, 'i_ongkos_cetak_rim'));
        $this->assertNull($advisor->valueToRestore($c->id_costing, $p->id_costing_part, null, 'i_ongkos_hotprint_pc')); // template 0: tidak ada nilai saran
    }

    public function test_statistics_replace_default_after_enough_history_then_ml_threshold(): void
    {
        $advisor = new CostingAdvisor;
        // 5 costing lama dengan margin akhir 20, 22, 24, 26, 28 (median 24) -> statistik
        foreach ([20, 22, 24, 26, 28] as $m) {
            [$old, $ot] = $this->costing('Hist '.$m, 1000, 'KRAFT');
            $ot->update(['margin_pct' => $m]);
            $advisor->markTouched($old->id_costing, null, $ot->id_price_tier, 'margin_pct', $m, 'KRAFT');
            $advisor->learn($old->fresh());
        }
        [$c, $t] = $this->costing('Baru', 1000, 'KRAFT');
        $advisor->prefill($c);

        $this->assertEquals(24.0, (float) $t->fresh()->margin_pct);
        $rec = IqpCostingRecommendation::where('costing_id', $c->id_costing)->where('field_key', 'margin_pct')->first();
        $this->assertSame('STAT', $rec->source);
        $this->assertSame(5, (int) $rec->sample_count);
    }

    public function test_report_trace_equals_engine_result_and_has_notes(): void
    {
        [$c, $t, $p] = $this->costing();
        $advisor = new CostingAdvisor;
        $advisor->prefill($c);
        $p->update(['i_paper_price_override' => 300000]);
        (new CostingEngine)->run($c->fresh());
        $advisor->learn($c->fresh());

        $report = $advisor->report($c->fresh(), $t->fresh());
        $this->assertTrue($report['ok'], 'penelusuran harus sama dengan hasil CostingEngine');
        $this->assertEqualsWithDelta((float) $t->fresh()->c_after_ppn_pc, $report['steps'][array_key_last($report['steps'])][1], 0.01);
        $this->assertNotEmpty($report['sim']);
        $this->assertTrue(collect($report['notes'])->contains(fn ($n) => str_contains($n[2], 'perkiraan'))); // geometri perkiraan
        $this->assertGreaterThan(0, $report['learned']);
        $this->assertIsArray($advisor->missing($p->fresh()));
    }
}
