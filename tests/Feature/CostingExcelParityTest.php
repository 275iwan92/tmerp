<?php

namespace Tests\Feature;

use App\Models\Master\MatGrammage;
use App\Models\Master\MatSheetRate;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpCostingPart;
use App\Models\Sdi\IqpCostingPartCoating;
use App\Models\Sdi\IqpCostingPriceTier;
use App\Services\Costing\CostingEngine;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Paritas engine vs 5 Excel HPP asli (OneDrive KalkuHPP/excel, dicocokkan 2026-09-18).
// Angka target = nilai cache cell Excel apa adanya. Lihat procost_formula_library.md §VIII.
class CostingExcelParityTest extends TestCase
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

    private function grammage(string $kind, int $gsm): int
    {
        return (int) MatGrammage::where('paper_kind', $kind)->where('grammage_value', $gsm)->where('activate', true)->value('id_grammage');
    }

    /**
     * @param  array<int, array{0: string, 1: array, 2?: array}>  $parts  [type, fields, coatings[[name, index]]]
     * @return array{0: IqpCosting, 1: IqpCostingPriceTier, 2: array<int, IqpCostingPart>}
     */
    private function calc(float $qty, array $tier, string $ppnMode, float $ppnPct, array $parts): array
    {
        $costing = IqpCosting::create(['mode' => 'INSTANT', 'calc_type' => 'NEW', 'doc_status' => 'DRAFT', 'ppn_mode' => $ppnMode, 'ppn_pct' => $ppnPct, 'created_by' => 'test']);
        $this->costingIds[] = $costing->id_costing;
        $t = IqpCostingPriceTier::create($tier + ['costing_id' => $costing->id_costing, 'qty_pcs' => $qty, 'is_primary' => true, 'rounding_mode' => 'NONE', 'created_by' => 'test']);

        $created = [];
        foreach ($parts as $i => $spec) {
            [$type, $fields] = $spec;
            $p = IqpCostingPart::create($fields + ['costing_id' => $costing->id_costing, 'no_urut' => $i + 1, 'part_name' => 'P'.($i + 1), 'part_type_code' => $type, 'qty_per_set' => 1, 'created_by' => 'test']);
            foreach ($spec[2] ?? [] as $j => [$name, $index]) {
                IqpCostingPartCoating::create(['costing_part_id' => $p->id_costing_part, 'seq' => $j + 1, 'coating_type' => $name, 'index_coating' => $index]);
            }
            $created[] = $p;
        }

        (new CostingEngine)->run($costing);

        return [$costing->refresh(), $t->refresh(), array_map(fn ($p) => $p->refresh(), $created)];
    }

    private function fsGtz(): array
    {
        return [
            'grammage_id' => $this->grammage('DUPLEX', 250), 'i_paper_price_override' => 253000, 'i_dc_persen_override' => 5.3,
            'i_panjang_plano_cm' => 89, 'i_lebar_plano_cm' => 125, 'i_cut_per_plano' => 3, 'i_isi_per_lb' => 4,
            'i_uk_cetak_p_cm' => 89, 'i_uk_cetak_l_cm' => 42, 'i_jumlah_warna' => 4,
            'i_ongkos_cetak_rim' => 1500000, 'i_ongkos_plong_lb' => 250, 'i_ongkos_lem_pc' => 60,
            'i_ongkos_kupas_pc' => 20, 'i_ongkos_qc_pack_pc' => 20, 'i_ongkos_kirim_pc' => 20,
            'i_film_rate' => 55, 'i_film_faktor' => 0.05, 'i_pisau_faktor' => 0.05, 'i_pisau_rate_rp' => 900000, 'i_ongkos_plate_warna' => 90000,
        ];
    }

    /** "FS GTZ-5S MF TRIAL 2026-04-01_202607-3" Bag atas = legacy hpp #5/#6. */
    public function test_fs_gtz_202607_dc_sf_ppn10(): void
    {
        [, $tier, [$p]] = $this->calc(3000, ['tolerance_pct' => 6, 'margin_pct' => 15], 'PPN', 10, [
            ['DC+SF', $this->fsGtz() + ['i_sf_ef_rate_override' => 2600, 'i_ongkos_lam_rp_cm2_sh' => 0.075, 'i_ongkos_lam_rp_cm2_sf' => 0.065], [['Laminating Biasa', 0.125]]],
        ]);

        $this->assertEqualsWithDelta(343.2070898, (float) $p->c_paper_cost_pc, 0.0005);   // J3
        $this->assertEqualsWithDelta(116.8125, (float) $p->c_total_coating_pc, 0.0001);   // J33
        $this->assertEqualsWithDelta(242.97, (float) $p->c_sf_ef_cost_pc, 0.0001);        // J37
        $this->assertEqualsWithDelta(60.7425, (float) $p->c_ongkos_lam_pc, 0.0001);       // J38
        $this->assertEqualsWithDelta(1331.4034, (float) $tier->c_hpp_pc, 0.0005);         // J43
        $this->assertEqualsWithDelta(1411.2876, (float) $tier->c_after_tolerance_pc, 0.0005); // J45
        $this->assertEqualsWithDelta(1660.3384, (float) $tier->c_after_margin_pc, 0.0005);    // G46 (NON PPN)
        $this->assertEqualsWithDelta(1597.6456, (float) $tier->price_per_pcs, 0.0005);        // K48 (DPP)
        $this->assertEqualsWithDelta(1757.4102, (float) $tier->c_after_ppn_pc, 0.0005);       // K50
    }

    /** "FS GTZ-5S MF TRIAL 2026-04-01" Bag atas: laminasi sheet flat 1852/isi, ongkos lam 0,095. */
    public function test_fs_gtz_april_dc_sheet_ppn10(): void
    {
        [, $tier] = $this->calc(667 * 4, ['tolerance_pct' => 6, 'margin_pct' => 15], 'PPN', 10, [
            ['DC+SHEET', $this->fsGtz() + ['pakai_laminasi' => true, 'i_lam_sheet_flat_pc' => 1852 / 4, 'i_ongkos_lam_rp_cm2_sh' => 0.095, 'i_ongkos_lam_rp_cm2_sf' => 0.085], [['Laminating Biasa', 0.125]]],
        ]);

        $this->assertEqualsWithDelta(1596.2888, (float) $tier->c_hpp_pc, 0.0005);             // J43
        $this->assertEqualsWithDelta(1692.0662, (float) $tier->c_after_tolerance_pc, 0.0005);  // J45
        $this->assertEqualsWithDelta(1990.6661, (float) $tier->c_after_margin_pc, 0.0005);     // G46
        $this->assertEqualsWithDelta(1904.4407, (float) $tier->price_per_pcs, 0.0005);         // K48
        $this->assertEqualsWithDelta(2094.8848, (float) $tier->c_after_ppn_pc, 0.0005);        // K50
    }

    /** "R3 KRAFT POLOS 140X100X70MM 2025-08-23 PPN" Bag atas: margin NON PPN 12% (G45) & PPN 15% (J47) dlm 1 dokumen. */
    public function test_kraft_per_kg_both_paths(): void
    {
        $kraft = [['KRAFT', [
            'grammage_id' => $this->grammage('KRAFT', 250), 'i_paper_price_override' => 6700,
            'i_panjang_plano_cm' => 77, 'i_lebar_plano_cm' => 101, 'i_cut_per_plano' => 2, 'i_isi_per_lb' => 4,
            'i_uk_cetak_p_cm' => 38.5, 'i_uk_cetak_l_cm' => 101, 'i_jumlah_warna' => 0,
            'i_ongkos_plong_lb' => 60, 'i_ongkos_kupas_pc' => 8,
            'i_film_rate' => 55, 'i_film_faktor' => 0.1, 'i_pisau_faktor' => 0.1, 'i_pisau_rate_rp' => 1500000,
        ]]];

        [, $ppn, [$p]] = $this->calc(100000, ['tolerance_pct' => 2, 'margin_pct' => 12, 'margin_ppn_pct' => 15], 'PPN', 11, $kraft);
        $this->assertEqualsWithDelta(162.8309375, (float) $p->c_paper_cost_pc, 0.0005);  // J3
        $this->assertEqualsWithDelta(187.3523, (float) $ppn->c_hpp_pc, 0.0005);           // J43
        $this->assertEqualsWithDelta(191.0994, (float) $ppn->c_after_tolerance_pc, 0.0005); // J45
        $this->assertEqualsWithDelta(217.1584, (float) $ppn->c_after_margin_pc, 0.0005);   // G46 (NON PPN, margin 12)
        $this->assertEqualsWithDelta(207.4077, (float) $ppn->price_per_pcs, 0.0005);       // K48 (margin PPN 15)
        $this->assertEqualsWithDelta(230.2226, (float) $ppn->c_after_ppn_pc, 0.0005);      // K50

        [, $non] = $this->calc(100000, ['tolerance_pct' => 2, 'margin_pct' => 12], 'NON_PPN', 11, $kraft);
        $this->assertEqualsWithDelta(217.1584, (float) $non->c_after_ppn_pc, 0.0005);      // G46
        $this->assertEqualsWithDelta(217.1584, (float) $non->price_per_pcs, 0.0005);
        $this->assertEqualsWithDelta(0.0, (float) $non->c_ppn_input_credit_pc, 0.0001);
    }

    private function icChina(): array
    {
        return [
            'grammage_id' => $this->grammage('DUPLEX', 400), 'i_paper_price_override' => 350000, 'i_dc_persen_override' => 3.9,
            'i_panjang_plano_cm' => 89, 'i_lebar_plano_cm' => 120, 'i_cut_per_plano' => 3, 'i_isi_per_lb' => 2,
            'i_uk_cetak_p_cm' => 93, 'i_uk_cetak_l_cm' => 40, 'i_ongkos_plong_lb' => 90, 'i_film_faktor' => 0.15, 'i_pisau_faktor' => 0.15,
        ];
    }

    /** "IC CHINA 244X184X45MM 2026-04-18 PPN": sheet badan (+ kolom PPN) & tutup. */
    public function test_ic_china_badan_and_tutup(): void
    {
        $badan = ['DC', $this->icChina() + ['i_jumlah_warna' => 0, 'i_ongkos_kupas_pc' => 60, 'i_ongkos_qc_pack_pc' => 60, 'i_ongkos_kirim_pc' => 80, 'i_film_rate' => 55, 'i_pisau_rate_rp' => 1000000], [['Laminating Biasa', 0.16], ['Laminating Biasa', 0.16]]];
        $tutup = ['DC', $this->icChina() + ['i_jumlah_warna' => 2, 'i_ongkos_cetak_rim' => 350000, 'i_ongkos_plate_warna' => 90000, 'i_film_rate' => 30, 'i_pisau_rate_rp' => 900000], [['Laminating Biasa', 0.16]]];

        [, $tier, [$p]] = $this->calc(20000, ['tolerance_pct' => 4, 'margin_pct' => 15], 'PPN', 11, [$badan]);
        $this->assertEqualsWithDelta(709.0233, (float) $p->c_paper_cost_pc, 0.0005);       // J3
        $this->assertEqualsWithDelta(595.2, (float) $p->c_total_coating_pc, 0.0001);       // J33
        $this->assertEqualsWithDelta(1556.9535, (float) $tier->c_hpp_pc, 0.0005);           // J43
        $this->assertEqualsWithDelta(1619.2317, (float) $tier->c_after_tolerance_pc, 0.0005); // J45
        $this->assertEqualsWithDelta(1829.1470, (float) $tier->price_per_pcs, 0.0005);       // K48
        $this->assertEqualsWithDelta(2030.3532, (float) $tier->c_after_ppn_pc, 0.0005);      // K50

        [, $tier] = $this->calc(20000, ['tolerance_pct' => 3, 'margin_pct' => 15], 'NON_PPN', 11, [$tutup]);
        $this->assertEqualsWithDelta(1184.1656, (float) $tier->c_hpp_pc, 0.0005);           // tutup J39
        $this->assertEqualsWithDelta(1219.6905, (float) $tier->c_after_tolerance_pc, 0.0005); // tutup J41

        // Set badan+tutup dgn toleransi per part (badan 4%, tutup 3%): badan J46 = J45 + tutup!J41, G46 = ÷(1−15%).
        $badan[1]['tolerance_pct'] = 4;
        $tutup[1]['tolerance_pct'] = 3;
        [, $tier] = $this->calc(20000, ['tolerance_pct' => 0, 'margin_pct' => 15], 'NON_PPN', 11, [$badan, $tutup]);
        $this->assertEqualsWithDelta(1556.9535 + 1184.1656, (float) $tier->c_hpp_pc, 0.001);
        $this->assertEqualsWithDelta(2838.9222, (float) $tier->c_after_tolerance_pc, 0.0005); // badan J46
        $this->assertEqualsWithDelta(3339.9085, (float) $tier->c_after_margin_pc, 0.0005);    // badan G46
    }

    /** "OB COCO TOKYO ... 2026-07-13": kolom D (EXCL PPN) -- sheet ÷1,1, toleransi ÷(1−t) atas DPP. */
    public function test_coco_tokyo_sheet_digital_ppn_path(): void
    {
        $rate = MatSheetRate::where('substance_name', '200 KL / 125 / 200 KL')->where('flute_type', 'B/F')->firstOrFail();

        [, $tier, [$p]] = $this->calc(1800, ['tolerance_pct' => 1, 'margin_pct' => 15], 'PPN', 11, [['SHEET_DIGITAL', [
            'i_dim_panjang_mm' => 427, 'i_dim_lebar_mm' => 220, 'i_dim_tinggi_mm' => 172, 'i_lidah_lem_mm' => 35,
            'flute_type' => 'B/F', 'sheet_rate_id' => $rate->id_sheet_rate, 'supplier_sheet_code' => 'UJK', 'i_isi_per_lb_kerja' => 1,
            'i_discount_rm' => -0.05, // Excel B17; tier legacy UJK utk K200/M125/K200 = default -10%
            'i_ongkos_print_pc' => 100, 'i_ongkos_diecut_pc' => 350, 'i_ongkos_qc_pack_pc' => 100, 'i_ongkos_kirim_pc' => 100,
        ]]]);

        $this->assertEqualsWithDelta(3536.035965, (float) $p->c_sheet_cost_pc, 0.0005);      // C19
        $this->assertEqualsWithDelta(4329.3293, (float) $tier->c_after_tolerance_pc, 0.0005); // C31
        $this->assertEqualsWithDelta(5093.3285, (float) $tier->c_after_margin_pc, 0.0005);    // C32 (NON PPN)
        $this->assertEqualsWithDelta(4711.3228, (float) $tier->price_per_pcs, 0.0005);        // D32
        $this->assertEqualsWithDelta(5229.5683, (float) $tier->c_after_ppn_pc, 0.0005);       // D34
        // Info supplier RCA: lebar mesin 215 & gap 5 (legacy m_sheet_supplier) -> isi/trim 5, trim 210,
        // RM 482,76 -> tier RCA default 300-499 = 20%: 210×134,1/10000×6088/5×0,8.
        $this->assertStringContainsString('RCA: Rp '.number_format(210 * 134.1 / 10000 * 6088 / 5 * 0.8, 2).' (disc 20.0%', (string) $p->c_sheet_compare_info);
    }

    public function test_legacy_sheet_discount_tier_precedence(): void
    {
        $id = fn (string $name) => (int) MatSheetRate::where('substance_name', $name)->where('flute_type', 'B/F')->value('id_sheet_rate');
        $lookup = \App\Models\Master\MatSheetDiscountTier::class;

        $this->assertEqualsWithDelta(-0.05, $lookup::lookup('UJK', 100, $id('K125/M125/M125'), 'SHEET'), 1e-9);   // K125 aktif Apr2026
        $this->assertEqualsWithDelta(-0.10, $lookup::lookup('UJK', 100, $id('K200/M125/K200'), 'SHEET'), 1e-9);   // UJK default
        $this->assertEqualsWithDelta(0.225, $lookup::lookup('RCA', 5000, $id('WK150/M125/M125'), 'SHEET'), 1e-9);  // WK150 flat
        $this->assertEqualsWithDelta(0.25, $lookup::lookup('RCA', 600, $id('K200/M125/K200'), 'SHEET'), 1e-9);     // RCA default 500-999
        $this->assertEqualsWithDelta(0.20, $lookup::lookup('RCA', 299.995, null, 'SHEET'), 1e-9);                  // rm_to 299.99 inklusif
    }

    public function test_rca_manual_discount_must_be_between_20_and_26_percent(): void
    {
        $rate = MatSheetRate::where('substance_name', '200 KL / 125 / 200 KL')->where('flute_type', 'B/F')->firstOrFail();
        $part = fn (float $disc) => [['SHEET_DIGITAL', [
            'i_dim_panjang_mm' => 427, 'i_dim_lebar_mm' => 220, 'i_dim_tinggi_mm' => 172, 'i_lidah_lem_mm' => 35,
            'flute_type' => 'B/F', 'sheet_rate_id' => $rate->id_sheet_rate, 'supplier_sheet_code' => 'RCA', 'i_isi_per_lb_kerja' => 1,
            'i_discount_rm' => $disc,
        ]]];

        [, , [$p]] = $this->calc(1800, ['tolerance_pct' => 1, 'margin_pct' => 15], 'NON_PPN', 11, $part(0.225));
        $this->assertEqualsWithDelta(0.225, (float) $p->c_discount_rm, 1e-9);

        $this->expectExceptionMessage('di luar batas (0.2 s/d 0.26)');
        $this->calc(1800, ['tolerance_pct' => 1, 'margin_pct' => 15], 'NON_PPN', 11, $part(0.30));
    }
}
