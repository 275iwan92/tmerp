<?php

namespace Tests\Feature;

use App\Models\Acc\FinVoucher;
use App\Models\Inventory\InvPeriod;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\RmStockcard;
use App\Models\Inventory\RmStockD;
use App\Models\Master\MatRaw;
use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Sys\SecUser;
use App\Services\Inventory\StockDocumentService;
use App\Services\Inventory\StockPostingService;
use App\Services\Inventory\StockReversalService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Cancel dokumen stok POSTED lewat Pembalik (user 2026-10-08, D26 / T17): contoh user -- saldo 5.000, Stock In 1.000 (EB 6.000),
// Stock Out salah ketik 5.000 (EB 1.000), Pembalik (EB 6.000), Stock Out benar 500 (EB 5.500). Baris asal tetap ada, kartu
// bersambung, tidak ada Adjustment. Ditolak: periode tertutup, saldo jadi minus, dokumen yang sudah dibatalkan / pembaliknya /
// Adjustment / buatan proses lain. Menulis ke database sungguhan (gudang uji sendiri + satu bahan tanpa kartu stok), bersih di tearDown().
class StockReversalServiceTest extends TestCase
{
    private const BY = 'test_stock_reversal';

    private WhWarehouse $warehouse;

    private WhLoc $bin;

    private MatRaw $material;

    private mixed $curStock;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
        $this->warehouse = WhWarehouse::create(['warehouse_code' => 'ZZV-A', 'warehouse_name' => 'Test ZZV-A', 'warehouse_type' => 'MIX', 'activate' => true,
            'length_m' => 20, 'width_m' => 10, 'floor_count' => 1, 'shape' => 'RECT', 'created_by' => self::BY, 'updated_by' => self::BY]);
        $this->bin = WhLoc::create(['warehouse_id' => $this->warehouse->id_warehouse, 'location_code' => 'ZZV-A-01', 'row_code' => 'ZZV-A-01', 'col_code' => '1',
            'stack_code' => '1', 'is_usable' => true, 'activate' => true, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $this->material = MatRaw::withoutGlobalScopes()->where('activate', true)->whereNotIn('material_id', RmStockcard::query()->whereNotNull('material_id')->distinct()->pluck('material_id'))
            ->orderBy('material_id')->firstOrFail();
        $this->curStock = DB::connection('tm2mst')->table('mat_raw')->where('material_id', $this->material->material_id)->value('cur_stock');
    }

    protected function tearDown(): void
    {
        $this->clean();
        DB::connection('tm2mst')->table('mat_raw')->where('material_id', $this->material->material_id)->update(['cur_stock' => $this->curStock]);
        $this->assertSame(0, RmStockcard::query()->where('create_by', self::BY)->count());
        $this->assertSame(0, RmStock::query()->where('create_by', self::BY)->count());
        $this->assertSame(0, RmStock::query()->where('notes', 'like', 'Reversal of ZZV/%')->count(), 'pembalik uji lewat layar harus bersih');
        $this->assertSame(0, RmStockcard::query()->where('create_by', 'johiwan')->where('create_log', '>=', now()->startOfDay())->whereNotIn('reference_name', ['tm2inv.inv_transfer_line:OUT', 'tm2inv.inv_transfer_line:IN'])->whereNotIn('reference_detail_id', RmStockD::query()->select('doc_detail_id'))->count(), 'baris Kartu Stok yatim dari uji harus bersih');
        $this->assertSame(0, RmStockD::query()->where('create_by', self::BY)->count());
        $this->assertSame(0, InvPeriod::query()->where('created_by', self::BY)->count());
        $this->assertSame(0, FinVoucher::withoutGlobalScopes()->where('created_by', self::BY)->count());
        $this->assertSame(0, WhWarehouse::query()->where('created_by', self::BY)->count());
        parent::tearDown();
    }

    private function clean(): void
    {
        // Pembalik yang dibuat lewat route layar dibuat oleh user yang login (bukan BY): dicari lewat dokumen asalnya sebelum dokumen asal dihapus.
        $origins = RmStock::query()->where('create_by', self::BY)->pluck('doc_id');
        $viaScreen = RmStock::query()->whereIn('reverses_doc_id', $origins)->where('create_by', '!=', self::BY)->pluck('doc_id');
        RmStockcard::query()->where('reference_name', app(StockPostingService::class)->referenceName('RM'))->whereIn('reference_detail_id', RmStockD::query()->whereIn('doc_id', $viaScreen)->pluck('doc_detail_id'))->delete();
        RmStockD::query()->whereIn('doc_id', $viaScreen)->delete();
        RmStock::query()->whereIn('doc_id', $viaScreen)->delete();
        // Pembalik + dokumen asal + salinan Duplicate: semuanya dibuat oleh BY.
        RmStockcard::query()->where('create_by', self::BY)->delete();
        RmStockD::query()->where('create_by', self::BY)->delete();
        RmStock::query()->where('create_by', self::BY)->delete();
        InvPeriod::query()->where('created_by', self::BY)->delete();
        $vouchers = FinVoucher::withoutGlobalScopes()->where('created_by', self::BY)->get();
        if ($vouchers->isNotEmpty()) {
            FinVoucher::withoutGlobalScopes()->whereKey($vouchers->modelKeys())->update(['reversed_by_id' => null, 'reversal_of_id' => null]);
            foreach ($vouchers as $v) {
                $v->lines()->delete();
                $v->delete();
            }
        }
        $ids = WhWarehouse::query()->where('created_by', self::BY)->pluck('id_warehouse')->all();
        WhLoc::query()->whereIn('warehouse_id', $ids ?: [0])->delete();
        WhWarehouse::query()->whereIn('id_warehouse', $ids ?: [0])->delete();
    }

    private function postDoc(int $docType, float $qty, Carbon $at, float $price = 15, string $cat = 'STOCK', string $ref = 'MANUAL'): array
    {
        $head = ['doc_no' => 'ZZV/'.(++$this->seq).'/'.uniqid(), 'doc_date' => $at, 'doc_type' => $docType, 'doc_cat' => $cat, 'ref_doc_type' => $ref,
            'warehouse_id' => $this->warehouse->id_warehouse, 'notes' => 'test stock reversal'];
        $line = ['material_id' => $this->material->material_id, 'qty' => $qty, 'uom_code' => 'SHEET', 'unit_price' => $price, 'location_id' => $this->bin->location_id];
        $docId = app(StockDocumentService::class)->saveDraft('RM', null, $head, [$line], self::BY);

        return app(StockPostingService::class)->postDocument('RM', $docId, self::BY) + ['doc_id' => $docId];
    }

    private function cards()
    {
        return RmStockcard::query()->where('material_id', $this->material->material_id)->where('location_id', $this->bin->location_id)->orderBy('doc_date')->orderBy('doc_id')->get();
    }

    // Kartu bersambung: BB tiap baris = EB baris sebelumnya.
    private function assertChained(float $last): void
    {
        $prev = 0.0;
        foreach ($this->cards() as $c) {
            $this->assertEqualsWithDelta($prev, (float) $c->qty_bb, 0.0001, 'BB = EB of the previous row.');
            $prev = (float) $c->qty_eb;
        }
        $this->assertEqualsWithDelta($last, $prev, 0.0001);
    }

    private function reverse(int $docId, string $reason = 'Kesalahan transaksi'): array
    {
        return app(StockReversalService::class)->reverse('RM', $docId, $reason, self::BY);
    }

    public function test_t17_reversal_of_a_wrong_stock_out_keeps_the_card_chained(): void
    {
        $now = now();
        $this->assertTrue($this->postDoc(1, 5000, $now->copy()->subDays(3))['posted']);
        $this->assertTrue($this->postDoc(1, 1000, $now->copy()->subDays(2))['posted']);
        $wrong = $this->postDoc(-1, 5000, $now->copy()->subDay());
        $this->assertTrue($wrong['posted']);
        $this->assertChained(1000);

        $r = $this->reverse($wrong['doc_id']);
        $this->assertTrue($r['ok'], (string) $r['error']);
        $this->assertChained(6000);

        $original = RmStock::find($wrong['doc_id']);
        $reversal = RmStock::find($r['doc_id']);
        $this->assertTrue($original->stat_cancel);
        $this->assertSame('CANCELLED', $original->flow_status);
        $this->assertSame('Kesalahan transaksi', $original->cancel_reason);
        $this->assertSame(self::BY, $original->cancelled_by);
        $this->assertSame((int) $reversal->doc_id, (int) $original->reversed_by_doc_id);
        $this->assertSame((int) $original->doc_id, (int) $reversal->reverses_doc_id);
        $this->assertSame('POSTED', $reversal->flow_status);
        $this->assertSame(1, (int) $reversal->doc_type);
        $this->assertStringStartsWith('RV/', $reversal->doc_no);
        // Baris asal tetap ada di kartu stok.
        $this->assertSame(1, RmStockcard::query()->where('reference_detail_id', $original->lines->first()->doc_detail_id)->where('qty_out', 5000)->count());

        $this->assertTrue($this->postDoc(-1, 500, now())['posted']);
        $this->assertChained(5500);
        // Harga rata-rata tidak berubah (keluar pada harga keluar asalnya, masuk lagi pada harga yang sama).
        $this->assertEqualsWithDelta(15.0, (float) $this->cards()->last()->avg_unitprice, 0.0001);
        $this->assertSame(0, $this->cards()->filter(fn ($c) => (float) $c->qty_adj !== 0.0)->count(), 'No Adjustment row.');
    }

    public function test_a_second_cancel_and_a_cancel_of_the_reversal_are_refused_and_nothing_is_written(): void
    {
        $in = $this->postDoc(1, 1000, now()->subDays(2));
        $r = $this->reverse($in['doc_id']);
        $this->assertTrue($r['ok'], (string) $r['error']);
        $rows = $this->cards()->count();
        $docs = RmStock::query()->where('create_by', self::BY)->count();

        $again = $this->reverse($in['doc_id']);
        $reversal = $this->reverse($r['doc_id']);

        $this->assertFalse($again['ok']);
        $this->assertFalse($reversal['ok']);
        $this->assertSame($rows, $this->cards()->count());
        $this->assertSame($docs, RmStock::query()->where('create_by', self::BY)->count());
        $this->assertEqualsWithDelta(0, (float) $this->cards()->last()->qty_eb, 0.0001);
    }

    public function test_a_reversal_that_would_make_the_balance_negative_is_refused_whole(): void
    {
        $in = $this->postDoc(1, 1000, now()->subDays(3));
        $this->assertTrue($this->postDoc(-1, 800, now()->subDay())['posted']);
        $rows = $this->cards()->count();

        $r = $this->reverse($in['doc_id']);

        $this->assertFalse($r['ok']);
        $this->assertNotEmpty($r['error']);
        $this->assertSame($rows, $this->cards()->count());
        $this->assertSame('POSTED', RmStock::find($in['doc_id'])->flow_status);
        $this->assertNull(RmStock::find($in['doc_id'])->reversed_by_doc_id);
        $this->assertSame(2, RmStock::query()->where('create_by', self::BY)->count(), 'The refused reversal draft is removed.');
        $this->assertChained(200);
    }

    public function test_a_reversal_in_a_closed_inventory_period_is_refused(): void
    {
        $in = $this->postDoc(1, 1000, now()->subDays(2));
        InvPeriod::create(['i_period_code' => 'ZZV01', 'i_date_start' => now()->subDay()->toDateString(), 'i_date_end' => now()->addDay()->toDateString(), 'i_status' => 'CLOSED',
            'created_by' => self::BY, 'updated_by' => self::BY]);

        $r = $this->reverse($in['doc_id']);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString(now()->addDay()->toDateString(), (string) $r['error']);
        $this->assertSame('POSTED', RmStock::find($in['doc_id'])->flow_status);
        $this->assertSame(1, RmStockcard::query()->where('create_by', self::BY)->count());
    }

    public function test_adjustment_and_documents_from_other_processes_are_refused(): void
    {
        $this->postDoc(1, 1000, now()->subDays(3));
        $adj = $this->postDoc(-1, -100, now()->subDays(2), 0, 'AD');
        $pv = $this->postDoc(1, 50, now()->subDay(), 15, 'STOCK', 'PV');

        $this->assertFalse($this->reverse($adj['doc_id'])['ok']);
        $r = $this->reverse($pv['doc_id']);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('PV', (string) $r['error']);
        $this->assertFalse($this->reverse($adj['doc_id'], '   ')['ok'], 'A blank reason is refused.');
        $this->assertSame('POSTED', RmStock::find($pv['doc_id'])->flow_status);
    }

    public function test_duplicate_makes_a_draft_copy_with_a_new_number(): void
    {
        $in = $this->postDoc(1, 700, now()->subDays(2));
        $this->assertTrue($this->reverse($in['doc_id'])['ok']);

        $copyId = app(StockReversalService::class)->duplicate('RM', $in['doc_id'], self::BY);
        $copy = RmStock::with('lines')->find($copyId);
        $orig = RmStock::find($in['doc_id']);

        $this->assertSame('DRAFT', $copy->flow_status);
        $this->assertFalse((bool) $copy->stat_confirm);
        $this->assertNotSame($orig->doc_no, $copy->doc_no);
        $this->assertSame(1, (int) $copy->doc_type);
        $this->assertEqualsWithDelta(700, (float) $copy->lines->first()->qty, 0.0001);
        $this->assertNull(app(StockReversalService::class)->duplicate('RM', (int) $orig->reversed_by_doc_id, self::BY), 'A reversal itself is not duplicated.');
    }

    public function test_state_tells_the_screen_what_it_may_show(): void
    {
        $in = $this->postDoc(1, 100, now()->subDays(2));
        $svc = app(StockReversalService::class);
        $before = $svc->state('RM', $in['doc_id']);
        $this->assertTrue($before['can']);
        $this->assertStringStartsWith('Kesalahan transaksi no. ZZV/', $before['default_reason']);

        $r = $this->reverse($in['doc_id'], 'salah ketik');
        $after = $svc->state('RM', $in['doc_id']);
        $this->assertFalse($after['can']);
        $this->assertTrue($after['cancelled']);
        $this->assertSame($r['doc_no'], $after['reversal']['no']);
        $this->assertSame($in['doc_id'], $svc->state('RM', $r['doc_id'])['reverses']['id']);
    }

    public function test_the_stock_in_form_offers_cancel_and_shows_the_result(): void
    {
        $in = $this->postDoc(1, 100, now()->subDays(2));
        $this->actingAs(SecUser::where('is_admin', true)->firstOrFail());
        $form = route('v2.screen', 'inv-stock-doc-form').'?kind=in&class=RM&id='.$in['doc_id'];

        $this->get($form)->assertOk()->assertSee('Duplicate')->assertSee('Cancel Document')->assertSee('Kesalahan transaksi no. '.RmStock::find($in['doc_id'])->doc_no);
        $this->post(route('v2.inv.stockdoc.reverse', ['in', 'RM', $in['doc_id']]), ['reason' => ''])->assertSessionHasErrors('document');
        $this->post(route('v2.inv.stockdoc.reverse', ['in', 'RM', $in['doc_id']]), ['reason' => 'salah ketik'])->assertRedirect();
        $this->get($form)->assertOk()->assertSee('reversed by');
        $this->assertTrue(RmStock::find($in['doc_id'])->stat_cancel);
    }
}
