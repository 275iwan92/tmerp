<?php

namespace Tests\Feature;

use App\Models\Inventory\InvTransfer;
use App\Models\Inventory\InvTransferLine;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\RmStockcard;
use App\Models\Inventory\RmStockD;
use App\Models\Master\MatRaw;
use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Services\Inventory\ItemAverageCost;
use App\Services\Inventory\StockDocumentService;
use App\Services\Inventory\StockPostingService;
use App\Services\Inventory\StockReversalService;
use App\Services\Inventory\StockTransferService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Average per item (INV_AVG_SCOPE = ITEM, D13 / D15): satu Avg per item lintas bin; BIN = perilaku lama. Menulis ke database sungguhan
// (gudang uji sendiri + satu bahan tanpa kartu stok), bersih di tearDown(); nilai konstanta dikembalikan.
class AvgPerItemTest extends TestCase
{
    private const BY = 'test_avg_per_item';

    private WhWarehouse $warehouse;

    private WhLoc $a;

    private WhLoc $b;

    private MatRaw $material;

    private mixed $curStock;

    private mixed $scope;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
        $this->warehouse = WhWarehouse::create(['warehouse_code' => 'ZZI-A', 'warehouse_name' => 'Test ZZI-A', 'warehouse_type' => 'MIX', 'activate' => true,
            'length_m' => 20, 'width_m' => 10, 'floor_count' => 1, 'shape' => 'RECT', 'created_by' => self::BY, 'updated_by' => self::BY]);
        $bin = fn (string $code) => WhLoc::create(['warehouse_id' => $this->warehouse->id_warehouse, 'location_code' => $code, 'row_code' => $code, 'col_code' => '1',
            'stack_code' => '1', 'is_usable' => true, 'activate' => true, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $this->a = $bin('ZZI-A-01');
        $this->b = $bin('ZZI-A-02');
        $this->material = MatRaw::withoutGlobalScopes()->where('activate', true)->whereNotIn('material_id', RmStockcard::query()->whereNotNull('material_id')->distinct()->pluck('material_id'))
            ->orderBy('material_id')->firstOrFail();
        $this->curStock = DB::connection('tm2mst')->table('mat_raw')->where('material_id', $this->material->material_id)->value('cur_stock');
        $this->scope = DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'INV_AVG_SCOPE')->value('constant_text');
        $this->scope('ITEM');
    }

    protected function tearDown(): void
    {
        $this->clean();
        DB::connection('tm2mst')->table('mat_raw')->where('material_id', $this->material->material_id)->update(['cur_stock' => $this->curStock]);
        DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'INV_AVG_SCOPE')->update(['constant_text' => $this->scope]);
        $this->assertSame(0, RmStockcard::query()->where('create_by', self::BY)->count());
        $this->assertSame(0, RmStock::query()->where('create_by', self::BY)->count());
        $this->assertSame(0, InvTransfer::query()->where('created_by', self::BY)->count());
        $this->assertSame(0, DB::connection('tm2inv')->table(ItemAverageCost::OPEN_TABLE)->where('item_id', $this->material->material_id)->where('stock_class', 'RM')->count());
        $this->assertSame(0, WhWarehouse::query()->where('created_by', self::BY)->count());
        parent::tearDown();
    }

    private function clean(): void
    {
        RmStockcard::query()->where('create_by', self::BY)->delete();
        InvTransfer::query()->where('created_by', self::BY)->delete();
        RmStockD::query()->where('create_by', self::BY)->delete();
        RmStock::query()->where('create_by', self::BY)->delete();
        if (isset($this->material)) {
            DB::connection('tm2inv')->table(ItemAverageCost::OPEN_TABLE)->where('stock_class', 'RM')->where('item_id', $this->material->material_id)->delete();
        }
        $ids = WhWarehouse::query()->where('created_by', self::BY)->pluck('id_warehouse')->all();
        WhLoc::query()->whereIn('warehouse_id', $ids ?: [0])->delete();
        WhWarehouse::query()->whereIn('id_warehouse', $ids ?: [0])->delete();
    }

    private function scope(string $scope): void
    {
        DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'INV_AVG_SCOPE')->update(['constant_text' => $scope]);
    }

    private function postDoc(int $docType, WhLoc $bin, float $qty, int $daysAgo, float $price = 0, string $owner = 'COMPANY'): array
    {
        $head = ['doc_no' => 'ZZI/'.(++$this->seq).'/'.uniqid(), 'doc_date' => Carbon::now()->subDays($daysAgo), 'doc_type' => $docType, 'doc_cat' => 'STOCK', 'ref_doc_type' => 'MANUAL',
            'warehouse_id' => $this->warehouse->id_warehouse, 'notes' => 'test avg per item'];
        $line = ['material_id' => $this->material->material_id, 'qty' => $qty, 'uom_code' => 'KG', 'unit_price' => $price, 'location_id' => $bin->location_id];
        $docId = app(StockDocumentService::class)->saveDraft('RM', null, $head, [$line], self::BY);
        if ($owner !== 'COMPANY') {
            RmStockD::query()->where('doc_id', $docId)->update(['owner_type' => $owner, 'owner_id' => 1]);
        }
        $r = app(StockPostingService::class)->postDocument('RM', $docId, self::BY) + ['doc_id' => $docId];
        $this->assertTrue($r['posted'], (string) $r['error']);

        return $r;
    }

    private function in(WhLoc $bin, float $qty, float $price, int $daysAgo): array
    {
        return $this->postDoc(1, $bin, $qty, $daysAgo, $price);
    }

    private function out(WhLoc $bin, float $qty, int $daysAgo): array
    {
        return $this->postDoc(-1, $bin, $qty, $daysAgo);
    }

    private function cards(?WhLoc $bin = null)
    {
        $q = RmStockcard::query()->where('material_id', $this->material->material_id)->orderBy('doc_date')->orderBy('doc_id');

        return $bin ? $q->where('location_id', $bin->location_id)->get() : $q->get();
    }

    private function lastOutValue(): float
    {
        $c = $this->cards()->where('qty_out', '>', 0)->last();

        return (float) $c->qty_out * (float) $c->avg_unitprice;
    }

    // Jumlah nilai terakhir semua bin = nilai item.
    private function itemValue(): float
    {
        return (float) $this->cards()->groupBy('location_id')->sum(fn ($rows) => (float) $rows->last()->value_eb);
    }

    private function transfer(WhLoc $from, WhLoc $to, float $qty): void
    {
        $t = InvTransfer::create(['transfer_date' => now()->toDateString(), 'from_warehouse_id' => $this->warehouse->id_warehouse, 'to_warehouse_id' => $this->warehouse->id_warehouse,
            'created_by' => self::BY, 'updated_by' => self::BY]);
        InvTransferLine::create(['transfer_id' => $t->id_transfer, 'line_no' => 1, 'stock_class' => 'RM', 'item_id' => $this->material->material_id,
            'item_code' => $this->material->material_code, 'item_name' => $this->material->material_name, 'from_location_id' => $from->location_id,
            'to_location_id' => $to->location_id, 'qty' => $qty, 'uom_code' => 'KG', 'created_by' => self::BY, 'updated_by' => self::BY]);
        $this->assertNull(app(StockTransferService::class)->confirm($t, self::BY));
    }

    private function assertChained(WhLoc $bin): void
    {
        $prev = 0.0;
        foreach ($this->cards($bin) as $c) {
            $this->assertEqualsWithDelta($prev, (float) $c->qty_bb, 0.0001, 'BB = EB of the previous row.');
            $prev = (float) $c->qty_eb;
        }
    }

    public function test_t1_second_receipt_at_a_higher_price_raises_the_item_average_across_two_bins(): void
    {
        $this->in($this->a, 1000, 15000, 5);
        $this->assertEqualsWithDelta(15000, (float) $this->cards()->last()->avg_unitprice, 0.0001);
        $this->in($this->b, 500, 16500, 4);

        $this->assertEqualsWithDelta(15500, (float) $this->cards()->last()->avg_unitprice, 0.0001);
        $this->assertEqualsWithDelta(23250000, $this->itemValue(), 0.01);
    }

    public function test_t2_issue_uses_the_item_average_whichever_bin_is_taken(): void
    {
        $this->in($this->a, 1000, 15000, 5);
        $this->in($this->b, 500, 16500, 4);
        $this->out($this->a, 800, 3);
        $this->assertEqualsWithDelta(12400000, $this->lastOutValue(), 0.01);
        $this->out($this->b, 100, 2);
        $this->assertEqualsWithDelta(1550000, $this->lastOutValue(), 0.01);
        $this->assertEqualsWithDelta(23250000 - 12400000 - 1550000, $this->itemValue(), 0.01);
        $this->assertChained($this->a);
        $this->assertChained($this->b);
    }

    public function test_t3_transfer_between_bins_keeps_item_value_and_average(): void
    {
        $this->in($this->a, 1000, 15000, 5);
        $this->in($this->b, 500, 16500, 4);
        $this->transfer($this->a, $this->b, 300);

        $this->assertEqualsWithDelta(15500, (float) $this->cards()->last()->avg_unitprice, 0.0001);
        $this->assertEqualsWithDelta(23250000, $this->itemValue(), 0.01);
        $this->assertEqualsWithDelta(700, (float) $this->cards($this->a)->last()->qty_eb, 0.0001);
        $this->assertEqualsWithDelta(800, (float) $this->cards($this->b)->last()->qty_eb, 0.0001);
        $this->assertEqualsWithDelta(300 * 15500, (float) $this->cards($this->b)->last()->value_in, 0.01);
        $this->assertChained($this->a);
        $this->assertChained($this->b);
    }

    public function test_t5_customer_owned_stock_is_not_valued_and_does_not_change_the_average(): void
    {
        $this->in($this->a, 1000, 15000, 5);
        $this->postDoc(1, $this->b, 200, 4, 99999, 'CUSTOMER');

        $last = $this->cards($this->b)->last();
        $this->assertEqualsWithDelta(15000, (float) $last->avg_unitprice, 0.0001);
        $this->assertEqualsWithDelta(0, (float) $last->value_eb, 0.01);
        $this->assertEqualsWithDelta(200, (float) $last->qty_eb, 0.0001);
        $this->assertEqualsWithDelta(15000000, $this->itemValue(), 0.01);
    }

    public function test_t6_reversal_of_an_issue_keeps_the_card_chained_and_the_average(): void
    {
        $this->in($this->a, 1000, 15000, 5);
        $this->in($this->b, 500, 16500, 4);
        $out = $this->out($this->b, 400, 3);

        $r = app(StockReversalService::class)->reverse('RM', $out['doc_id'], 'test', self::BY);
        $this->assertTrue($r['ok'], (string) $r['error']);

        $this->assertChained($this->b);
        $this->assertEqualsWithDelta(500, (float) $this->cards($this->b)->last()->qty_eb, 0.0001);
        $this->assertEqualsWithDelta(15500, (float) $this->cards()->last()->avg_unitprice, 0.0001);
        $this->assertEqualsWithDelta(23250000, $this->itemValue(), 0.01);
    }

    public function test_t7_scope_bin_keeps_the_old_per_bin_average(): void
    {
        $this->scope('BIN');
        $this->in($this->a, 1000, 15000, 5);
        $this->in($this->b, 500, 16500, 4);
        $this->out($this->b, 100, 3);

        $this->assertEqualsWithDelta(1650000, $this->lastOutValue(), 0.01, 'Per bin: bin B average stays 16.500.');
    }

    public function test_t8_t9_revalue_command_is_idempotent_and_keeps_the_total_value(): void
    {
        $this->scope('BIN');
        $this->in($this->a, 1000, 15000, 5);
        $this->in($this->b, 500, 16500, 4);
        $this->out($this->b, 100, 3);
        $before = $this->itemValue();
        $snapshot = fn () => $this->cards()->map(fn ($c) => [$c->doc_id, (string) $c->value_eb, (string) $c->avg_unitprice])->all();
        $rows = $snapshot();
        $count = fn () => DB::connection('tm2inv')->table(ItemAverageCost::OPEN_TABLE)->where('stock_class', 'RM')->where('item_id', $this->material->material_id)->count();

        $this->assertSame(0, Artisan::call('inv:revalue-per-item', ['--check' => true, '--class' => 'RM', '--item' => $this->material->material_id]));
        $this->assertSame(0, $count(), '--check writes nothing.');

        $this->assertSame(0, Artisan::call('inv:revalue-per-item', ['--apply' => true, '--class' => 'RM', '--item' => $this->material->material_id]));
        $this->assertSame(0, Artisan::call('inv:revalue-per-item', ['--apply' => true, '--class' => 'RM', '--item' => $this->material->material_id]));
        $this->assertSame(1, $count());
        $open = DB::connection('tm2inv')->table(ItemAverageCost::OPEN_TABLE)->where('stock_class', 'RM')->where('item_id', $this->material->material_id)->first();
        $this->assertEqualsWithDelta(1400, (float) $open->open_qty, 0.0001);
        $this->assertEqualsWithDelta($before, (float) $open->open_value, 0.01);
        $this->assertEqualsWithDelta($before / 1400, (float) $open->open_avg, 0.0001);
        $this->assertSame($rows, $snapshot(), 'History is not recalculated.');

        // Sesudah peralihan: keluar dari bin mana pun memakai Avg item dan total nilai konsisten.
        $this->scope('ITEM');
        $this->out($this->a, 140, 1);
        $this->assertEqualsWithDelta(140 * $before / 1400, $this->lastOutValue(), 0.01);
        $this->assertEqualsWithDelta($before * 0.9, $this->itemValue(), 0.01);
        $this->assertSame(0, Artisan::call('inv:revalue-per-item', ['--check' => true, '--class' => 'RM', '--item' => $this->material->material_id]));
    }

    public function test_backdated_receipt_reprices_the_later_issue_and_reports_it(): void
    {
        $this->in($this->a, 1000, 15000, 6);
        $this->in($this->b, 500, 16500, 5);
        $this->out($this->a, 800, 3);
        $r = $this->in($this->b, 600, 18000, 4);

        $this->assertEqualsWithDelta(800 * 34050000 / 2100, $this->lastOutValue(), 0.01);
        $this->assertSame(1, $r['revaluation']['rows']);
        $this->assertEqualsWithDelta(800 * 34050000 / 2100 - 12400000, $r['revaluation']['value'], 0.01);
        $this->assertEqualsWithDelta(34050000 - 800 * 34050000 / 2100, $this->itemValue(), 0.01);
    }
}
