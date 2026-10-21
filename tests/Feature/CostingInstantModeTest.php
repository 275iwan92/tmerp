<?php

namespace Tests\Feature;

use App\Models\Master\CustCustomer;
use App\Models\Master\MatGrammage;
use App\Models\Master\ProdLayoutGroup;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sys\SecUser;
use App\Services\Costing\InstantCostingService;
use App\Support\V2\Data\ProCostInstant;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// ProCost MODE INSTANT (InstantCostingService + layar v2 procost-instant, tata letak program HPP lama). Input persis kasus
// legacy RAJA BAKERY (= mockup #1) harus menghasilkan HPP/PC 1.487,4648 -- bukti aturan + engine = program lama.
class CostingInstantModeTest extends TestCase
{
    protected ?int $costingId = null;

    protected ?int $inquiryId = null;

    protected function tearDown(): void
    {
        $sdi = DB::connection('tm2sdi');
        if ($this->costingId) {
            $partIds = $sdi->table('iqp_costing_part')->where('costing_id', $this->costingId)->pluck('id_costing_part');
            $sdi->table('iqp_costing_part_coating')->whereIn('costing_part_id', $partIds)->delete();
            $sdi->table('iqp_costing_part')->where('costing_id', $this->costingId)->delete();
            $sdi->table('iqp_costing_price_tier')->where('costing_id', $this->costingId)->delete();
            $sdi->table('iqp_costing')->where('id_costing', $this->costingId)->delete();
        }
        if ($this->inquiryId) {
            $prodPartIds = $sdi->table('iqp_part')->where('inquiry_id', $this->inquiryId)->pluck('prod_part_id')->filter();
            $productIds = ProdPart::whereIn('id_part', $prodPartIds)->pluck('product_id');
            $groupIds = ProdLayoutGroup::whereIn('product_id', $productIds)->pluck('id_layout_group');
            DB::connection('tm2mst')->table('prod_layout_group_part')->whereIn('id_layout_group', $groupIds)->delete();
            ProdLayoutGroup::whereIn('id_layout_group', $groupIds)->delete();
            ProdPart::whereIn('id_part', $prodPartIds)->delete();
            ProdProduct::whereIn('id_product', $productIds)->delete();
            $sdi->table('iqp_tier')->where('inquiry_id', $this->inquiryId)->delete();
            $sdi->table('iqp_part')->where('inquiry_id', $this->inquiryId)->delete();
            $sdi->table('iqp_inquiry')->where('id_inquiry', $this->inquiryId)->delete();
        }

        parent::tearDown();
    }

    public function test_instant_rules_reproduce_legacy_raja_bakery_and_reverse(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());

        // + New Record (rute v2) membuat draft Instant kosong, lalu layar v2 terbuka.
        $this->get(route('v2.procost-instant.new'))->assertRedirect();
        $costing = IqpCosting::where('mode', 'INSTANT')->latest('id_costing')->firstOrFail();
        $this->costingId = $costing->id_costing;
        $this->get('/v2/s/procost-instant?id='.$costing->id_costing)->assertOk();

        $cust = CustCustomer::where('activate', true)->firstOrFail();
        $g250 = MatGrammage::where('paper_kind', 'DUPLEX')->where('grammage_value', 250)->where('activate', true)->value('id_grammage');

        $svc = InstantCostingService::class;
        $svc::updateHeader($costing, 'customer_id', $cust->id_customer);
        $svc::updateHeader($costing, 'product_name_draft', 'SJJ IB GOLD STAR GM5Z-3B CV');
        $svc::updateHeader($costing, 'ppn_mode', 'PPN');
        $svc::updateTier($costing, 'qty_pcs', 5000);
        $svc::updateTier($costing, 'margin_pct', 15);
        $svc::updateTier($costing, 'tolerance_pct', 5);
        $part = $svc::addPart($costing);
        $svc::updatePart($costing, $part->id_costing_part, 'part_type_code', 'DC+SF');
        $svc::updatePart($costing, $part->id_costing_part, 'grammage_id', $g250);

        foreach ([
            'i_paper_price_override' => 255000, 'i_dc_persen_override' => 5.30, 'i_panjang_plano_cm' => 120, 'i_lebar_plano_cm' => 104,
            'i_cut_per_plano' => 4, 'i_isi_per_lb' => 2, 'i_uk_cetak_p_cm' => 60, 'i_uk_cetak_l_cm' => 52, 'i_jumlah_warna' => 4,
            'i_ongkos_cetak_rim' => 500000, 'i_ongkos_plong_lb' => 75, 'i_ongkos_lem_pc' => 30, 'i_ongkos_kupas_pc' => 10,
            'i_ongkos_qc_pack_pc' => 10, 'i_ongkos_kirim_pc' => 10, 'pakai_laminasi' => 1, 'i_sf_ef_rate_override' => 2600,
            'i_lam_pakai_ukuran_sf' => 1, 'i_sheet_panjang_cm' => 59, 'i_sheet_lebar_cm' => 51, 'i_ongkos_lam_rp_cm2_sf' => 0.065,
            'i_film_rate' => 60, 'i_film_faktor' => 0.05, 'i_pisau_faktor' => 0.05, 'i_pisau_rate_rp' => 600000, 'i_ongkos_plate_warna' => 90000,
        ] as $field => $value) {
            $svc::updatePart($costing, $part->id_costing_part, $field, $value);
        }

        $svc::addCoating($costing, $part->id_costing_part);
        $coating = $costing->parts()->first()->coatings()->firstOrFail();
        $this->assertSame('', $svc::updateCoating($costing, $part->id_costing_part, $coating->id_coating, 'index_coating', 0.065));
        $this->get('/v2/s/procost-instant?id='.$costing->id_costing)->assertOk()->assertSee('Cost price (HPP)', false);

        $tier = $costing->priceTiers()->where('is_primary', true)->firstOrFail();
        $this->assertEqualsWithDelta(1487.4648, (float) $tier->c_hpp_pc, 0.0005);
        $this->assertSame('CALCULATED', $costing->fresh()->doc_status);

        // Part ke-2 ditambah lalu dihapus.
        $second = $svc::addPart($costing);
        $this->assertSame(2, $costing->parts()->count());
        $svc::removePart($costing, $second->id_costing_part);
        $this->assertSame(1, $costing->parts()->count());

        // Reverse -> Integrated + Inquiry BARU, angka part tidak berubah.
        $svc::reverse($costing->refresh());
        $costing->refresh();
        $this->inquiryId = $costing->inquiry_id;
        $this->assertSame(IqpCosting::MODE_INTEGRATED, $costing->mode);
        $this->assertSame('NEW', IqpInquiry::find($costing->inquiry_id)->inquiry_type);
        $this->assertEqualsWithDelta(1487.4648, (float) $costing->priceTiers()->where('is_primary', true)->value('c_hpp_pc'), 0.0005);
    }

    // Isian form v2 (Save): hanya field yang berubah dikirim ke service, lalu dihitung ulang; dokumen terkunci tidak berubah.
    public function test_instant_form_save_applies_changed_fields_and_recalculates(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $costing = IqpCosting::create(['mode' => 'INSTANT', 'calc_type' => 'NEW', 'doc_status' => 'DRAFT', 'created_by' => 'test']);
        $this->costingId = $costing->id_costing;
        $costing = InstantCostingService::open($costing->id_costing);
        $part = InstantCostingService::addPart($costing);

        $this->post(route('v2.procost-instant.save', $costing->id_costing), [
            'action' => 'save', 'part_id' => $part->id_costing_part, 'product_name_draft' => 'Form Save Test', 'tier' => ['qty_pcs' => '2500', 'margin_pct' => '12'],
            'part' => [$part->id_costing_part => ['part_name' => 'Body', 'i_jumlah_warna' => '2']],
        ])->assertRedirect();

        $this->assertSame('Form Save Test', $costing->refresh()->product_name_draft);
        $this->assertEquals(2500, (float) $costing->priceTiers()->first()->qty_pcs);
        $this->assertEquals(12, (float) $costing->priceTiers()->first()->margin_pct);
        $this->assertSame('Body', $part->refresh()->part_name);
        $this->assertSame(2, (int) $part->i_jumlah_warna);

        IqpCosting::whereKey($costing->id_costing)->update(['doc_status' => 'PENDING_APPROVAL']);
        $this->post(route('v2.procost-instant.save', $costing->id_costing), ['action' => 'save', 'product_name_draft' => 'Changed after lock'])->assertRedirect();
        $this->assertSame('Form Save Test', $costing->refresh()->product_name_draft);
    }

    // Rincian HPP hanya untuk login di konstanta PROCOST_FORMULA_LOGINS: state layar menandai login lain terkunci (layar menampilkan gembok).
    public function test_breakdown_hidden_for_login_outside_constant(): void
    {
        $other = SecUser::whereNotIn('login', ['johiwan', 'wiwik2'])->where('activate', true)->firstOrFail();
        $this->actingAs($other);

        $costing = IqpCosting::create(['mode' => 'INSTANT', 'calc_type' => 'NEW', 'doc_status' => 'DRAFT', 'created_by' => 'test']);
        $this->costingId = $costing->id_costing;
        InstantCostingService::addPart(InstantCostingService::open($costing->id_costing));

        $this->assertFalse(ProCostInstant::state($costing->id_costing)['canSee']);

        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $this->assertTrue(ProCostInstant::state($costing->id_costing)['canSee']);
    }
}
