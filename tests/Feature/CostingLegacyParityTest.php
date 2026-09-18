<?php

namespace Tests\Feature;

use App\Models\Master\MatGrammage;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpCostingPart;
use App\Models\Sdi\IqpCostingPartCoating;
use App\Models\Sdi\IqpCostingPriceTier;
use App\Services\Costing\CostingEngine;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Paritas engine vs data asli program HPP lama (docs/ddl/tmhpp.sql, hpp_parts).
// Angka target = kolom c_* legacy apa adanya. Lihat procost_formula_library.md §VII.4.
class CostingLegacyParityTest extends TestCase
{
    private array $costingIds = [];

    protected function tearDown(): void
    {
        $sdi = DB::connection('tm2sdi');
        $partIds = $sdi->table('iqp_costing_part')->whereIn('costing_id', $this->costingIds)->pluck('id_costing_part');
        $sdi->table('iqp_costing_part_coating')->whereIn('costing_part_id', $partIds)->delete();
        $sdi->table('iqp_costing_part')->whereIn('costing_id', $this->costingIds)->delete();
        $sdi->table('iqp_costing_price_tier')->whereIn('costing_id', $this->costingIds)->delete();
        $sdi->table('iqp_costing')->whereIn('id_costing', $this->costingIds)->delete();

        parent::tearDown();
    }

    private function grammage(int $gsm): int
    {
        return (int) MatGrammage::where('paper_kind', 'DUPLEX')->where('grammage_value', $gsm)->where('activate', true)->value('id_grammage');
    }

    /** @return array{0: IqpCosting, 1: IqpCostingPart, 2: IqpCostingPriceTier} */
    private function run1(string $type, float $qty, array $part, array $coatings = []): array
    {
        $costing = IqpCosting::create(['mode' => 'INSTANT', 'calc_type' => 'NEW', 'doc_status' => 'DRAFT', 'ppn_mode' => 'PPN', 'ppn_pct' => 11, 'created_by' => 'test']);
        $this->costingIds[] = $costing->id_costing;
        $tier = IqpCostingPriceTier::create(['costing_id' => $costing->id_costing, 'qty_pcs' => $qty, 'is_primary' => true, 'created_by' => 'test']);
        $p = IqpCostingPart::create($part + ['costing_id' => $costing->id_costing, 'no_urut' => 1, 'part_name' => 'T', 'part_type_code' => $type, 'qty_per_set' => 1, 'created_by' => 'test']);
        foreach ($coatings as $i => [$typeName, $index]) {
            IqpCostingPartCoating::create(['costing_part_id' => $p->id_costing_part, 'seq' => $i + 1, 'coating_type' => $typeName, 'index_coating' => $index]);
        }

        (new CostingEngine)->run($costing);

        return [$costing->refresh(), $p->refresh(), $tier->refresh()];
    }

    public function test_raja_bakery_dc_sf_matches_legacy_row_51_and_mockup(): void
    {
        [, $p, $tier] = $this->run1('DC+SF', 5000, [
            'grammage_id' => $this->grammage(250), 'i_paper_price_override' => 255000, 'i_dc_persen_override' => 5.30,
            'i_panjang_plano_cm' => 120, 'i_lebar_plano_cm' => 104, 'i_cut_per_plano' => 4, 'i_isi_per_lb' => 2,
            'i_uk_cetak_p_cm' => 60, 'i_uk_cetak_l_cm' => 52, 'i_jumlah_warna' => 4,
            'i_ongkos_cetak_rim' => 500000, 'i_ongkos_plong_lb' => 75, 'i_ongkos_lem_pc' => 30,
            'i_ongkos_kupas_pc' => 10, 'i_ongkos_qc_pack_pc' => 10, 'i_ongkos_kirim_pc' => 10,
            'pakai_laminasi' => true, 'i_sf_ef_rate_override' => 2600, 'i_lam_pakai_ukuran_sf' => true,
            'i_sheet_panjang_cm' => 59, 'i_sheet_lebar_cm' => 51, 'i_ongkos_lam_rp_cm2_sf' => 0.065,
            'i_film_rate' => 60, 'i_film_faktor' => 0.05, 'i_pisau_faktor' => 0.05, 'i_pisau_rate_rp' => 600000, 'i_ongkos_plate_warna' => 90000,
        ], [['UV', 0.065]]);

        $this->assertEqualsWithDelta(582.0787, (float) $p->c_paper_cost_pc, 0.0005);
        $this->assertEqualsWithDelta(125.0, (float) $p->c_cetak_cost_pc, 0.0001);
        $this->assertEqualsWithDelta(37.5, (float) $p->c_plong_cost_pc, 0.0001);
        $this->assertEqualsWithDelta(101.4, (float) $p->c_total_coating_pc, 0.0001);
        $this->assertEqualsWithDelta(405.6, (float) $p->c_sf_ef_cost_pc, 0.0001);
        $this->assertEqualsWithDelta(97.7925, (float) $p->c_ongkos_lam_pc, 0.0001);
        $this->assertEqualsWithDelta(827.2925, (float) $p->c_process_cost_pc + (float) $p->c_total_coating_pc, 0.0005);
        $this->assertEqualsWithDelta(390468.0, (float) $p->c_tooling_cost_order, 0.01);
        $this->assertEqualsWithDelta(3120.0, (float) $p->c_area_cetak_cm2, 0.0001);
        $this->assertEqualsWithDelta(625.0, (float) $p->c_jumlah_bahan_lb, 0.0001);
        $this->assertEqualsWithDelta(1487.4648, (float) $tier->c_hpp_pc, 0.0005);
    }

    public function test_ns40_dc_sheet_matches_legacy_row_1(): void
    {
        [, $p, $tier] = $this->run1('DC+SHEET', 1000, [
            'grammage_id' => $this->grammage(350), 'i_paper_price_override' => 314000, 'i_dc_persen_override' => 5.30,
            'i_panjang_plano_cm' => 70, 'i_lebar_plano_cm' => 100, 'i_cut_per_plano' => 2, 'i_isi_per_lb' => 1,
            'i_uk_cetak_p_cm' => 70, 'i_uk_cetak_l_cm' => 41, 'i_jumlah_warna' => 4,
            'i_ongkos_cetak_rim' => 900000, 'i_ongkos_plong_lb' => 250, 'i_ongkos_lem_pc' => 375,
            'i_ongkos_kupas_pc' => 30, 'i_ongkos_qc_pack_pc' => 100, 'i_ongkos_kirim_pc' => 200,
            'pakai_laminasi' => true, 'i_lam_sheet_flat_pc' => 1360, 'i_ongkos_lam_rp_cm2_sh' => 0.15, 'i_ongkos_lam_rp_cm2_sf' => 0.10,
            'i_film_rate' => 60, 'i_film_faktor' => 0.25, 'i_pisau_faktor' => 0.25, 'i_pisau_rate_rp' => 900000, 'i_ongkos_plate_warna' => 90000,
        ], [['Laminating Biasa', 0.165]]);

        $this->assertEqualsWithDelta(1608.1059, (float) $p->c_paper_cost_pc, 0.0005);
        $this->assertEqualsWithDelta(3645.5, (float) $p->c_process_cost_pc, 0.0005);
        $this->assertEqualsWithDelta(473.55, (float) $p->c_total_coating_pc, 0.0001);
        $this->assertEqualsWithDelta(595762.5, (float) $p->c_tooling_cost_order, 0.01);
        $this->assertEqualsWithDelta(6322.9184, (float) $tier->c_hpp_pc, 0.0005);
    }

    public function test_dus_dc_matches_legacy_row_52(): void
    {
        [, $p, $tier] = $this->run1('DC', 10000, [
            'grammage_id' => $this->grammage(350), 'i_paper_price_override' => 314000, 'i_dc_persen_override' => 5.60,
            'i_panjang_plano_cm' => 70, 'i_lebar_plano_cm' => 100, 'i_cut_per_plano' => 1, 'i_isi_per_lb' => 3,
            'i_uk_cetak_p_cm' => 62, 'i_uk_cetak_l_cm' => 100, 'i_jumlah_warna' => 4,
            'i_ongkos_cetak_rim' => 135000, 'i_ongkos_plong_lb' => 75,
            'i_ongkos_kupas_pc' => 10, 'i_ongkos_qc_pack_pc' => 10, 'i_ongkos_kirim_pc' => 10,
            'i_film_rate' => 45, 'i_film_faktor' => 0.5, 'i_pisau_faktor' => 0.25, 'i_pisau_rate_rp' => 1000000, 'i_ongkos_plate_warna' => 90000,
        ]);

        $this->assertEqualsWithDelta(1123.1216, (float) $p->c_paper_cost_pc, 0.0005);
        $this->assertEqualsWithDelta(145.0, (float) $p->c_process_cost_pc, 0.0001);
        $this->assertEqualsWithDelta(1336.0966, (float) $tier->c_hpp_pc, 0.0005);
    }
}
