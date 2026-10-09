<?php

namespace Tests\Feature;

use App\Models\Inventory\InvTransfer;
use App\Models\Inventory\InvTransferLine;
use App\Models\Inventory\RmStockcard;
use App\Services\Inventory\ItemAverageCost;
use App\Services\Inventory\StockTransferService;
use App\Services\Inventory\WarehouseStock;
use App\Support\V2\Grid\Inv\ValuationCheckReport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Pembaca Average per item (readerAvg / currentAvg / WarehouseStock / nilai transfer / Valuation Check) dan rantai inkremental.
class AvgPerItemReadersTest extends AvgPerItemBase
{
    private function id(): int
    {
        return (int) $this->material->material_id;
    }

    private function snapshot(): array
    {
        return $this->cards()->map(fn ($c) => [$c->doc_id, (string) $c->qty_eb, (string) $c->value_eb, (string) $c->avg_unitprice, (string) $c->value_in])->all();
    }

    private function scenario(): void
    {
        $this->in($this->a, 1000, 15000, 9);
        $this->in($this->b, 500, 16500, 8);
        $this->out($this->a, 300, 7);
        $this->transfer($this->a, $this->b, 200);
        $this->in($this->a, 250, 17333.33, 5);
        $this->out($this->b, 400, 4);
        $this->in($this->b, 600, 18000, 7); // bertanggal mundur: disisipkan di tengah rantai
        $this->out($this->a, 100, 1);
    }

    public function test_incremental_chain_equals_full_recalculation(): void
    {
        $this->scenario();
        $after = $this->snapshot();

        $full = ItemAverageCost::recalculate('RM', $this->id(), null);
        $this->assertSame(0, $full['rows'], 'A full recalculation finds nothing to change.');
        $this->assertSame($after, $this->snapshot());
        $this->assertEqualsWithDelta($this->itemValue(), $full['value'], 0.005);
    }

    public function test_incremental_chain_does_not_touch_rows_before_the_changed_one(): void
    {
        $this->scenario();
        $first = $this->cards()->first();
        $first->forceFill(['avg_unitprice' => 1.5])->save(); // rusak sengaja, jauh sebelum dokumen baru
        $this->in($this->a, 10, 16000, 0);

        $this->assertEqualsWithDelta(1.5, (float) $this->cards()->first()->avg_unitprice, 0.00001, 'Posting a late document does not rewrite earlier rows.');
        ItemAverageCost::recalculate('RM', $this->id(), null);
        $this->assertGreaterThan(1000, (float) $this->cards()->first()->avg_unitprice, 'A full recalculation repairs it.');
    }

    public function test_incremental_chain_after_cutover_equals_full(): void
    {
        $this->scope('BIN');
        $this->in($this->a, 1000, 15000, 9);
        $this->in($this->b, 500, 16500, 8);
        $this->out($this->b, 100, 7);
        $this->assertSame(0, Artisan::call('inv:revalue-per-item', ['--apply' => true, '--class' => 'RM', '--item' => $this->id()]));
        $this->scope('ITEM');
        $this->in($this->a, 200, 17000, 3);
        $this->out($this->b, 50, 2);
        $this->in($this->b, 100, 16000, 6); // sebelum titik peralihan: tidak ikut rantai (sama seperti hitung penuh)
        $after = $this->snapshot();

        $this->assertSame(0, ItemAverageCost::recalculate('RM', $this->id(), null)['rows']);
        $this->assertSame($after, $this->snapshot());
    }

    public function test_current_average_follows_the_item_not_the_last_row_of_the_bin(): void
    {
        $this->in($this->b, 500, 16500, 6);
        $this->in($this->a, 1000, 15000, 5); // bin B tidak bergerak lagi: avg di baris terakhirnya basi
        $item = (float) $this->cards()->last()->avg_unitprice;
        $staleBin = (float) $this->cards($this->b)->last()->avg_unitprice;

        $this->assertNotEqualsWithDelta($item, $staleBin, 1.0);
        $this->assertEqualsWithDelta($item, ItemAverageCost::currentAvg('RM', $this->id(), null), 0.00001);
        $this->assertEqualsWithDelta($item, ItemAverageCost::readerAvg('RM', $this->id(), null, $staleBin), 0.00001);

        $position = WarehouseStock::positions([$this->b->location_id])->firstWhere('item_id', $this->id());
        $this->assertEqualsWithDelta($item, $position['avg'], 0.00001, 'Warehouse map shows the Average item.');
        $this->assertEqualsWithDelta(500 * 16500, $position['value'], 0.01, 'Bin value stays the bin value.');

        $this->scope('BIN');
        $this->assertEqualsWithDelta($staleBin, ItemAverageCost::readerAvg('RM', $this->id(), null, $staleBin), 0.00001, 'Scope Bin keeps the old reader.');
        $this->assertEqualsWithDelta($staleBin, WarehouseStock::positions([$this->b->location_id])->firstWhere('item_id', $this->id())['avg'], 0.00001);
    }

    public function test_current_average_right_after_cutover_is_the_cutover_average(): void
    {
        $this->scope('BIN');
        $this->in($this->a, 1000, 15000, 5);
        $this->in($this->b, 500, 16500, 4);
        $this->assertSame(0, Artisan::call('inv:revalue-per-item', ['--apply' => true, '--class' => 'RM', '--item' => $this->id()]));
        $this->scope('ITEM');

        $this->assertEqualsWithDelta(23250000 / 1500, ItemAverageCost::currentAvg('RM', $this->id(), null), 0.00001);
    }

    public function test_transfer_approval_value_uses_the_item_average(): void
    {
        $this->in($this->b, 500, 16500, 6);
        $this->in($this->a, 1000, 15000, 5);
        $t = InvTransfer::create(['transfer_date' => now()->toDateString(), 'from_warehouse_id' => $this->warehouse->id_warehouse, 'to_warehouse_id' => $this->warehouse->id_warehouse,
            'created_by' => self::BY, 'updated_by' => self::BY]);
        InvTransferLine::create(['transfer_id' => $t->id_transfer, 'line_no' => 1, 'stock_class' => 'RM', 'item_id' => $this->id(), 'item_code' => $this->material->material_code,
            'item_name' => $this->material->material_name, 'from_location_id' => $this->b->location_id, 'to_location_id' => $this->a->location_id, 'qty' => 100, 'uom_code' => 'KG',
            'created_by' => self::BY, 'updated_by' => self::BY]);

        $avg = (float) $this->cards()->last()->avg_unitprice;
        $this->assertEqualsWithDelta(100 * $avg, app(StockTransferService::class)->value($t->load('lines')), 0.01);
        $this->scope('BIN');
        $this->assertEqualsWithDelta(100 * 16500, app(StockTransferService::class)->value($t->load('lines')), 0.01);
    }

    public function test_valuation_check_shows_zero_difference_per_item(): void
    {
        $this->scenario();
        $row = collect((new ValuationCheckReport)->rows())->firstWhere('id', 'RM|'.$this->id().'|0');

        $this->assertNotNull($row);
        $this->assertSame('OK', $row['result']);
        $this->assertEqualsWithDelta(0.0, $row['difference'], 0.005);
        $this->assertEqualsWithDelta($this->itemValue(), $row['bin_value'], 0.005);
        $this->assertSame(0, RmStockcard::query()->where('create_by', 'nobody')->count());
        $this->assertSame(1, DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'INV_AVG_SCOPE')->where('constant_text', 'ITEM')->count());
    }
}
