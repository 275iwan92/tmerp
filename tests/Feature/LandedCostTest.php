<?php

namespace Tests\Feature;

use App\Models\Acc\FinVoucher;
use App\Models\Inventory\InvLandedCost;
use App\Models\Inventory\InvRepostLog;
use App\Models\Inventory\StockOnhand;
use App\Services\Inventory\Valuation\LandedCostService;
use App\Services\Inventory\Valuation\ValuationAccounts;
use App\Services\Inventory\Valuation\ValuationRefused;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PurgesLandedCards;
use Tests\TestCase;

// Landed cost (D17): pembagian menurut nilai / qty, bagian stok vs bagian yang sudah keluar, jurnal, koreksi bertumpuk, pembatalan, penutupan selisih ke HPP.
// Jalankan terhadap database dummy: powershell -NoProfile -File tools/test-ddt.ps1 tests/Feature/LandedCostTest.php
class LandedCostTest extends TestCase
{
    use PurgesLandedCards;

    private const USER = 'landedtest';

    /** @var list<int> */
    private array $docIds = [];

    private int $m1;

    private int $m2;

    private float $onhand1;

    private float $onhand2;

    protected function setUp(): void
    {
        parent::setUp();
        $items = DB::connection('tm2mst')->table('mat_raw')->whereNotNull('stock_asset_account_id')->orderBy('material_id')->pluck('material_id');
        $found = [];
        foreach ($items as $id) {
            $q = (float) StockOnhand::of('RM')->where('material_id', $id)->sum('qty_onhand');
            if ($q > 0) {
                $found[$id] = $q;
            }
            if (count($found) === 2) {
                break;
            }
        }
        if (count($found) < 2) {
            $this->markTestSkipped('Needs two raw materials with stock on hand and an inventory account.');
        }
        [$this->m1, $this->m2] = array_keys($found);
        [$this->onhand1, $this->onhand2] = array_values($found);
    }

    protected function tearDown(): void
    {
        $voucherIds = DB::connection('tm2acc')->table('fin_voucher')->where('created_by', self::USER)->pluck('voucher_id');
        DB::connection('tm2acc')->table('fin_voucher')->whereIn('voucher_id', $voucherIds)->update(['reversal_of_id' => null, 'reversed_by_id' => null]);
        DB::connection('tm2acc')->table('fin_voucher_line')->whereIn('voucher_id', $voucherIds)->delete();
        DB::connection('tm2acc')->table('fin_voucher')->whereIn('voucher_id', $voucherIds)->delete();
        InvRepostLog::query()->where('created_by', self::USER)->delete();
        $this->purgeLandedCards($this->docIds);
        InvLandedCost::query()->whereIn('id_landed_cost', $this->docIds)->delete();
        parent::tearDown();
    }

    // Kasus: baris 1 diterima 2 x saldo sekarang (separuh sudah keluar), baris 2 diterima setengah saldo (semua masih ada). Biaya 1.000 menurut nilai 6.000 : 4.000.
    private function draft(float $amount = 1000.0, string $basis = 'VALUE'): InvLandedCost
    {
        $service = app(LandedCostService::class);
        $doc = $service->create(['doc_date' => now()->toDateString(), 'cost_type' => 'FREIGHT', 'description' => 'test freight', 'amount' => $amount, 'alloc_basis' => $basis], self::USER);
        $this->docIds[] = $doc->id_landed_cost;
        $doc->lines()->create(['line_no' => 1, 'pv_id' => 900000001, 'pv_line_id' => 900000001, 'item_id' => $this->m1, 'qty_received' => $this->onhand1 * 2, 'base_value' => 6000]);
        $doc->lines()->create(['line_no' => 2, 'pv_id' => 900000001, 'pv_line_id' => 900000002, 'item_id' => $this->m2, 'qty_received' => $this->onhand2 / 2, 'base_value' => 4000]);

        return $doc->refresh();
    }

    public function test_allocates_by_value_and_splits_stock_from_issued(): void
    {
        $plan = app(LandedCostService::class)->allocate($this->draft());

        $this->assertSame([600.0, 400.0], array_column($plan['lines'], 'allocated'));
        $this->assertSame([300.0, 400.0], array_column($plan['lines'], 'to_stock'));
        $this->assertSame([300.0, 0.0], array_column($plan['lines'], 'to_variance'));
        $this->assertSame(700.0, $plan['to_stock']);
        $this->assertSame(300.0, $plan['to_variance']);
    }

    public function test_allocates_by_quantity_and_the_rounding_goes_to_the_last_line(): void
    {
        $doc = $this->draft(100.0, 'QTY');
        $doc->lines()->delete();
        // Tiga baris sama besar tidak habis dibagi: bagian terakhir menyerap sisa pembulatan.
        foreach ([1, 2, 3] as $n) {
            $doc->lines()->create(['line_no' => $n, 'pv_id' => 900000001, 'pv_line_id' => 900000010 + $n, 'item_id' => $this->m1, 'qty_received' => 3, 'base_value' => 1]);
        }
        $plan = app(LandedCostService::class)->allocate($doc->refresh());

        $this->assertSame([33.33, 33.33, 33.34], array_column($plan['lines'], 'allocated'));
        $this->assertSame(100.0, round(array_sum(array_column($plan['lines'], 'allocated')), 2));
    }

    public function test_confirm_posts_a_balanced_journal_with_stock_variance_and_payable(): void
    {
        $service = app(LandedCostService::class);
        $doc = $this->draft();
        $voucher = $service->confirm($doc, self::USER);
        $doc->refresh();

        $this->assertSame('CONFIRMED', $doc->status());
        $this->assertMatchesRegularExpression('/^LC\d{2}-\d{5}$/', $doc->doc_no);
        $this->assertSame(700.0, $doc->amount_to_stock);
        $this->assertSame(300.0, $doc->amount_to_variance);
        $this->assertTrue($doc->card_synced, 'the landed cost value is written to the stock cards');

        $lines = $voucher->lines()->get();
        $this->assertSame(1000.0, round((float) $lines->sum('debit'), 2));
        $this->assertSame(1000.0, round((float) $lines->sum('credit'), 2));
        $byAccount = fn (int $id, string $side) => round((float) $lines->where('account_id', $id)->sum($side), 2);
        $this->assertSame(1000.0, $byAccount(ValuationAccounts::id('INV_ACC_LANDED_PAYABLE'), 'credit'));
        $this->assertSame(300.0, $byAccount(ValuationAccounts::id('INV_ACC_LANDED_VARIANCE'), 'debit'));
        $stockAccounts = array_unique([ValuationAccounts::stockFor($this->m1), ValuationAccounts::stockFor($this->m2)]);
        $this->assertSame(700.0, round(array_sum(array_map(fn ($a) => $byAccount($a, 'debit'), $stockAccounts)), 2));
    }

    public function test_confirm_is_refused_without_lines(): void
    {
        $empty = app(LandedCostService::class)->create(['amount' => 50], self::USER);
        $this->docIds[] = $empty->id_landed_cost;

        $this->expectException(ValuationRefused::class);
        app(LandedCostService::class)->confirm($empty, self::USER);
    }

    public function test_a_confirmed_document_cannot_be_confirmed_or_edited_again(): void
    {
        $service = app(LandedCostService::class);
        $doc = $this->draft();
        $service->confirm($doc, self::USER);

        $this->expectException(ValuationRefused::class);
        $service->saveHeader($doc->refresh(), ['amount' => 5], self::USER);
    }

    public function test_revise_adds_a_stacked_correction_and_leaves_the_original_journal_alone(): void
    {
        $service = app(LandedCostService::class);
        $doc = $this->draft();
        $original = $service->confirm($doc, self::USER);
        $before = $original->lines()->get()->map(fn ($l) => [$l->account_id, (float) $l->debit, (float) $l->credit])->all();

        $correction = $service->revise($doc->refresh(), 1500.0, 'Forwarder bill corrected', self::USER);

        $this->assertNotNull($correction);
        $this->assertSame('CORRECTION', $correction->journal_kind);
        $this->assertSame($before, $original->refresh()->lines()->get()->map(fn ($l) => [$l->account_id, (float) $l->debit, (float) $l->credit])->all(), 'the old journal must not change');
        // Total debit koreksi = tambahan 500 menurut nilai 60 : 40 (stok 350 + selisih 150).
        $this->assertSame(500.0, round((float) $correction->lines()->sum('debit'), 2));
        $this->assertSame(500.0, round((float) $correction->lines()->sum('credit'), 2));
        $this->assertSame(1500.0, $doc->refresh()->amount);
        $log = InvRepostLog::query()->where('original_voucher_id', $original->voucher_id)->first();
        $this->assertSame('CORRECTION', $log->repost_kind);
        $this->assertSame([1000.0, 1500.0, 500.0], [$log->amount_before, $log->amount_after, $log->amount_delta]);
    }

    public function test_cancel_reverses_the_journal_and_is_logged(): void
    {
        $service = app(LandedCostService::class);
        $doc = $this->draft();
        $original = $service->confirm($doc, self::USER);

        $reversal = $service->cancel($doc->refresh(), 'Booked on the wrong receipt', self::USER);

        $this->assertSame('CANCELLED', $doc->refresh()->status());
        $this->assertSame($original->voucher_id, $reversal->reversal_of_id);
        $this->assertNotNull(FinVoucher::withoutGlobalScopes()->find($original->voucher_id)->reversed_by_id);
        $this->assertSame('REVERSAL', InvRepostLog::query()->where('original_voucher_id', $original->voucher_id)->value('repost_kind'));
    }

    public function test_close_variance_moves_the_issued_part_to_cogs_once(): void
    {
        $service = app(LandedCostService::class);
        $doc = $this->draft();
        $service->confirm($doc, self::USER);

        $month = now()->format('Ym');
        $voucher = $service->closeVariance($month, self::USER, [$doc->id_landed_cost]);

        $this->assertSame(300.0, round((float) $voucher->lines()->sum('debit'), 2));
        $this->assertNotNull($doc->refresh()->variance_voucher_id);
        $this->assertNull($service->closeVariance($month, self::USER, [$doc->id_landed_cost]), 'a second run finds nothing left to close');
        $this->expectException(ValuationRefused::class);
        $service->cancel($doc, 'Too late to cancel this one', self::USER);
    }
}
