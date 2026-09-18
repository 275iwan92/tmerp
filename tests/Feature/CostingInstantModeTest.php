<?php

namespace Tests\Feature;

use App\Models\Master\CustCustomer;
use App\Models\Master\MatGrammage;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

// Mode Instant (standalone, keputusan user 2026-09-18): iqp_costing.mode=INSTANT,
// inquiry_id NULL, "ID sendiri" -- lihat docs/inquiry_product_costing/
// procost_formula_library.md §I & CLAUDE.md diskusi sesi ini. Cek grid berdiri
// sendiri + form (komponen SAMA dgn Integrated, pages::inquiry.costing, cabang
// via $costing->mode) bisa dipakai penuh: header manual, part, hitung, tier manual.
class CostingInstantModeTest extends TestCase
{
    protected ?int $costingId = null;

    protected function tearDown(): void
    {
        if ($this->costingId) {
            $partIds = DB::connection('tm2sdi')->table('iqp_costing_part')->where('costing_id', $this->costingId)->pluck('id_costing_part');
            DB::connection('tm2sdi')->table('iqp_costing_part_coating')->whereIn('costing_part_id', $partIds)->delete();
            DB::connection('tm2sdi')->table('iqp_costing_part')->where('costing_id', $this->costingId)->delete();
            DB::connection('tm2sdi')->table('iqp_costing_price_tier')->where('costing_id', $this->costingId)->delete();
            DB::connection('tm2sdi')->table('iqp_costing')->where('id_costing', $this->costingId)->delete();
        }

        parent::tearDown();
    }

    public function test_instant_grid_and_form_full_flow(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        // 1) Grid instant render OK kosong/tidak.
        Livewire::test('pages::inquiry.costing-instant-list')->assertOk();

        // 2) "+ New Record" -> baris mode INSTANT, inquiry_id NULL.
        Livewire::test('pages::inquiry.costing-instant-list')->call('newInstant');
        $costing = IqpCosting::where('mode', IqpCosting::MODE_INSTANT)->latest('id_costing')->firstOrFail();
        $this->costingId = $costing->id_costing;
        $this->assertNull($costing->inquiry_id);
        $this->assertSame('DRAFT', $costing->doc_status);

        // 3) Form yang SAMA (pages::inquiry.costing) dibuka via {costing}, bukan {inquiry}.
        $form = Livewire::test('pages::inquiry.costing', ['costing' => $costing->id_costing]);
        $form->assertOk();

        // Tier default 1 baris (qty 1000) sudah dibuat mount() krn Instant tak
        // punya iqp_tier sumber.
        $this->assertSame(1, $costing->priceTiers()->count());

        // 4) Header manual (customer/product/dimensi) -- field yg Integrated read-only.
        $cust = CustCustomer::where('activate', true)->firstOrFail();
        $form->call('updateHeaderField', 'customer_id', $cust->id_customer)
            ->call('updateHeaderField', 'product_name_draft', 'Dus Test Instant')
            ->call('updateHeaderField', 'i_dim_panjang_mm', 100)
            ->call('updateHeaderField', 'i_dim_lebar_mm', 80)
            ->call('updateHeaderField', 'i_dim_tinggi_mm', 60);

        $costing->refresh();
        $this->assertSame($cust->id_customer, $costing->customer_id);
        $this->assertSame('Dus Test Instant', $costing->product_name_draft);
        $this->assertEquals(100, $costing->i_dim_panjang_mm);

        // 5) Part DC manual + Hitung -- harus hasilkan angka (engine jalan normal
        // persis sama dgn mode Integrated, cuma sumber datanya beda).
        $grammage = MatGrammage::where('activate', true)->firstOrFail();
        $form->call('addPart', 'DC');
        $part = $costing->parts()->firstOrFail();

        $form->call('updatePart', $part->id_costing_part, 'grammage_id', $grammage->id_grammage)
            ->call('updatePart', $part->id_costing_part, 'i_panjang_plano_cm', 65)
            ->call('updatePart', $part->id_costing_part, 'i_lebar_plano_cm', 90)
            ->call('updatePart', $part->id_costing_part, 'i_ongkos_cetak_rim', 500000)
            ->call('updatePart', $part->id_costing_part, 'i_ongkos_plong_lb', 75)
            ->call('updatePart', $part->id_costing_part, 'i_ongkos_lem_pc', 30)
            ->call('hitung')
            ->assertSet('engineError', '');

        $part->refresh();
        $this->assertNotNull($part->c_total_part_cost_pc);
        $this->assertGreaterThan(0, (float) $part->c_total_part_cost_pc);

        // 6) Tier manual: tambah, edit qty+margin, hitung ulang ladder, hapus.
        $form->call('addTier');
        $this->assertSame(2, $costing->priceTiers()->count());

        $tier2 = $costing->priceTiers()->latest('id_price_tier')->firstOrFail();
        $form->call('updateTier', $tier2->id_price_tier, 'qty_pcs', 500)
            ->call('updateTier', $tier2->id_price_tier, 'margin_pct', 15)
            ->call('hitung');

        $tier2->refresh();
        $this->assertEquals(500, $tier2->qty_pcs);
        $this->assertNotNull($tier2->price_per_pcs);

        $form->call('removeTier', $tier2->id_price_tier);
        $this->assertSame(1, $costing->priceTiers()->count());

        // 7) Reverse masih stub -- pastikan TIDAK melempar exception & mode tetap INSTANT.
        $form->call('reverseToIntegrated');
        $costing->refresh();
        $this->assertSame(IqpCosting::MODE_INSTANT, $costing->mode);
    }

    // Regresi cepat: mode Integrated (route {inquiry}) di komponen yang sama
    // harus tetap jalan seperti sebelum perubahan ini (tidak boleh ada gap
    // di guard mode/isIntegrated() yang menutup jalur lama).
    public function test_integrated_mode_still_works_on_shared_component(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test('pages::inquiry.form', ['mode' => 'NEW'])
            ->set('inquiry_date', now()->toDateString())
            ->set('product_draft_name', 'Box Test Instant-Regression')
            ->call('setProspect', true)
            ->set('prospect_name', 'Prospek Test Instant-Regression')
            ->set('tiers.0.qty', 1000)
            ->set('parts.0.part_name', 'Body')
            ->call('save')
            ->assertHasNoErrors();

        $inquiry = IqpInquiry::where('product_draft_name', 'Box Test Instant-Regression')->latest('id_inquiry')->firstOrFail();

        $form = Livewire::test('pages::inquiry.costing', ['inquiry' => $inquiry->id_inquiry]);
        $form->assertOk();

        $costing = IqpCosting::where('inquiry_id', $inquiry->id_inquiry)->firstOrFail();
        $this->assertSame(IqpCosting::MODE_INTEGRATED, $costing->mode);
        $this->assertSame($inquiry->id_inquiry, $costing->inquiry_id);

        // cleanup
        $costingIds = DB::connection('tm2sdi')->table('iqp_costing')->where('inquiry_id', $inquiry->id_inquiry)->pluck('id_costing');
        DB::connection('tm2sdi')->table('iqp_costing_price_tier')->whereIn('costing_id', $costingIds)->delete();
        DB::connection('tm2sdi')->table('iqp_costing')->where('inquiry_id', $inquiry->id_inquiry)->delete();
        DB::connection('tm2sdi')->table('iqp_part')->where('inquiry_id', $inquiry->id_inquiry)->delete();
        DB::connection('tm2sdi')->table('iqp_tier')->where('inquiry_id', $inquiry->id_inquiry)->delete();
        DB::connection('tm2sdi')->table('iqp_inquiry')->where('id_inquiry', $inquiry->id_inquiry)->delete();
    }
}
