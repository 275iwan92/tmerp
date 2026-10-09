<?php

namespace Tests\Feature;

use App\Models\Inventory\InvLandedCost;
use App\Models\Inventory\InvRepostLog;
use App\Models\Inventory\RmStockcard;
use App\Services\Inventory\ItemAverageCost;
use App\Services\Inventory\StockCardChain;
use App\Services\Inventory\StockLedger;
use App\Services\Inventory\StockPostingService;
use App\Services\Inventory\Valuation\LandedCostCards;
use App\Services\Inventory\Valuation\LandedCostService;
use App\Services\Inventory\Valuation\ReconcileService;
use App\Services\Inventory\Valuation\ValuationAccounts;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

// Landed cost masuk kartu stok: baris khusus nilai (VA) bersambung, Average naik, rekonsiliasi nol, Revise / Cancel, idempoten,
// dan penilaian ulang pemakaian lewat RepostService. Jalankan: powershell -NoProfile -File tools/test-ddt.ps1 tests/Feature/LandedCostCardsTest.php
class LandedCostCardsTest extends AvgPerItemBase
{
    /** @var list<int> */
    private array $docIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (! ValuationAccounts::stockFor($this->material->material_id)) {
            $this->markTestSkipped('Needs a raw material with an inventory account.');
        }
    }

    protected function tearDown(): void
    {
        $this->purgeLandedCards($this->docIds);
        $this->purgeTestJournals(self::BY);
        InvLandedCost::query()->whereIn('id_landed_cost', $this->docIds)->delete();
        $this->assertSame(0, RmStockcard::query()->where('doc_type', StockCardChain::VALUE_ADJUSTMENT)->where('material_id', $this->material->material_id)->count());
        parent::tearDown();
    }

    // Satu baris diterima = saldo item sekarang (rasio 1: semua masuk persediaan).
    private function landed(float $amount = 600.0): InvLandedCost
    {
        $doc = app(LandedCostService::class)->create(['doc_date' => now()->toDateString(), 'cost_type' => 'FREIGHT', 'description' => 'cards test', 'amount' => $amount, 'alloc_basis' => 'VALUE'], self::BY);
        $this->docIds[] = $doc->id_landed_cost;
        $onhand = (float) $this->cards()->groupBy('location_id')->sum(fn ($rows) => (float) $rows->last()->qty_eb);
        $doc->lines()->create(['line_no' => 1, 'pv_id' => 900000001, 'pv_line_id' => 900000001, 'item_id' => $this->material->material_id, 'qty_received' => $onhand, 'base_value' => 1000]);

        return $doc->refresh();
    }

    private function va()
    {
        return $this->cards()->where('doc_type', StockCardChain::VALUE_ADJUSTMENT)->values();
    }

    private function stock(): void
    {
        $this->in($this->a, 1000, 100, 6);
        $this->in($this->b, 500, 120, 5);
    }

    public function test_item_scope_writes_a_chained_value_row_and_the_average_rises(): void
    {
        $this->stock();
        $this->assertEqualsWithDelta(160000, $this->itemValue(), 0.01);
        $doc = $this->landed(600);

        app(LandedCostService::class)->confirm($doc, self::BY);

        $va = $this->va();
        $this->assertCount(1, $va);
        $row = $va->first();
        $this->assertSame($this->a->location_id, (int) $row->location_id, 'the bin with most stock carries the value');
        $this->assertEqualsWithDelta(0.0, (float) $row->qty, 0.0001);
        $this->assertEqualsWithDelta(1000, (float) $row->qty_bb, 0.0001);
        $this->assertEqualsWithDelta(1000, (float) $row->qty_eb, 0.0001);
        $this->assertEqualsWithDelta(600, (float) $row->value_in, 0.0001);
        $this->assertEqualsWithDelta(160600, $this->itemValue(), 0.01);
        $this->assertEqualsWithDelta(160600 / 1500, ItemAverageCost::currentAvg('RM', $this->material->material_id, null), 0.0001);
        $this->assertChained($this->a);
        $this->assertChained($this->b);
        $this->assertTrue($doc->refresh()->card_synced);
        $this->assertSame(0.0, collect((new ReconcileService)->run(CarbonImmutable::today(), self::BY, false))->firstWhere('stock_class', 'RM')['landed_pending']);
    }

    public function test_bin_scope_also_accepts_the_value_row(): void
    {
        $this->scope('BIN');
        $this->stock();
        app(LandedCostService::class)->confirm($this->landed(600), self::BY);

        $this->assertEqualsWithDelta(100600, (float) $this->cards($this->a)->last()->value_eb, 0.01);
        $this->assertEqualsWithDelta(60000, (float) $this->cards($this->b)->last()->value_eb, 0.01);
        $this->assertChained($this->a);
    }

    public function test_the_stock_card_labels_the_row_as_a_value_adjustment(): void
    {
        $this->stock();
        $doc = $this->landed(600);
        app(LandedCostService::class)->confirm($doc, self::BY);

        StockLedger::forget();
        $moves = collect(StockLedger::between(CarbonImmutable::today(), CarbonImmutable::today()->addDay())->movements())->where('item_id', $this->material->material_id)->where('kind', 'Value adjustment');
        $this->assertCount(1, $moves);
        $this->assertEqualsWithDelta(600, $moves->first()['value'], 0.01);
        $this->assertSame($doc->refresh()->doc_no, DB::connection('tm2inv')->table('v_inv_movement')->where('doc_id', $this->va()->first()->doc_id)->where('stock_class', 'RM')->value('doc_no'));
    }

    public function test_sync_is_idempotent(): void
    {
        $this->stock();
        $doc = $this->landed(600);
        app(LandedCostService::class)->confirm($doc, self::BY);

        app(LandedCostCards::class)->sync($doc->refresh(), self::BY);
        app(LandedCostCards::class)->sync($doc->refresh(), self::BY);

        $this->assertCount(1, $this->va());
        $this->assertEqualsWithDelta(160600, $this->itemValue(), 0.01);
    }

    public function test_revise_adds_a_difference_row_and_cancel_reverses_the_value(): void
    {
        $this->stock();
        $service = app(LandedCostService::class);
        $doc = $this->landed(600);
        $service->confirm($doc, self::BY);

        $service->revise($doc->refresh(), 900.0, 'Forwarder bill corrected', self::BY);
        $this->assertSame([600.0, 300.0], $this->va()->map(fn ($r) => (float) $r->value_in)->all());
        $this->assertEqualsWithDelta(160900, $this->itemValue(), 0.01);

        $service->cancel($doc->refresh(), 'Booked on the wrong receipt', self::BY);
        $this->assertSame([600.0, 300.0, -900.0], $this->va()->map(fn ($r) => (float) $r->value_in)->all());
        $this->assertEqualsWithDelta(160000, $this->itemValue(), 0.01);
        $this->assertEqualsWithDelta(160000 / 1500, ItemAverageCost::currentAvg('RM', $this->material->material_id, null), 0.0001);
        $this->assertChained($this->a);
        $this->assertEqualsWithDelta(0.0, (float) $doc->lines()->first()->card_value, 0.0001);
    }

    // Baris keluar bertanggal sesudah baris nilai ikut dihargai ulang: jurnal koreksi bertumpuk, tercatat satu kali di laporan Repost.
    public function test_a_later_dated_issue_is_revalued_once_through_the_repost_service(): void
    {
        $this->in($this->a, 1000, 100, 6);
        $this->out($this->a, 400, -1); // dated tomorrow, after the value row
        $doc = $this->landed(1000);

        app(LandedCostService::class)->confirm($doc, self::BY);
        app(LandedCostCards::class)->sync($doc->refresh(), self::BY);

        $log = InvRepostLog::query()->where('repost_kind', 'REVALUATION')->where('created_by', self::BY)->get();
        $this->assertCount(1, $log, $log->toJson());
        $this->assertEqualsWithDelta(400 * (101000 / 1000 - 100), (float) $log->first()->amount_delta, 0.01);
    }

    // Penilaian ulang dari posting stok: penerimaan bertanggal mundur menaikkan Average, pengeluaran lama dijurnal koreksi sekali.
    public function test_a_back_dated_receipt_posts_one_revaluation_journal(): void
    {
        $this->in($this->a, 1000, 15000, 6);
        $this->in($this->b, 500, 16500, 5);
        $this->out($this->a, 800, 3);
        $r = $this->in($this->b, 600, 18000, 4);

        $log = InvRepostLog::query()->where('repost_kind', 'REVALUATION')->where('created_by', self::BY)->get();
        $this->assertCount(1, $log, $log->toJson());
        $this->assertEqualsWithDelta($r['revaluation']['value'], (float) $log->first()->amount_delta, 0.01);
        $again = app(StockPostingService::class)->postDocument('RM', $r['doc_id'], self::BY);
        $this->assertFalse($again['posted'], 'a posted document is not posted again');
        $this->assertSame(1, InvRepostLog::query()->where('repost_kind', 'REVALUATION')->where('created_by', self::BY)->count());
    }
}
