<?php

namespace Tests\Feature;

use App\Models\Inventory\InvLandedCost;
use App\Models\Inventory\InvRepostLog;
use App\Models\Pur\PvLine;
use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Layar Inventory > Valuation & Period: semua layar terbuka, dan alur Landed Cost lewat route (buat -> tambah penerimaan -> Confirm -> Cancel).
// Menulis ke database: dokumen uji dan jurnalnya dibersihkan di tearDown(). Jalankan terhadap database dummy: powershell -NoProfile -File tools/test-ddt.ps1 tests/Feature/LandedCostScreenTest.php
class LandedCostScreenTest extends TestCase
{
    private const MARK = 'zz-test-landed-screen';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(SecUser::query()->where('is_admin', 1)->firstOrFail());
    }

    protected function tearDown(): void
    {
        $docs = InvLandedCost::query()->where('description', self::MARK)->get();
        $vouchers = $docs->pluck('voucher_id')->filter()->all();
        $vouchers = array_merge($vouchers, DB::connection('tm2acc')->table('fin_voucher')->whereIn('voucher_id', $vouchers)->pluck('reversed_by_id')->filter()->all());
        DB::connection('tm2acc')->table('fin_voucher')->whereIn('voucher_id', $vouchers)->update(['reversal_of_id' => null, 'reversed_by_id' => null]);
        DB::connection('tm2acc')->table('fin_voucher_line')->whereIn('voucher_id', $vouchers)->delete();
        DB::connection('tm2acc')->table('fin_voucher')->whereIn('voucher_id', $vouchers)->delete();
        InvRepostLog::query()->whereIn('source_no', $docs->pluck('doc_no')->filter()->all())->delete();
        InvLandedCost::query()->whereIn('id_landed_cost', $docs->pluck('id_landed_cost'))->delete();
        $this->assertSame(0, InvLandedCost::query()->where('description', self::MARK)->count());
        parent::tearDown();
    }

    public function test_every_valuation_screen_opens(): void
    {
        foreach (['inv-valuation', 'landed-cost', 'obsolete-stock', 'inv-grir-aging', 'inv-reconcile', 'inv-repost', 'inv-stock-aging'] as $slug) {
            $this->get('/v2/s/'.$slug)->assertOk();
        }
        $this->get('/v2/s/landed-cost?new=1')->assertOk()->assertSee('name="alloc_basis"', false);
        $this->get('/v2/s/inv-valuation')->assertOk()->assertSee('Inventory Period');
        $this->get('/v2/s/obsolete-stock')->assertOk()->assertSee('Post provision');
    }

    public function test_create_add_receipt_confirm_and_cancel_from_the_screen(): void
    {
        $line = PvLine::query()->whereHas('receipt', fn ($q) => $q->where('status', 'POSTED')->where('i_receipt_type', 'PO'))->where('is_stock', 1)->whereNotNull('item_id')
            ->where('c_qty_accepted', '>', 0)->first();
        if (! $line) {
            $this->markTestSkipped('Needs a posted goods receipt with a stock line.');
        }
        $day = max(now()->toDateString(), (string) $line->receipt->i_doc_date);

        $created = $this->post('/v2/inv-valuation/landed', ['doc_date' => $day, 'cost_type' => 'FREIGHT', 'alloc_basis' => 'VALUE', 'amount' => '1500.5', 'description' => self::MARK]);
        $doc = InvLandedCost::query()->where('description', self::MARK)->firstOrFail();
        $created->assertRedirect('/v2/s/landed-cost?id='.$doc->id_landed_cost);
        $this->get('/v2/s/landed-cost?id='.$doc->id_landed_cost)->assertOk()->assertSee('DRAFT-'.$doc->id_landed_cost);

        $this->post('/v2/inv-valuation/landed/'.$doc->id_landed_cost.'/receipt', ['pv_id' => $line->pv_id])->assertSessionHasNoErrors();
        $this->assertGreaterThan(0, $doc->lines()->count());

        $this->post('/v2/inv-valuation/landed/'.$doc->id_landed_cost.'/confirm')->assertSessionHasNoErrors();
        $doc->refresh();
        $this->assertSame('CONFIRMED', $doc->status());
        $this->assertSame(1500.5, round($doc->amount_to_stock + $doc->amount_to_variance, 2));
        $this->get('/v2/s/landed-cost?id='.$doc->id_landed_cost)->assertOk()->assertSee($doc->doc_no)->assertSee('Revise Amount');
        $this->get('/v2/s/inv-repost')->assertOk();

        $this->post('/v2/inv-valuation/landed/'.$doc->id_landed_cost.'/cancel', ['reason' => 'Booked on the wrong receipt'])->assertSessionHasNoErrors();
        $this->assertSame('CANCELLED', $doc->refresh()->status());
    }

    public function test_a_refused_step_shows_the_message_and_changes_nothing(): void
    {
        $this->post('/v2/inv-valuation/landed', ['doc_date' => now()->toDateString(), 'cost_type' => 'FREIGHT', 'alloc_basis' => 'VALUE', 'amount' => '10', 'description' => self::MARK]);
        $doc = InvLandedCost::query()->where('description', self::MARK)->firstOrFail();

        $this->post('/v2/inv-valuation/landed/'.$doc->id_landed_cost.'/confirm')->assertSessionHasErrors('landed');
        $this->assertSame('DRAFT', $doc->refresh()->status());
    }

    public function test_period_actions_report_a_refusal(): void
    {
        $this->post('/v2/inv-valuation/period/open', ['period' => '190001'])->assertSessionHasErrors('period');
        $this->post('/v2/inv-valuation/period/explode', ['period' => '190001'])->assertNotFound();
    }
}
