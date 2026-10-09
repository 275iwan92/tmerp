<?php

namespace Tests\Feature;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\InvQcDecision;
use App\Models\Inventory\RmStockcard;
use App\Models\Master\MatRaw;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysAppMenu;
use App\Services\Inventory\BatchService;
use App\Services\Inventory\QcDecisionService;
use App\Support\V2\Data\Batches;
use App\Support\V2\Grid\Grids;
use App\Support\V2\Grid\Inv\BatchListGrid;
use App\Support\V2\Grid\Inv\BatchLookup;
use Devlib\Lookup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Layar Inventory > Batch & Quality > Batch (inv-batch: daftar BatchListGrid + form; simpan lewat BatchLookup). Menulis ke database sungguhan: batch uji diberi catatan
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
        InvQcDecision::query()->whereIn('batch_id', InvBatch::query()->where('notes', self::MARK)->pluck('id_batch'))->delete();
        RmStockcard::query()->where('create_by', self::BY)->delete();
        InvBatch::query()->where('notes', self::MARK)->delete();
    }

    private function grid()
    {
        return new BatchLookup;
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

    public function test_the_list_is_a_transactional_grid_that_opens_the_batch_form(): void
    {
        $grid = Grids::find('inv-batch');
        $this->assertInstanceOf(BatchListGrid::class, $grid);
        $this->assertSame('transactional', $grid->family());
        $this->assertStringContainsString('?id={id}', $grid->openUrl());
        $this->assertStringContainsString('?new=1', $grid->newUrl());

        $this->grid()->save([$this->newRow()]);
        $batch = InvBatch::query()->where('notes', self::MARK)->firstOrFail();
        $row = collect($grid->rows())->firstWhere('id', $batch->id_batch);
        $this->assertSame($batch->batch_no, $row['batch_no']);
        $this->assertSame('Yes', $row['food_label']);
        $this->assertSame('FSC', $row['cert_label']);
        $this->assertSame($this->material->material_code, $row['item_code']);
    }

    public function test_the_form_shows_the_stock_of_the_batch_and_its_quality_decisions(): void
    {
        $item = (int) $this->material->material_id;
        $batch = BatchService::create('RM', $item, 'ZZ-F', ['qc_status' => 'QUARANTINE', 'notes' => self::MARK], self::BY);
        DB::connection('tm2inv')->table('rm_stockcard')->insert(['doc_date' => now()->subDay(), 'material_id' => $item, 'doc_type' => 'IN', 'location_id' => 1, 'warehouse_id' => 1, 'qty' => 24, 'uom_code' => 'KG',
            'reference_name' => 'test:form', 'reference_detail_id' => 950, 'qty_bb' => 0, 'qty_in' => 24, 'qty_out' => 0, 'qty_adj' => 0, 'qty_eb' => 0, 'batch_id' => $batch->id_batch, 'create_by' => self::BY, 'update_by' => self::BY]);
        QcDecisionService::record($batch, 'USAGE_DECISION', 'RELEASED', self::BY);

        $this->assertSame(24.0, Batches::onHand($batch));
        $this->assertSame('Status', Batches::history($batch)[0]['action']);
        $page = $this->get(route('v2.screen', 'inv-batch').'?id='.$batch->id_batch)->assertOk();
        $page->assertSee($batch->batch_no)->assertSee('Stock of this batch')->assertSee('Quality decisions')->assertSee('Usage decision')->assertSee('Release');
        $this->get(route('v2.screen', 'inv-batch').'?new=1')->assertOk()->assertSee('Item Code');
        $this->get(route('v2.screen', 'inv-batch').'?id=999999999')->assertNotFound();
    }

    public function test_the_save_route_changes_a_batch_but_never_its_status_and_the_decide_route_returns_to_the_form(): void
    {
        $new = $this->post(route('v2.iq.batch-save'), ['stock_class' => 'RM', 'item_code' => $this->material->material_code, 'supplier_lot' => 'ZZ-S1', 'notes' => self::MARK, 'certification' => 'PEFC', 'food_grade' => 1,
            'expiry_date' => '2027-01-01']);
        $batch = InvBatch::query()->where('notes', self::MARK)->firstOrFail();
        $new->assertRedirect(route('v2.screen', 'inv-batch').'?id='.$batch->id_batch);
        $this->assertSame('PEFC', $batch->certification);

        $this->post(route('v2.iq.batch-save', $batch->id_batch), ['supplier_lot' => 'ZZ-S2', 'notes' => self::MARK, 'certification' => 'FSC', 'qc_status' => 'REJECTED'])->assertRedirect();
        $batch->refresh();
        $this->assertSame('ZZ-S2', $batch->supplier_lot);
        $this->assertFalse((bool) $batch->food_grade, 'An unchecked box saves as false.');
        $this->assertSame('RELEASED', $batch->qc_status);

        $this->post(route('v2.iq.batch-save'), ['stock_class' => 'RM', 'item_code' => 'NOPE-CODE', 'notes' => self::MARK])->assertSessionHasErrors('batch');

        $this->post(route('v2.iq.decide', $batch->id_batch).'?from=inv-batch', ['decision' => 'REJECTED', 'decision_type' => 'USAGE_DECISION', 'reason' => 'Failed the moisture test'])
            ->assertRedirect(route('v2.screen', 'inv-batch').'?id='.$batch->id_batch);
        $this->assertSame('REJECTED', $batch->fresh()->qc_status);
    }

    public function test_the_item_lookup_finds_items_of_every_class_and_fills_the_class(): void
    {
        $rows = Batches::itemSearch($this->material->material_code);
        $hit = collect($rows)->firstWhere('item_code', $this->material->material_code);
        $this->assertNotNull($hit);
        $this->assertSame('RM', $hit['class']);
        $this->assertSame($this->material->material_code, Batches::itemFind($this->material->material_code)['item_code']);
        $this->assertNull(Batches::itemFind('NOPE-CODE'));
        $this->assertSame(['stock_class' => 'class'], Lookup::get('stock_item')['fill']);
    }

    public function test_expiry_is_red_when_expired_and_amber_when_soon_in_the_list_and_the_form(): void
    {
        $item = (int) $this->material->material_id;
        $old = BatchService::create('RM', $item, 'ZZ-EXP', ['notes' => self::MARK, 'expiry_date' => now()->subDays(3)->toDateString()], self::BY);
        $soon = BatchService::create('RM', $item, 'ZZ-SOON', ['notes' => self::MARK, 'expiry_date' => now()->addDays(2)->toDateString()], self::BY);
        $far = BatchService::create('RM', $item, 'ZZ-FAR', ['notes' => self::MARK, 'expiry_date' => now()->addYear()->toDateString()], self::BY);

        $this->assertSame('danger', Batches::expiryTone($old));
        $this->assertSame('warn', Batches::expiryTone($soon));
        $this->assertNull(Batches::expiryTone($far));
        $rows = collect($this->grid()->rows());
        $this->assertSame('danger', $rows->firstWhere('id', $old->id_batch)['expiry_tone']);
        $this->assertSame('expiry_tone', collect((new BatchListGrid)->columns())->firstWhere('key', 'expiry_date')['toneKey']);
        $this->get(route('v2.screen', 'inv-batch').'?id='.$old->id_batch)->assertOk()->assertSee('dl-field--tone-danger', false);
        $this->get(route('v2.screen', 'inv-batch').'?id='.$far->id_batch)->assertOk()->assertDontSee('dl-field--tone-', false);
    }

    public function test_a_certificate_of_analysis_can_be_uploaded_with_the_batch(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('COA-ZZ.pdf', 20, 'application/pdf');
        $this->post(route('v2.iq.batch-save'), ['stock_class' => 'RM', 'item_code' => $this->material->material_code, 'supplier_lot' => 'ZZ-COA', 'notes' => self::MARK, 'coa' => $file])->assertRedirect();
        $batch = InvBatch::query()->where('notes', self::MARK)->firstOrFail();
        $this->assertSame('batch/'.$batch->batch_no.'/COA-ZZ.pdf', $batch->coa_path);
        Storage::disk('public')->assertExists($batch->coa_path);
        $this->get(route('v2.screen', 'inv-batch').'?id='.$batch->id_batch)->assertOk()->assertSee('COA-ZZ.pdf');

        $bad = UploadedFile::fake()->create('virus.exe', 5);
        $this->post(route('v2.iq.batch-save', $batch->id_batch), ['notes' => self::MARK, 'coa' => $bad])->assertSessionHasErrors('coa');
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
