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
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

// Layar ProCost MODE INSTANT (pages::inquiry.costing-instant, tata letak program
// HPP lama). Input via layar persis kasus legacy RAJA BAKERY (= mockup #1) harus
// menghasilkan HPP/PC 1.487,4648 -- bukti layar + engine = program lama.
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

    public function test_instant_screen_reproduces_legacy_raja_bakery_and_reverse(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());

        Livewire::test('pages::inquiry.costing-instant-list')->assertOk()->call('newInstant');
        $costing = IqpCosting::where('mode', 'INSTANT')->latest('id_costing')->firstOrFail();
        $this->costingId = $costing->id_costing;

        $cust = CustCustomer::where('activate', true)->firstOrFail();
        $g250 = MatGrammage::where('paper_kind', 'DUPLEX')->where('grammage_value', 250)->where('activate', true)->value('id_grammage');

        $form = Livewire::test('pages::inquiry.costing-instant', ['costing' => $costing->id_costing])->assertOk()
            ->call('updateHeader', 'customer_id', $cust->id_customer)
            ->call('updateHeader', 'product_name_draft', 'SJJ IB GOLD STAR GM5Z-3B CV')
            ->call('updateHeader', 'ppn_mode', 'PPN')
            ->call('updateTier', 'qty_pcs', 5000)
            ->call('updateTier', 'margin_pct', 15)
            ->call('updateTier', 'tolerance_pct', 5)
            ->call('addPart')
            ->assertSee('Parameter')
            ->call('updatePart', 'part_type_code', 'DC+SF')
            ->call('updatePart', 'grammage_id', $g250);

        foreach ([
            'i_paper_price_override' => 255000, 'i_dc_persen_override' => 5.30, 'i_panjang_plano_cm' => 120, 'i_lebar_plano_cm' => 104,
            'i_cut_per_plano' => 4, 'i_isi_per_lb' => 2, 'i_uk_cetak_p_cm' => 60, 'i_uk_cetak_l_cm' => 52, 'i_jumlah_warna' => 4,
            'i_ongkos_cetak_rim' => 500000, 'i_ongkos_plong_lb' => 75, 'i_ongkos_lem_pc' => 30, 'i_ongkos_kupas_pc' => 10,
            'i_ongkos_qc_pack_pc' => 10, 'i_ongkos_kirim_pc' => 10, 'pakai_laminasi' => 1, 'i_sf_ef_rate_override' => 2600,
            'i_lam_pakai_ukuran_sf' => 1, 'i_sheet_panjang_cm' => 59, 'i_sheet_lebar_cm' => 51, 'i_ongkos_lam_rp_cm2_sf' => 0.065,
            'i_film_rate' => 60, 'i_film_faktor' => 0.05, 'i_pisau_faktor' => 0.05, 'i_pisau_rate_rp' => 600000, 'i_ongkos_plate_warna' => 90000,
        ] as $field => $value) {
            $form->call('updatePart', $field, $value);
        }

        $form->call('addCoating');
        $coating = $costing->parts()->first()->coatings()->firstOrFail();
        $form->call('updateCoating', $coating->id_coating, 'index_coating', 0.065)
            ->assertSet('engineError', '')
            ->assertSee('Harga Pokok (HPP)/PC');

        $tier = $costing->priceTiers()->where('is_primary', true)->firstOrFail();
        $this->assertEqualsWithDelta(1487.4648, (float) $tier->c_hpp_pc, 0.0005);
        $this->assertSame('CALCULATED', $costing->fresh()->doc_status);

        // Pager part: tambah part ke-2 lalu kembali ke part 1.
        $form->call('addPart')->assertSet('partNo', 2)->call('goPart', 1)->assertSet('partNo', 1);
        $form->call('goPart', 2)->call('removePart')->assertSet('partNo', 1);

        // Reverse -> Integrated + Inquiry BARU, angka part tidak berubah.
        $form->call('reverse');
        $costing->refresh();
        $this->inquiryId = $costing->inquiry_id;
        $this->assertSame(IqpCosting::MODE_INTEGRATED, $costing->mode);
        $this->assertSame('NEW', IqpInquiry::find($costing->inquiry_id)->inquiry_type);
        $this->assertEqualsWithDelta(1487.4648, (float) $costing->priceTiers()->where('is_primary', true)->value('c_hpp_pc'), 0.0005);
    }

    public function test_breakdown_hidden_for_login_outside_constant(): void
    {
        $other = SecUser::whereNotIn('login', ['johiwan', 'wiwik2'])->where('activate', true)->firstOrFail();
        $this->actingAs($other);

        $costing = IqpCosting::create(['mode' => 'INSTANT', 'calc_type' => 'NEW', 'doc_status' => 'DRAFT', 'created_by' => 'test']);
        $this->costingId = $costing->id_costing;

        Livewire::test('pages::inquiry.costing-instant', ['costing' => $costing->id_costing])
            ->call('addPart')
            ->assertSee('PROCOST_FORMULA_LOGINS');
    }
}
