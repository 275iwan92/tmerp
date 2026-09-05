<?php

namespace Tests\Feature;

use App\Models\Sdi\IqpInquiry;
use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

// Permintaan user 2026-09-05: 8 stat box di grid Inquiry Hub (2 baris x 4) --
// baris 1 backlog "belum confirm" per tipe + total tahun ini, baris 2 backlog
// "sudah diberi harga (PRICE_APPROVED) tapi belum ke customer" per tipe +
// total sudah di-approve Direksi (is_approved) tahun ini.
class InquiryHubStatBoxTest extends TestCase
{
    protected array $inquiryIds = [];

    protected function tearDown(): void
    {
        if ($this->inquiryIds) {
            DB::connection('tm2sdi')->table('iqp_tier')->whereIn('inquiry_id', $this->inquiryIds)->delete();
            DB::connection('tm2sdi')->table('iqp_inquiry')->whereIn('id_inquiry', $this->inquiryIds)->delete();
        }

        parent::tearDown();
    }

    public function test_hub_renders_and_stat_boxes_count_correctly(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $thisYear = now()->toDateString();

        // 1 NEW belum confirm (harus kehitung statUnconfirmedByType('NEW')).
        $newUnconfirmed = IqpInquiry::create([
            'inquiry_no' => 'IQTESTHUB01', 'inquiry_date' => $thisYear, 'product_draft_name' => 'Test Hub NEW Unconfirmed',
            'inquiry_type' => 'NEW', 'sub_state' => 'DRAFT', 'stat_confirm' => false, 'is_approved' => false,
        ]);
        $this->inquiryIds[] = $newUnconfirmed->id_inquiry;

        // 1 NEW sudah confirm + CANCELLED -- TIDAK boleh kehitung di manapun (mati).
        $newCancelled = IqpInquiry::create([
            'inquiry_no' => 'IQTESTHUB02', 'inquiry_date' => $thisYear, 'product_draft_name' => 'Test Hub NEW Cancelled',
            'inquiry_type' => 'NEW', 'sub_state' => 'CANCELLED', 'stat_confirm' => false, 'is_approved' => false,
        ]);
        $this->inquiryIds[] = $newCancelled->id_inquiry;

        // 1 REVISE PRICE_APPROVED (harus kehitung statPricedNotQuotedByType('REVISE') + statApprovedThisYear).
        $revisePriced = IqpInquiry::create([
            'inquiry_no' => 'IQTESTHUB03', 'inquiry_date' => $thisYear, 'product_draft_name' => 'Test Hub REVISE Priced',
            'inquiry_type' => 'REVISE', 'sub_state' => 'PRICE_APPROVED', 'stat_confirm' => true, 'is_approved' => true,
        ]);
        $this->inquiryIds[] = $revisePriced->id_inquiry;

        // 1 RECALC QUOTED + is_approved=1 -- sudah lewat approve (kehitung statApprovedThisYear)
        // TAPI sub_state bukan PRICE_APPROVED lagi -- TIDAK boleh kehitung statPricedNotQuotedByType('RECALC').
        $recalcQuoted = IqpInquiry::create([
            'inquiry_no' => 'IQTESTHUB04', 'inquiry_date' => $thisYear, 'product_draft_name' => 'Test Hub RECALC Quoted',
            'inquiry_type' => 'RECALC', 'sub_state' => 'QUOTED', 'stat_confirm' => true, 'is_approved' => true,
        ]);
        $this->inquiryIds[] = $recalcQuoted->id_inquiry;

        $test = Livewire::test('pages::inquiry.hub')->assertOk();
        $test->assertSee('NEW belum confirm')
            ->assertSee('REVISE belum confirm')
            ->assertSee('RECALC belum confirm')
            ->assertSee('Total inquiry tahun ini')
            ->assertSee('NEW sudah harga, belum ke customer')
            ->assertSee('REVISE sudah harga, belum ke customer')
            ->assertSee('RECALC sudah harga, belum ke customer')
            ->assertSee('Sudah di-approve tahun ini');
        $component = $test->instance();

        $this->assertGreaterThanOrEqual(1, $component->statUnconfirmedByType('NEW'));
        $this->assertSame(0, IqpInquiry::whereKey($newCancelled->id_inquiry)->where('stat_confirm', false)->whereNotIn('sub_state', ['CANCELLED', 'LOST'])->count(), 'CANCELLED tidak boleh ikut query belum-confirm');
        $this->assertGreaterThanOrEqual(1, $component->statPricedNotQuotedByType('REVISE'));
        $this->assertSame(0, IqpInquiry::whereKey($recalcQuoted->id_inquiry)->where('sub_state', 'PRICE_APPROVED')->count(), 'QUOTED tidak boleh ikut query priced-not-quoted');
        $this->assertGreaterThanOrEqual(2, $component->statApprovedThisYear);
        $this->assertGreaterThanOrEqual(4, $component->statTotalThisYear);
    }
}
