<?php

namespace Tests\Feature;

use App\Models\Inventory\InvTransfer;
use App\Models\Inventory\InvTransferLine;
use App\Models\Inventory\RmStockcard;
use App\Models\Master\MatRaw;
use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Master\WhZone;
use App\Services\Inventory\BinGenerator;
use App\Services\Inventory\StockTransferService;
use App\Services\Inventory\WarehouseStock;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Stock Transfer (2026-10-02): posting pindah stok antar bin / gudang + aturan dead stock + garis luar gedung + pembuat bin.
// Menulis ke database sungguhan (gedung uji sendiri, satu bahan yang belum punya kartu stok) dan MEMBERSIHKAN sendiri di
// tearDown(); cur_stock master tidak disentuh (saldo awal uji ditulis langsung ke kartu, transfer tidak mengubah cur_stock).
class StockTransferServiceTest extends TestCase
{
    private const BY = 'test_stock_transfer';

    private WhWarehouse $from;

    private WhWarehouse $to;

    private WhLoc $src;

    private WhLoc $dst;

    private WhLoc $blocked;

    private MatRaw $material;

    private StockTransferService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
        $this->svc = new StockTransferService;
        $mk = fn (string $code) => WhWarehouse::create(['warehouse_code' => $code, 'warehouse_name' => 'Test '.$code, 'warehouse_type' => 'MIX', 'activate' => true,
            'length_m' => 20, 'width_m' => 10, 'floor_count' => 2, 'shape' => 'RECT', 'created_by' => self::BY, 'updated_by' => self::BY]);
        $this->from = $mk('ZZT-A');
        $this->to = $mk('ZZT-B');
        $bin = fn (WhWarehouse $w, string $code, bool $usable = true) => WhLoc::create(['warehouse_id' => $w->id_warehouse, 'location_code' => $code, 'row_code' => $code, 'col_code' => '1',
            'stack_code' => '1', 'is_usable' => $usable, 'capacity_kg' => 100, 'activate' => true, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $this->src = $bin($this->from, 'ZZT-A-01');
        $this->dst = $bin($this->to, 'ZZT-B-01');
        $this->blocked = $bin($this->to, 'ZZT-B-02', false);
        // Bahan tanpa kartu stok sama sekali: pergerakan terakhirnya = saldo awal uji (400 hari lalu) -> dead stock.
        $this->material = MatRaw::withoutGlobalScopes()->where('activate', true)->whereNotIn('material_id', RmStockcard::query()->whereNotNull('material_id')->distinct()->pluck('material_id'))
            ->orderBy('material_id')->firstOrFail();
        (new RmStockcard)->forceFill(['doc_date' => now()->subDays(400), 'material_id' => $this->material->material_id, 'doc_type' => 'BB', 'location_id' => $this->src->location_id,
            'warehouse_id' => $this->from->id_warehouse, 'qty' => 100, 'uom_code' => 'KG', 'reference_name' => 'test:stock_transfer', 'reference_detail_id' => $this->src->location_id,
            'qty_bb' => 0, 'qty_in' => 100, 'qty_out' => 0, 'qty_adj' => 0, 'qty_eb' => 100, 'avg_unitprice' => 50, 'value_eb' => 5000, 'create_by' => self::BY, 'update_by' => self::BY])->save();
    }

    protected function tearDown(): void
    {
        $this->clean();
        $this->assertSame(0, InvTransfer::query()->where('created_by', self::BY)->count());
        $this->assertSame(0, RmStockcard::query()->where('create_by', self::BY)->count());
        $this->assertSame(0, WhWarehouse::query()->where('created_by', self::BY)->count());
        $this->assertSame(0, WhLoc::query()->where('created_by', self::BY)->count());
        parent::tearDown();
    }

    private function clean(): void
    {
        RmStockcard::query()->where('create_by', self::BY)->delete();
        InvTransfer::query()->where('created_by', self::BY)->delete();
        $ids = WhWarehouse::query()->where('created_by', self::BY)->pluck('id_warehouse')->all();
        WhLoc::query()->whereIn('warehouse_id', $ids ?: [0])->delete();
        WhZone::query()->whereIn('warehouse_id', $ids ?: [0])->delete();
        WhWarehouse::query()->whereIn('id_warehouse', $ids ?: [0])->delete();
    }

    private function transfer(float $qty, ?WhLoc $to = null): InvTransfer
    {
        $t = InvTransfer::create(['transfer_date' => now()->toDateString(), 'from_warehouse_id' => $this->from->id_warehouse, 'to_warehouse_id' => $this->to->id_warehouse,
            'created_by' => self::BY, 'updated_by' => self::BY]);
        InvTransferLine::create(['transfer_id' => $t->id_transfer, 'line_no' => 1, 'stock_class' => 'RM', 'item_id' => $this->material->material_id,
            'item_code' => $this->material->material_code, 'item_name' => $this->material->material_name, 'from_location_id' => $this->src->location_id,
            'to_location_id' => ($to ?? $this->dst)->location_id, 'qty' => $qty, 'uom_code' => 'KG', 'created_by' => self::BY, 'updated_by' => self::BY]);

        return $t;
    }

    private function balance(WhLoc $bin): float
    {
        return $this->svc->balance('RM', (int) $this->material->material_id, null, (int) $bin->location_id);
    }

    public function test_confirm_moves_stock_and_value_between_bins_without_changing_the_item_total(): void
    {
        $cur = MatRaw::withoutGlobalScopes()->whereKey($this->material->material_id)->value('cur_stock');
        $t = $this->transfer(40);

        $this->assertNull($this->svc->confirm($t, self::BY));
        $t->refresh();

        $this->assertSame('CONFIRMED', $t->status());
        $this->assertMatchesRegularExpression('/^TF\d{7}$/', (string) $t->transfer_no);
        $this->assertSame(60.0, $this->balance($this->src));
        $this->assertSame(40.0, $this->balance($this->dst));
        $in = RmStockcard::query()->where('reference_name', StockTransferService::REF.':IN')->where('reference_detail_id', $t->lines->first()->id_transfer_line)->firstOrFail();
        $this->assertSame('TF', $in->doc_type);
        $this->assertSame((int) $this->to->id_warehouse, (int) $in->warehouse_id);
        $this->assertEqualsWithDelta(50.0, (float) $in->avg_unitprice, 0.0001);
        $this->assertEqualsWithDelta(2000.0, (float) $in->value_eb, 0.0001);
        $this->assertEqualsWithDelta(50.0, (float) $t->lines->first()->unit_cost, 0.0001);
        $this->assertEquals($cur, MatRaw::withoutGlobalScopes()->whereKey($this->material->material_id)->value('cur_stock'), 'A transfer must not change the item total.');
        // Kartu stok (laporan) membaca nomor transfernya.
        $row = DB::connection('tm2inv')->table('v_inv_movement')->where('stock_class', 'RM')->where('doc_id', $in->doc_id)->first();
        $this->assertSame($t->transfer_no, $row->doc_no);
        $this->assertSame('TF', $row->doc_cat);
    }

    public function test_moving_dead_stock_does_not_make_it_alive(): void
    {
        $dead = fn (WhLoc $bin) => WarehouseStock::positions([$bin->location_id])->first();
        $this->assertTrue($dead($this->src)['dead']);

        $t = $this->transfer(100);
        $this->assertNull($this->svc->confirm($t, self::BY));

        $moved = $dead($this->dst);
        $this->assertTrue($moved['dead'], 'Stock moved today is still dead: a transfer is not a movement.');
        $this->assertGreaterThanOrEqual(399, $moved['idle_days']);
        $this->assertTrue($t->lines()->first()->was_dead_stock);
        $summary = WarehouseStock::binSummaries(collect([$this->dst->refresh()]))[$this->dst->location_id];
        $this->assertTrue($summary['dead']);
        $this->assertSame('FULL', $summary['status']); // 100 kg di bin berkapasitas 100 kg
    }

    public function test_confirm_is_refused_when_stock_is_short_or_the_bin_is_blocked(): void
    {
        $this->assertNotNull($this->svc->confirm($this->transfer(100.5), self::BY));
        $this->assertNotNull($this->svc->confirm($this->transfer(10, $this->blocked), self::BY));
        $this->assertNotNull($this->svc->confirm($this->transfer(0), self::BY));

        $this->assertSame(100.0, $this->balance($this->src));
        $this->assertSame(0, RmStockcard::query()->where('reference_name', 'like', StockTransferService::REF.'%')->where('create_by', self::BY)->count());
    }

    public function test_cancel_brings_the_stock_back_and_needs_a_reason(): void
    {
        $t = $this->transfer(25);
        $this->assertNull($this->svc->confirm($t, self::BY));

        $this->assertNotNull($this->svc->cancel($t, '  ', self::BY));
        $this->assertNull($this->svc->cancel($t, 'Wrong bin', self::BY));

        $this->assertSame('CANCELLED', $t->refresh()->status());
        $this->assertSame(100.0, $this->balance($this->src));
        $this->assertSame(0.0, $this->balance($this->dst));
        $this->assertNotNull($this->svc->cancel($t, 'Again', self::BY));
    }

    public function test_building_outline_follows_its_shape(): void
    {
        $w = new WhWarehouse(['length_m' => 30, 'width_m' => 20, 'shape' => 'RECT']);
        $this->assertSame(600.0, $w->floorArea());

        $l = new WhWarehouse(['length_m' => 30, 'width_m' => 20, 'shape' => 'L', 'notch_length_m' => 10, 'notch_width_m' => 8, 'notch_corner' => 'NE']);
        $this->assertCount(6, $l->outlinePoints());
        $this->assertSame(520.0, $l->floorArea());
        $this->assertSame([30.0, 20.0], array_map('floatval', $l->extent()));

        $c = new WhWarehouse(['shape' => 'CUSTOM', 'outline' => '0,0; 12,0; 12,5; 0,5']);
        $this->assertSame(60.0, $c->floorArea());
        $this->assertSame([], (new WhWarehouse(['shape' => 'CUSTOM', 'outline' => 'nonsense']))->outlinePoints());
    }

    public function test_bin_generator_makes_floor_and_rack_bins_and_skips_existing_ones(): void
    {
        $zone = fn (string $code, string $mode) => WhZone::create(['warehouse_id' => $this->from->id_warehouse, 'floor_no' => 1, 'zone_code' => $code, 'zone_name' => 'Test '.$code,
            'zone_type' => 'STORAGE', 'storage_mode' => $mode, 'map_x' => 0, 'map_y' => 0, 'map_w' => 5, 'map_h' => 5, 'created_by' => self::BY, 'updated_by' => self::BY]);

        $floor = BinGenerator::generate($zone('PPR', 'FLOOR'), ['rows_from' => 1, 'rows_to' => 2, 'cols_from' => 1, 'cols_to' => 3, 'capacity_kg' => 3000], self::BY);
        $this->assertSame(['created' => 6, 'skipped' => 0], $floor);
        $this->assertTrue(WhLoc::query()->where('location_code', 'ZZT-A-PPR-L02-03')->where('capacity_kg', 3000)->exists());

        $again = BinGenerator::generate(WhZone::query()->where('warehouse_id', $this->from->id_warehouse)->where('zone_code', 'PPR')->first(),
            ['rows_from' => 1, 'rows_to' => 3, 'cols_from' => 1, 'cols_to' => 3], self::BY);
        $this->assertSame(['created' => 3, 'skipped' => 6], $again);

        $rack = BinGenerator::generate($zone('INK', 'RACK'), ['rows_from' => 1, 'rows_to' => 1, 'cols_from' => 1, 'cols_to' => 2, 'levels' => 3], self::BY);
        $this->assertSame(6, $rack['created']);
        $this->assertTrue(WhLoc::query()->where('location_code', 'ZZT-A-INK-R01-02-3')->exists());

        $this->assertSame(1, BinGenerator::generate($zone('RCV', 'AREA'), [], self::BY)['created']);
    }
}
