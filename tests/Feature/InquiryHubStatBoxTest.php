<?php

namespace Tests\Feature;

use App\Models\Sdi\IqpInquiry;
use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

// Stat box Inquiry Hub (versi 2026-09-28): Not Confirm, Ready to Quote,
// Processed by Pro-Dev, Processed by Pro-Cost.
class InquiryHubStatBoxTest extends TestCase
{
    protected array $inquiryIds = [];

    private ?string $origLang = null;

    // Label kotak yang dicek berbahasa Indonesia; akun johiwan bisa sedang
    // ber-lang 'en' (pilihan user) -- paksa 'id' selama test, kembalikan di
    // tearDown (disetujui user 2026-09-23; layar/flow Inquiry tidak disentuh).
    protected function setUp(): void
    {
        parent::setUp();
        $this->origLang = SecUser::where('login', 'johiwan')->value('lang_code');
        SecUser::where('login', 'johiwan')->update(['lang_code' => 'id']);
    }

    protected function tearDown(): void
    {
        SecUser::where('login', 'johiwan')->update(['lang_code' => $this->origLang]);

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

        // Kotak statistik Hub dirombak 2026-09-28 (a676d0e/1f0f929): Not Confirm, Ready to Quote,
        // Processed by Pro-Dev, Processed by Pro-Cost -- Win/Loss pindah ke Quotation.
        $test = Livewire::test('pages::inquiry.hub')->assertOk();
        $test->assertSee('Not Confirm')
            ->assertSee('Ready to Quote')
            ->assertSee('Processed by Pro-Dev')
            ->assertSee('Processed by Pro-Cost');
        $component = $test->instance();

        $expected = IqpInquiry::where('stat_confirm', false)->whereNotIn('sub_state', ['CANCELLED', 'LOST'])->count();
        $this->assertSame($expected, $component->statUnconfirmed);
        $this->assertGreaterThanOrEqual(1, $component->statUnconfirmed);
        $this->assertSame(0, IqpInquiry::whereKey($newCancelled->id_inquiry)->where('stat_confirm', false)->whereNotIn('sub_state', ['CANCELLED', 'LOST'])->count(), 'CANCELLED tidak boleh ikut query belum-confirm');
        $this->assertSame(IqpInquiry::whereHas('costing')->count(), $component->statProcost);
        $this->assertSame(IqpInquiry::whereHas('parts', fn ($p) => $p->whereNotNull('prod_part_id'))->count(), $component->statProdev);
    }
}
