<?php

namespace Tests\Feature;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\RmStockcard;
use App\Models\Master\MatRaw;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysAppMenu;
use App\Services\Inventory\BatchService;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Layar Inventory > Batch & Quality > Batch (inv-batch, BatchLookup). Menulis ke database sungguhan: batch uji diberi catatan
// penanda dan dibersihkan di tearDown(); kartu stok uji ditandai create_by.
class BatchLookupTest extends TestCase
{
    private const MARK = 'zz-test-batch-lookup';

    private const BY = 'test_batch_lookup';

    private MatRaw $material;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
        $this->actingAs(SecUser::query()->where('is_admin', 1)->firstOrFail());
        $this->material = MatRaw::withoutGlobalScopes()->where('activate', true)->where(fn ($q) => $q->whereNull('shelf_life_days')->orWhere('shelf_life_days', 0))->orderBy('material_id')->firstOrFail();
    }

    protected function tearDown(): void
    {
        $this->clean();
        $this->assertSame(0, InvBatch::query()->where('notes', self::MARK)->count());
        $this->assertSame(0, RmStockcard::query()->where('create_by', self::BY)->count());
        parent::tearDown();
    }

    private function clean(): void
    {
        RmStockcard::query()->where('create_by', self::BY)->delete();
        InvBatch::query()->where('notes', self::MARK)->delete();
    }

    private function grid()
    {
        return Grids::find('inv-batch');
    }

    private function newRow(array $extra = []): array
    {
        return array_merge(['id' => null, 'stock_class' => 'RM', 'item_code' => $this->material->material_code, 'supplier_lot' => 'ZZ-LOT-1', 'notes' => self::MARK, 'certification' => 'FSC',
            'food_grade' => true, 'activate' => true, 'production_date' => '2026-10-01', 'expiry_date' => '2027-10-01'], $extra);
    }

    public function test_a_new_row_becomes_a_batch_with_an_automatic_number_and_the_item_from_the_master(): void
    {
        $rows = $this->grid()->save([$this->newRow()]);

        $batch = InvBatch::query()->where('notes', self::MARK)->firstOrFail();
        $this->assertMatchesRegularExpression('/^BT\d{2}\d{5}$/', $batch->batch_no);
        $this->assertSame((int) $this->material->material_id, (int) $batch->item_id);
        $this->assertSame($this->material->material_name, $batch->item_name);
        $this->assertSame('RELEASED', $batch->qc_status);
        $this->assertSame('FSC', $batch->certification);
        $this->assertTrue((bool) $batch->food_grade);
        $this->assertSame('ZZ-LOT-1', $batch->supplier_lot);
        $row = collect($rows)->firstWhere('id', $batch->id_batch);
        $this->assertSame($batch->batch_no, $row['batch_no']);
        $this->assertSame('Released', $row['qc_status'], 'The status column shows the label.');
        $this->assertSame('RELEASED', $row['qc']);
        $this->assertSame(0.0, $row['on_hand']);
        $this->assertSame('2027-10-01', $row['expiry_date']);
    }

    public function test_saving_cannot_change_status_batch_number_item_or_class_of_a_saved_batch(): void
    {
        $this->grid()->save([$this->newRow()]);
        $batch = InvBatch::query()->where('notes', self::MARK)->firstOrFail();

        $this->grid()->save([['id' => $batch->id_batch, 'stock_class' => 'FG', 'item_code' => 'OTHER', 'batch_no' => 'BT0000001', 'qc_status' => 'REJECTED', 'item_name' => 'Hacked',
            'supplier_lot' => 'ZZ-LOT-2', 'notes' => self::MARK, 'certification' => 'PEFC', 'food_grade' => false, 'activate' => true]]);

        $batch->refresh();
        $this->assertSame('RM', $batch->stock_class);
        $this->assertSame((int) $this->material->material_id, (int) $batch->item_id);
        $this->assertNotSame('BT0000001', $batch->batch_no);
        $this->assertSame('RELEASED', $batch->qc_status, 'Status changes only through BatchService::setStatus.');
        $this->assertNotSame('Hacked', $batch->item_name);
        $this->assertSame('ZZ-LOT-2', $batch->supplier_lot);
        $this->assertSame('PEFC', $batch->certification);

        BatchService::setStatus($batch, 'REJECTED', self::BY, 'test reason');
        $this->assertSame('Rejected', collect($this->grid()->rows())->firstWhere('id', $batch->id_batch)['qc_status']);
    }

    public function test_unknown_item_duplicate_lot_and_bad_dates_are_refused(): void
    {
        $refused = function (array $row): string {
            try {
                $this->grid()->save([$row]);
            } catch (ValidationException $e) {
                return $e->errors()['row'][0];
            }
            $this->fail('The row should have been refused: '.json_encode($row));
        };

        $this->assertStringContainsString('NOPE-CODE', $refused($this->newRow(['item_code' => 'NOPE-CODE'])));
        $this->assertStringContainsString('expiry date is before', $refused($this->newRow(['production_date' => '2026-10-05', 'expiry_date' => '2026-10-01'])));

        $this->grid()->save([$this->newRow()]);
        $no = InvBatch::query()->where('notes', self::MARK)->value('batch_no');
        $this->assertStringContainsString($no, $refused($this->newRow(['supplier_lot' => ' zz-lot-1 '])), 'The same item and lot (ignoring case and spaces) is one batch.');
        $this->assertSame(1, InvBatch::query()->where('notes', self::MARK)->count());
    }

    public function test_an_item_with_a_shelf_life_needs_an_expiry_date(): void
    {
        $shelf = MatRaw::withoutGlobalScopes()->where('shelf_life_days', '>', 0)->first();
        if (! $shelf) {
            $this->markTestSkipped('No raw material with a shelf life in this database.');
        }
        try {
            $this->grid()->save([$this->newRow(['item_code' => $shelf->material_code, 'expiry_date' => null])]);
            $this->fail('An expiry date is required for this item.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('shelf life', $e->errors()['row'][0]);
        }
        $this->assertSame(0, InvBatch::query()->where('notes', self::MARK)->count());
    }

    public function test_stat_boxes_filter_by_status_and_expiry_and_on_hand_comes_from_the_stock_card(): void
    {
        $item = (int) $this->material->material_id;
        $soon = now()->addDays(5)->toDateString();
        $quarantined = BatchService::create('RM', $item, 'ZZ-Q', ['qc_status' => 'QUARANTINE', 'notes' => self::MARK, 'expiry_date' => $soon], self::BY);
        $released = BatchService::create('RM', $item, 'ZZ-R', ['notes' => self::MARK, 'expiry_date' => $soon], self::BY);
        $empty = BatchService::create('RM', $item, 'ZZ-E', ['notes' => self::MARK, 'expiry_date' => $soon], self::BY);
        // Stok 12 di batch R (masuk 20, keluar 8); batch E tanpa stok.
        foreach ([[20, 0], [0, 8]] as $i => [$in, $out]) {
            DB::connection('tm2inv')->table('rm_stockcard')->insert(['doc_date' => now()->subDays(2 - $i), 'material_id' => $item, 'doc_type' => $in ? 'IN' : 'OUT', 'location_id' => 1, 'warehouse_id' => 1,
                'qty' => $in - $out, 'uom_code' => 'KG', 'reference_name' => 'test:lookup', 'reference_detail_id' => 900 + $i, 'qty_bb' => 0, 'qty_in' => $in, 'qty_out' => $out, 'qty_adj' => 0,
                'qty_eb' => 0, 'batch_id' => $released->id_batch, 'create_by' => self::BY, 'update_by' => self::BY]);
        }

        $rows = collect($this->grid()->rows());
        $mine = $rows->whereIn('id', [$quarantined->id_batch, $released->id_batch, $empty->id_batch])->keyBy('id');
        $this->assertSame(12.0, $mine[$released->id_batch]['on_hand']);
        $this->assertSame(0.0, $mine[$empty->id_batch]['on_hand']);
        $this->assertSame('QUARANTINE', $mine[$quarantined->id_batch]['qc']);
        $this->assertTrue($mine[$released->id_batch]['expiring'], 'Stock that expires within the limit counts as Expires soon.');
        $this->assertFalse($mine[$empty->id_batch]['expiring'], 'A batch without stock is not Expires soon.');

        $stats = collect($this->grid()->stats());
        $this->assertSame(['All', 'Quarantine', 'Conditional', 'Released', 'Rejected', 'Expires soon'], $stats->pluck('label')->all());
        $props = $this->grid()->props();
        $this->assertSame($stats->all(), $props['stats']);

        // Sheet menghitung kotak statistik dari baris: render layarnya memuat label dan angka kotak.
        $this->get(route('v2.screen', 'inv-batch'))->assertOk()->assertSee('Expires soon')->assertSee('Quarantine')->assertSee($released->batch_no);
    }

    public function test_the_screen_is_in_the_inventory_menu_under_batch_and_quality(): void
    {
        $item = SysAppMenu::query()->where('code', 'INV_BQ_BATCH')->first();
        if (! $item) {
            $this->markTestSkipped('The Batch menu row is not installed in this database yet.');
        }
        $this->assertSame('v2:inv-batch', $item->target);
        $this->assertSame('Batch & Quality', SysAppMenu::query()->find($item->parent_id)->label);
        $this->assertSame('INV', SysAppMenu::query()->find(SysAppMenu::query()->find($item->parent_id)->parent_id)->code);
    }
}
