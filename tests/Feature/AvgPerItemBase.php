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
use App\Services\Inventory\StockTransferService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Dasar uji Average per item: gudang uji sendiri + satu bahan tanpa kartu stok. Average per item (INV_AVG_SCOPE = ITEM, D13 / D15): satu Avg per item lintas bin; BIN = perilaku lama. Menulis ke database sungguhan
// (gudang uji sendiri + satu bahan tanpa kartu stok), bersih di tearDown(); nilai konstanta dikembalikan.
abstract class AvgPerItemBase extends TestCase
{
    protected const BY = 'test_avg_per_item';

    protected WhWarehouse $warehouse;

    protected WhLoc $a;

    protected WhLoc $b;

    protected MatRaw $material;

    protected mixed $curStock;

    protected mixed $scope;

    protected int $seq = 0;

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

    protected function clean(): void
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

    protected function scope(string $scope): void
    {
        DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'INV_AVG_SCOPE')->update(['constant_text' => $scope]);
    }

    protected function postDoc(int $docType, WhLoc $bin, float $qty, int $daysAgo, float $price = 0, string $owner = 'COMPANY'): array
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

    protected function in(WhLoc $bin, float $qty, float $price, int $daysAgo): array
    {
        return $this->postDoc(1, $bin, $qty, $daysAgo, $price);
    }

    protected function out(WhLoc $bin, float $qty, int $daysAgo): array
    {
        return $this->postDoc(-1, $bin, $qty, $daysAgo);
    }

    protected function cards(?WhLoc $bin = null)
    {
        $q = RmStockcard::query()->where('material_id', $this->material->material_id)->orderBy('doc_date')->orderBy('doc_id');

        return $bin ? $q->where('location_id', $bin->location_id)->get() : $q->get();
    }

    protected function lastOutValue(): float
    {
        $c = $this->cards()->where('qty_out', '>', 0)->last();

        return (float) $c->qty_out * (float) $c->avg_unitprice;
    }

    // Jumlah nilai terakhir semua bin = nilai item.
    protected function itemValue(): float
    {
        return (float) $this->cards()->groupBy('location_id')->sum(fn ($rows) => (float) $rows->last()->value_eb);
    }

    protected function transfer(WhLoc $from, WhLoc $to, float $qty): void
    {
        $t = InvTransfer::create(['transfer_date' => now()->toDateString(), 'from_warehouse_id' => $this->warehouse->id_warehouse, 'to_warehouse_id' => $this->warehouse->id_warehouse,
            'created_by' => self::BY, 'updated_by' => self::BY]);
        InvTransferLine::create(['transfer_id' => $t->id_transfer, 'line_no' => 1, 'stock_class' => 'RM', 'item_id' => $this->material->material_id,
            'item_code' => $this->material->material_code, 'item_name' => $this->material->material_name, 'from_location_id' => $from->location_id,
            'to_location_id' => $to->location_id, 'qty' => $qty, 'uom_code' => 'KG', 'created_by' => self::BY, 'updated_by' => self::BY]);
        $this->assertNull(app(StockTransferService::class)->confirm($t, self::BY));
    }

    protected function assertChained(WhLoc $bin): void
    {
        $prev = 0.0;
        foreach ($this->cards($bin) as $c) {
            $this->assertEqualsWithDelta($prev, (float) $c->qty_bb, 0.0001, 'BB = EB of the previous row.');
            $prev = (float) $c->qty_eb;
        }
    }
}
