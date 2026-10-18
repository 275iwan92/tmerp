<?php

namespace Tests\Feature;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\RmStockD;
use App\Models\Master\MatRaw;
use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Sys\SecUser;
use App\Services\Inventory\BatchService;
use App\Services\Inventory\StockInEntryService;
use App\Support\V2\Data\StockEntry;
use App\Support\V2\Grid\Grids;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Form Stock In v2 (RM): kolom Batch menggantikan Lot. Pilih batch yang ada untuk item itu, atau buat batch baru dari baris (lot supplier,
// tanggal produksi, kedaluwarsa; kedaluwarsa wajib bila item punya umur simpan). Dokumen lama tetap menampilkan teks lot-nya.
// Lewat StockInEntryService (form v2); menulis ke database sungguhan; dokumen uji diberi catatan penanda, batch uji diberi lot 'ZZ-SI-*'; dibersihkan di tearDown().
class BatchStockInFormTest extends TestCase
{
    private const MARK = 'zz-test-batch-stockin';

    private const BY = 'test_batch_stockin';

    private WhWarehouse $wh;

    private WhLoc $bin;

    private MatRaw $material;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
        $this->actingAs(SecUser::query()->where('is_admin', 1)->firstOrFail());
        $this->wh = WhWarehouse::create(['warehouse_code' => 'ZZS-A', 'warehouse_name' => 'Test ZZS-A', 'warehouse_type' => 'MIX', 'activate' => true, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $this->bin = WhLoc::create(['warehouse_id' => $this->wh->id_warehouse, 'location_code' => 'ZZS-A-01', 'row_code' => 'ZZS-A-01', 'col_code' => '1', 'stack_code' => '1',
            'is_usable' => true, 'activate' => true, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $this->material = MatRaw::withoutGlobalScopes()->where('activate', true)->where(fn ($q) => $q->whereNull('shelf_life_days')->orWhere('shelf_life_days', 0))->orderBy('material_id')->firstOrFail();
    }

    protected function tearDown(): void
    {
        $this->clean();
        $this->assertSame(0, RmStock::query()->where('notes', self::MARK)->count());
        $this->assertSame(0, InvBatch::query()->where('supplier_lot', 'like', 'ZZ-SI-%')->count());
        $this->assertSame(0, WhLoc::query()->where('created_by', self::BY)->count());
        parent::tearDown();
    }

    private function clean(): void
    {
        $ids = RmStock::query()->where('notes', self::MARK)->pluck('doc_id')->all();
        RmStockD::query()->whereIn('doc_id', $ids ?: [0])->delete();
        RmStock::query()->whereIn('doc_id', $ids)->delete();
        InvBatch::query()->where('supplier_lot', 'like', 'ZZ-SI-%')->delete();
        $w = WhWarehouse::query()->where('created_by', self::BY)->pluck('id_warehouse')->all();
        WhLoc::query()->whereIn('warehouse_id', $w ?: [0])->delete();
        WhWarehouse::query()->whereIn('id_warehouse', $w)->delete();
    }

    // Simpan Draft satu baris lewat layanan form v2.
    private function save(array $line, ?MatRaw $item = null): int
    {
        $item ??= $this->material;

        return app(StockInEntryService::class)->save('RM', null, ['doc_date' => now()->toDateString(), 'doc_cat' => 'STOCK', 'warehouse_id' => $this->wh->id_warehouse, 'notes' => self::MARK],
            [$line + ['item_id' => $item->material_id, 'location_id' => $this->bin->location_id, 'qty' => '12', 'uom_code' => 'KG']], SecUser::query()->where('is_admin', 1)->firstOrFail());
    }

    private function docLine(): RmStockD
    {
        return RmStockD::query()->whereIn('doc_id', RmStock::query()->where('notes', self::MARK)->pluck('doc_id'))->firstOrFail();
    }

    public function test_a_new_batch_is_made_from_the_line_and_the_document_line_carries_it(): void
    {
        $this->save(['new_lot' => 'ZZ-SI-1', 'new_production' => '2026-10-01', 'new_expiry' => '2027-04-01']);

        $batch = InvBatch::query()->where('supplier_lot', 'ZZ-SI-1')->firstOrFail();
        $this->assertSame((int) $this->material->material_id, (int) $batch->item_id);
        $this->assertSame('RM', $batch->stock_class);
        $this->assertSame('2027-04-01', $batch->expiry_date->toDateString());
        $this->assertSame('2026-10-01', $batch->production_date->toDateString());
        $line = $this->docLine();
        $this->assertSame((int) $batch->id_batch, (int) $line->batch_id);
        $this->assertSame('ZZ-SI-1', $line->lot_no, 'The old lot text stays filled for readers of the lot column.');
    }

    public function test_an_existing_batch_can_be_picked_and_the_same_lot_is_not_made_twice(): void
    {
        $existing = BatchService::create('RM', (int) $this->material->material_id, 'ZZ-SI-2', [], self::BY);

        $found = collect(StockEntry::batchSearch($existing->batch_no))->firstWhere('id', (int) $existing->id_batch);
        $this->assertNotNull($found, 'The lookup finds the batch by its number.');
        $this->assertSame('ZZ-SI-2', $found['lot']);
        $this->assertNotNull(collect(StockEntry::batchSearch('ZZ-SI-2'))->firstWhere('id', (int) $existing->id_batch), 'and by its supplier lot');

        $this->save(['batch_id' => (string) $existing->id_batch]);
        $this->assertSame((int) $existing->id_batch, (int) $this->docLine()->batch_id);

        // Batch baru dengan lot yang sama dengan batch yang ada memakai batch itu, bukan membuat dobel.
        $this->save(['new_lot' => ' zz-si-2 ']);
        $this->assertSame(1, InvBatch::query()->where('supplier_lot', 'ZZ-SI-2')->count());

        // Batch milik item lain ditolak.
        $other = MatRaw::withoutGlobalScopes()->where('activate', true)->where('material_id', '!=', $this->material->material_id)->firstOrFail();
        $this->expectException(ValidationException::class);
        $this->save(['batch_id' => (string) $existing->id_batch], $other);
    }

    public function test_an_item_with_a_shelf_life_needs_an_expiry_date_for_a_new_batch(): void
    {
        $shelf = MatRaw::withoutGlobalScopes()->where('activate', true)->where('shelf_life_days', '>', 0)->first();
        if (! $shelf) {
            $this->markTestSkipped('No raw material with a shelf life in this database.');
        }
        try {
            $this->save(['new_lot' => 'ZZ-SI-3'], $shelf);
            $this->fail('expiry date is required');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lines', $e->errors());
        }
        $this->assertSame(0, InvBatch::query()->where('supplier_lot', 'ZZ-SI-3')->count());
        $this->assertSame(0, RmStock::query()->where('notes', self::MARK)->count(), 'Nothing is saved while the batch is incomplete.');

        $this->save(['new_lot' => 'ZZ-SI-3', 'new_expiry' => '2027-01-01'], $shelf);
        $this->assertSame(1, InvBatch::query()->where('supplier_lot', 'ZZ-SI-3')->count());
    }

    public function test_an_old_document_without_a_batch_still_shows_and_keeps_its_lot_text(): void
    {
        $doc = RmStock::create(['doc_no' => 'ZZSI/'.uniqid(), 'doc_date' => now()->toDateString(), 'doc_type' => 1, 'doc_cat' => 'STOCK', 'warehouse_id' => $this->wh->id_warehouse,
            'flow_status' => 'DRAFT', 'ref_doc_type' => 'MANUAL', 'notes' => self::MARK, 'create_by' => self::BY, 'update_by' => self::BY]);
        RmStockD::create(['doc_id' => $doc->doc_id, 'seq_no' => 1, 'material_id' => $this->material->material_id, 'qty' => 3, 'uom_code' => 'KG', 'lot_no' => 'OLD-LOT-77',
            'location_id' => $this->bin->location_id, 'unit_price' => 0, 'create_by' => self::BY, 'update_by' => self::BY]);

        $this->get(route('v2.screen', 'inv-stock-in-form').'?class=RM&id='.$doc->doc_id)->assertOk()->assertSee('OLD-LOT-77');
        $rows = StockEntry::rows('RM', RmStock::with('lines')->find($doc->doc_id));
        $this->assertSame('', (string) $rows[0]['batch_id']);
        $this->assertSame('OLD-LOT-77', $rows[0]['lot_no']);

        // Simpan ulang dari form tanpa mengubah lot: teks lot lama tetap.
        app(StockInEntryService::class)->save('RM', $doc->doc_id, ['doc_date' => now()->toDateString(), 'doc_cat' => 'STOCK', 'warehouse_id' => $this->wh->id_warehouse, 'notes' => self::MARK],
            [['item_id' => $this->material->material_id, 'location_id' => $this->bin->location_id, 'qty' => '3', 'uom_code' => 'KG', 'lot_no' => 'OLD-LOT-77']], SecUser::query()->where('is_admin', 1)->firstOrFail());
        $this->assertSame('OLD-LOT-77', $this->docLine()->lot_no);
    }

    public function test_the_class_stock_card_screens_open(): void
    {
        foreach (['inv-rm-rpt-card', 'inv-sfg-rpt-card', 'inv-fg-rpt-card'] as $slug) {
            $this->get(route('v2.screen', $slug))->assertOk();
            $this->assertNotNull(Grids::find($slug));
        }
    }
}
