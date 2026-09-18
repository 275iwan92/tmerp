<?php

namespace Tests\Feature;

use App\Models\Master\MatGrammage;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class CostingEngineTest extends TestCase
{
    protected ?IqpInquiry $inquiry = null;

    protected function tearDown(): void
    {
        if ($this->inquiry) {
            $costingIds = DB::connection('tm2sdi')->table('iqp_costing')->where('inquiry_id', $this->inquiry->id_inquiry)->pluck('id_costing');
            $partIds = DB::connection('tm2sdi')->table('iqp_costing_part')->whereIn('costing_id', $costingIds)->pluck('id_costing_part');
            DB::connection('tm2sdi')->table('iqp_costing_part_coating')->whereIn('costing_part_id', $partIds)->delete();
            DB::connection('tm2sdi')->table('iqp_costing_part')->whereIn('costing_id', $costingIds)->delete();
            DB::connection('tm2sdi')->table('iqp_costing_price_tier')->whereIn('costing_id', $costingIds)->delete();
            DB::connection('tm2sdi')->table('iqp_costing')->where('inquiry_id', $this->inquiry->id_inquiry)->delete();
            DB::connection('tm2sdi')->table('iqp_part')->where('inquiry_id', $this->inquiry->id_inquiry)->delete();
            DB::connection('tm2sdi')->table('iqp_tier')->where('inquiry_id', $this->inquiry->id_inquiry)->delete();
            DB::connection('tm2sdi')->table('iqp_inquiry')->where('id_inquiry', $this->inquiry->id_inquiry)->delete();
        }

        parent::tearDown();
    }

    public function test_engine_calculates_dc_part_and_price_ladder(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        // 1) Buat inquiry dasar (1 tier qty=1000) via layar form yang sudah teruji.
        Livewire::test('pages::inquiry.form', ['mode' => 'NEW'])
            ->set('inquiry_date', now()->toDateString())
            ->set('product_draft_name', 'Box Test Costing Engine')
            ->call('setProspect', true)
            ->set('prospect_name', 'Prospek Test Costing')
            ->set('tiers.0.qty', 1000)
            ->set('parts.0.part_name', 'Body')
            ->call('save')
            ->assertHasNoErrors();

        $this->inquiry = IqpInquiry::where('product_draft_name', 'Box Test Costing Engine')->latest('id_inquiry')->firstOrFail();

        $grammage = MatGrammage::where('grammage_name', 'Duplex 300gsm')->firstOrFail();

        // 2) Buka layar costing (auto-create header + price tier dari inquiry).
        $component = Livewire::test('pages::inquiry.costing', ['inquiry' => $this->inquiry->id_inquiry]);

        $costing = IqpCosting::where('inquiry_id', $this->inquiry->id_inquiry)->firstOrFail();
        $this->assertSame('DRAFT', $costing->doc_status);
        // 3 preset qty default (form.blade.php, permintaan user 2026-09-04) --
        // bukan lagi 1 tier kosong. Baris pertama (tiers.0) di-override qty=1000
        // di atas, tetap yang dipakai $tier di bawah (assertSame ini cuma cek
        // total count, bukan tier mana yang dipakai).
        $this->assertSame(3, $costing->priceTiers()->count());

        // 3) Tambah 1 part DC, isi rate, tambah 1 coating layer.
        $component->call('addPart');
        $part = $costing->parts()->firstOrFail();

        $component
            ->call('updatePart', $part->id_costing_part, 'grammage_id', $grammage->id_grammage)
            ->call('updatePart', $part->id_costing_part, 'i_panjang_plano_cm', 65)
            ->call('updatePart', $part->id_costing_part, 'i_lebar_plano_cm', 100)
            ->call('updatePart', $part->id_costing_part, 'i_cut_per_plano', 2)
            ->call('updatePart', $part->id_costing_part, 'i_isi_per_lb', 4)
            ->call('updatePart', $part->id_costing_part, 'i_jumlah_warna', 4)
            ->call('updatePart', $part->id_costing_part, 'i_ongkos_cetak_rim', 200000)
            ->call('updatePart', $part->id_costing_part, 'i_ongkos_plong_lb', 5000)
            ->call('updatePart', $part->id_costing_part, 'i_ongkos_lem_pc', 300)
            ->call('updatePart', $part->id_costing_part, 'i_pisau_faktor', 0.5)
            ->call('addCoating', $part->id_costing_part);

        $coating = $part->coatings()->firstOrFail();
        $component->call('updateCoating', $coating->id_coating, 'coating_type', 'Water Base');

        $tier = $costing->priceTiers()->firstOrFail();
        $component
            ->call('updateTier', $tier->id_price_tier, 'tolerance_pct', 5)
            ->call('updateTier', $tier->id_price_tier, 'margin_pct', 20)
            ->call('updateTier', $tier->id_price_tier, 'rounding_step', 100);

        // 4) Hitung.
        $component->call('hitung')->assertSet('engineError', '');

        $part->refresh();
        $tier->refresh();
        $costing->refresh();

        // ── Verifikasi manual, rumus legacy tmhpp (procost_formula_library.md §VII) ──
        $areaCm2 = 65 * 100;            // luas PLANO -> hanya utk kertas
        $areaCetak = $areaCm2 / 2;      // Ukuran Cetak kosong -> fallback plano/cut
        $dcf = 1 + 5.3;                 // faktor DC legacy = 6,30
        $expectedPaperPc = ($areaCm2 / 8611) * 12500 * $dcf / 500 / 2 / 4;
        $this->assertEqualsWithDelta($expectedPaperPc, (float) $part->c_paper_cost_pc, 0.01);

        $expectedCetakPc = 200000 / 500 / 2 / 4;
        $expectedPlongPc = 5000 / 4;
        $expectedLemPc = 300;
        $this->assertEqualsWithDelta($expectedCetakPc, (float) $part->c_cetak_cost_pc, 0.01);
        $this->assertEqualsWithDelta($expectedPlongPc, (float) $part->c_plong_cost_pc, 0.01);
        $this->assertEqualsWithDelta($expectedLemPc, (float) $part->c_lem_cost_pc, 0.01);

        $expectedCoatingPc = ($areaCetak * 0.035) / 4;
        $this->assertEqualsWithDelta($expectedCoatingPc, (float) $part->c_total_coating_pc, 0.01);

        $expectedFilmOrder = (0.25 ** 2) * $areaCetak * 55;
        $expectedPlateOrder = 90000 * 4;
        $expectedKnifeOrder = 0.5 * 900000;
        $expectedToolingOrder = $expectedFilmOrder + $expectedPlateOrder + $expectedKnifeOrder;
        $this->assertEqualsWithDelta($expectedToolingOrder, (float) $part->c_tooling_cost_order, 0.01);

        $expectedTotalPartCostPc = $expectedPaperPc + $expectedCetakPc + $expectedPlongPc + $expectedLemPc + $expectedCoatingPc;
        $this->assertEqualsWithDelta($expectedTotalPartCostPc, (float) $part->c_total_part_cost_pc, 0.01);

        // Header roll-up (1 part).
        $this->assertEqualsWithDelta($expectedPaperPc, (float) $costing->total_material_cost_pc, 0.01);
        $this->assertEqualsWithDelta($expectedCetakPc + $expectedPlongPc + $expectedLemPc, (float) $costing->total_process_cost_pc, 0.01);
        $this->assertEqualsWithDelta($expectedCoatingPc, (float) $costing->total_finishing_cost_pc, 0.01);
        $this->assertEqualsWithDelta($expectedToolingOrder, (float) $costing->c_tooling_cost_order, 0.01);

        // Ladder tier (qty=1000, tolerance=5%, margin=20%, round NEAREST step=100).
        $expectedTotalHppPc = $expectedTotalPartCostPc;
        $expectedToolingPc = $expectedToolingOrder / 1000;
        $expectedHppPc = $expectedTotalHppPc + $expectedToolingPc;
        $expectedAfterTolerance = $expectedHppPc / (1 - 0.05); // mark-up on price, bukan ×(1+t)
        $expectedAfterRound = round($expectedAfterTolerance / 100) * 100;
        $expectedAfterMargin = $expectedAfterRound / (1 - 0.20);
        $expectedAfterPpn = $expectedAfterMargin * 1.11;

        $this->assertEqualsWithDelta($expectedHppPc, (float) $tier->c_hpp_pc, 0.01);
        $this->assertEqualsWithDelta($expectedAfterRound, (float) $tier->c_after_round_pc, 0.01);
        $this->assertEqualsWithDelta($expectedAfterMargin, (float) $tier->price_per_pcs, 0.01);
        $this->assertEqualsWithDelta($expectedAfterPpn, (float) $tier->c_after_ppn_pc, 0.01);

        $this->assertSame('CALCULATED', $costing->doc_status);
    }

    // 2026-09-02: SHEET, KRAFT, DC+SHEET, DC+SF diimplementasikan (lihat
    // CostingEngine docblock) -- test ini di-repurpose pakai kode BOGUS yang
    // genuinely tak dikenal, supaya default => throw branch tetap teruji.
    public function test_engine_rejects_unimplemented_part_type(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test('pages::inquiry.form', ['mode' => 'NEW'])
            ->set('inquiry_date', now()->toDateString())
            ->set('product_draft_name', 'Box Test Costing BOGUS')
            ->call('setProspect', true)
            ->set('prospect_name', 'Prospek Test Costing BOGUS')
            ->set('tiers.0.qty', 500)
            ->set('parts.0.part_name', 'Body')
            ->call('save')
            ->assertHasNoErrors();

        $this->inquiry = IqpInquiry::where('product_draft_name', 'Box Test Costing BOGUS')->latest('id_inquiry')->firstOrFail();

        $component = Livewire::test('pages::inquiry.costing', ['inquiry' => $this->inquiry->id_inquiry]);
        $costing = IqpCosting::where('inquiry_id', $this->inquiry->id_inquiry)->firstOrFail();

        $component->call('addPart');
        $part = $costing->parts()->firstOrFail();
        DB::connection('tm2sdi')->table('iqp_costing_part')->where('id_costing_part', $part->id_costing_part)->update(['part_type_code' => 'BOGUS']);

        $component->call('hitung');
        $component->assertSet('engineError', "part_type_code 'BOGUS' belum diimplementasi CostingEngine.");

        $costing->refresh();
        $this->assertSame('DRAFT', $costing->doc_status);
    }

    /**
     * Regresi byte-presisi ke Excel asli "OB COCO TOKYO FLAT 427X220X172MM 1800 BOX
     * 2026-07-13 K200_M125_K200.xlsx" (blok UJK, kolom "C" = NON PPN). Input & angka
     * expected diambil langsung dari cell Excel — bukan dihitung ulang manual.
     */
    public function test_engine_calculates_sheet_digital_matches_excel_coco_tokyo(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $sheetRate = \App\Models\Master\MatSheetRate::where('substance_name', '200 KL / 125 / 200 KL')->where('flute_type', 'B/F')->firstOrFail();

        Livewire::test('pages::inquiry.form', ['mode' => 'NEW'])
            ->set('inquiry_date', now()->toDateString())
            ->set('product_draft_name', 'Box Test Costing COCO TOKYO')
            ->call('setProspect', true)
            ->set('prospect_name', 'COCO TOKYO')
            ->set('tiers.0.qty', 1800)
            ->set('parts.0.part_name', 'Box')
            ->call('save')
            ->assertHasNoErrors();

        $this->inquiry = IqpInquiry::where('product_draft_name', 'Box Test Costing COCO TOKYO')->latest('id_inquiry')->firstOrFail();

        $component = Livewire::test('pages::inquiry.costing', ['inquiry' => $this->inquiry->id_inquiry]);
        $costing = IqpCosting::where('inquiry_id', $this->inquiry->id_inquiry)->firstOrFail();
        $costing->update(['ppn_mode' => 'NON_PPN']); // kolom "C" Excel = jalur NON PPN

        $component->call('addPart');
        $part = $costing->parts()->firstOrFail();

        DB::connection('tm2sdi')->table('iqp_costing_part')->where('id_costing_part', $part->id_costing_part)->update([
            'part_type_code' => 'SHEET_DIGITAL',
            'i_dim_panjang_mm' => 427,
            'i_dim_lebar_mm' => 220,
            'i_dim_tinggi_mm' => 172,
            'i_lidah_lem_mm' => 35,
            'flute_type' => 'B/F',
            'sheet_rate_id' => $sheetRate->id_sheet_rate,
            'supplier_sheet_code' => 'UJK',
            'i_isi_per_lb_kerja' => 1,
            'i_ongkos_print_pc' => 100,   // C20 = 2*B20 = 200 (engine yg kali 2)
            'i_ongkos_diecut_pc' => 350,
            'i_ongkos_qc_pack_pc' => 100,
            'i_ongkos_kirim_pc' => 100,
        ]);

        $tier = $costing->priceTiers()->firstOrFail();
        $component
            ->call('updateTier', $tier->id_price_tier, 'tolerance_pct', 1)
            ->call('updateTier', $tier->id_price_tier, 'margin_pct', 15)
            ->call('updateTier', $tier->id_price_tier, 'rounding_step', 0.0001); // Excel tak ada step pembulatan

        $component->call('hitung')->assertSet('engineError', '');

        $part->refresh();
        $tier->refresh();
        $costing->refresh();

        // ── Geometri (§B.5) ──
        $this->assertEqualsWithDelta(134.1, (float) $part->c_panjang_sheet_cm, 0.001);
        $this->assertEqualsWithDelta(40.2, (float) $part->c_lebar_sheet_cm, 0.001);
        $this->assertEqualsWithDelta(4, (float) $part->c_isi_trim, 0.001);
        $this->assertEqualsWithDelta(165, (float) $part->c_lebar_trim_cm, 0.001);
        $this->assertEqualsWithDelta(603.45, (float) $part->c_running_meter, 0.01);
        $this->assertEqualsWithDelta(-0.05, (float) $part->c_discount_rm, 0.0001);

        // ── Harga (§B.2) — Excel C19 = 3536.035965 ──
        $this->assertEqualsWithDelta(3536.035965, (float) $part->c_sheet_cost_pc, 0.01);

        // ── Total sebelum ladder — Excel C30 = 4286.035965 ──
        $this->assertEqualsWithDelta(4286.035965, (float) $part->c_total_part_cost_pc, 0.01);

        // ── Ladder — Excel C31 (toleransi 1%) = 4329.329257575758, C32 (margin 15%) = 5093.328538324421 ──
        $this->assertEqualsWithDelta(4286.035965, (float) $tier->c_hpp_pc, 0.01);
        $this->assertEqualsWithDelta(4329.329257575758, (float) $tier->c_after_round_pc, 0.05);
        $this->assertEqualsWithDelta(5093.328538324421, (float) $tier->price_per_pcs, 0.05);
        // NON_PPN: harga akhir TIDAK kena PPN sama sekali (Excel C34 = C32, tanpa PPN).
        $this->assertEqualsWithDelta(5093.328538324421, (float) $tier->c_after_ppn_pc, 0.05);
    }
}
