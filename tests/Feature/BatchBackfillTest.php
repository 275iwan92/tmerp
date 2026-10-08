<?php

namespace Tests\Feature;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\InvPallet;
use App\Services\Inventory\BatchBackfillService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// inv:backfill-batch (BatchBackfillService): lot lama di kartu stok dan palet menjadi batch Released, lalu batch_id diisi di kartu,
// baris dokumen, dan palet. Data FIKTIF: dua item dengan id di luar jangkauan nyata, dan hanya item itu yang diproses (--item).
// Membersihkan sendiri di tearDown().
class BatchBackfillTest extends TestCase
{
    private const BY = 'test_batch_backfill';

    private const ITEM_A = 999000101;

    private const ITEM_B = 999000102;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
        $inv = DB::connection('tm2inv');
        $card = fn (int $item, ?string $lot, int $n) => $inv->table('rm_stockcard')->insert(['doc_date' => '2099-01-0'.$n.' 08:00:00', 'material_id' => $item, 'doc_type' => 'IN', 'location_id' => 1,
            'warehouse_id' => 1, 'lot_no' => $lot, 'qty' => 1, 'uom_code' => 'KG', 'reference_name' => 'test:backfill', 'reference_detail_id' => $item * 10 + $n, 'qty_bb' => 0, 'qty_in' => 1,
            'qty_out' => 0, 'qty_adj' => 0, 'qty_eb' => 1, 'create_by' => self::BY, 'update_by' => self::BY]);
        // Item A: dua penulisan lot yang sama (digabung), dua baris tanpa lot (null dan kosong). Item B: satu lot yang sama dengan A (batch terpisah per item).
        $card(self::ITEM_A, 'MILL-1', 1);
        $card(self::ITEM_A, ' mill-1 ', 2);
        $card(self::ITEM_A, null, 3);
        $card(self::ITEM_A, '', 4);
        $card(self::ITEM_B, 'MILL-1', 5);

        $docId = $inv->table('rm_stock')->insertGetId(['doc_no' => 'ZZBF/1', 'doc_date' => '2099-01-01', 'doc_type' => 1, 'doc_cat' => 'STOCK', 'warehouse_id' => 1, 'flow_status' => 'DRAFT',
            'create_by' => self::BY, 'update_by' => self::BY]);
        $inv->table('rm_stock_d')->insert(['doc_id' => $docId, 'seq_no' => 1, 'material_id' => self::ITEM_A, 'qty' => 1, 'uom_code' => 'KG', 'lot_no' => 'Mill-1', 'location_id' => 1,
            'create_by' => self::BY, 'update_by' => self::BY]);

        // Dua palet lot MILL-1 item A: kedaluwarsa batch = yang terdekat.
        foreach ([['2099-05-01', 'PLBF-1'], ['2099-03-01', 'PLBF-2']] as [$expiry, $no]) {
            InvPallet::create(['pallet_no' => $no, 'unit_kind' => 'PALLET', 'stock_class' => 'RM', 'item_id' => self::ITEM_A, 'item_code' => 'ZZ-A', 'item_name' => 'Test A', 'lot_no' => 'mill-1',
                'qty_initial' => 1, 'qty' => 1, 'location_id' => 1, 'arrived_at' => '2099-01-01', 'expiry_date' => $expiry, 'status' => 'ACTIVE', 'created_by' => self::BY, 'updated_by' => self::BY]);
        }
    }

    protected function tearDown(): void
    {
        $this->clean();
        foreach (['rm_stockcard' => 'create_by', 'rm_stock_d' => 'create_by', 'rm_stock' => 'create_by', 'inv_pallet' => 'created_by'] as $table => $column) {
            $this->assertSame(0, DB::connection('tm2inv')->table($table)->where($column, self::BY)->count(), $table);
        }
        $this->assertSame(0, InvBatch::query()->whereIn('item_id', [self::ITEM_A, self::ITEM_B])->count());
        parent::tearDown();
    }

    private function clean(): void
    {
        $inv = DB::connection('tm2inv');
        $inv->table('rm_stockcard')->where('create_by', self::BY)->delete();
        $inv->table('rm_stock_d')->where('create_by', self::BY)->delete();
        $inv->table('rm_stock')->where('create_by', self::BY)->delete();
        $inv->table('inv_pallet')->where('created_by', self::BY)->delete();
        InvBatch::query()->whereIn('item_id', [self::ITEM_A, self::ITEM_B])->delete();
    }

    private function run_(bool $write): array
    {
        return BatchBackfillService::run($write, self::BY, ['RM'], [self::ITEM_A, self::ITEM_B]);
    }

    public function test_check_mode_counts_but_writes_nothing(): void
    {
        $r = $this->run_(false);

        $this->assertSame(3, $r['batches_created'], 'A: MILL-1 and no lot; B: MILL-1.');
        $this->assertSame(1, $r['groups_merged'], 'MILL-1 and mill-1 of item A are one batch.');
        $this->assertSame(['cards' => 5, 'lines' => 1, 'pallets' => 2], $r['linked']);
        $this->assertSame(0, InvBatch::query()->whereIn('item_id', [self::ITEM_A, self::ITEM_B])->count());
        $this->assertSame(0, DB::connection('tm2inv')->table('rm_stockcard')->where('create_by', self::BY)->whereNotNull('batch_id')->count());
    }

    public function test_backfill_creates_released_batches_links_rows_and_is_idempotent(): void
    {
        $r = $this->run_(true);
        $this->assertSame(3, $r['batches_created']);
        $this->assertSame(['cards' => 5, 'lines' => 1, 'pallets' => 2], $r['linked']);

        $a = InvBatch::query()->where('item_id', self::ITEM_A)->orderBy('batch_no')->get();
        $this->assertCount(2, $a);
        $mill = $a->firstWhere('supplier_lot', 'MILL-1');
        $none = $a->firstWhere('supplier_lot', null);
        $this->assertNotNull($mill);
        $this->assertNotNull($none, 'Rows without a lot become the NO-LOT batch.');
        $this->assertSame('RELEASED', $mill->qc_status);
        $this->assertSame('2099-03-01', $mill->expiry_date->toDateString(), 'Expiry = the nearest date of its pallets.');
        $this->assertNull($none->expiry_date);
        $b = InvBatch::query()->where('item_id', self::ITEM_B)->firstOrFail();
        $this->assertNotSame($mill->id_batch, $b->id_batch, 'The same lot of another item is another batch.');

        $inv = DB::connection('tm2inv');
        $cards = $inv->table('rm_stockcard')->where('create_by', self::BY)->orderBy('reference_detail_id')->pluck('batch_id', 'reference_detail_id');
        $this->assertSame([(int) $mill->id_batch, (int) $mill->id_batch, (int) $none->id_batch, (int) $none->id_batch, (int) $b->id_batch], $cards->values()->map(fn ($v) => (int) $v)->all());
        $this->assertSame((int) $mill->id_batch, (int) $inv->table('rm_stock_d')->where('create_by', self::BY)->value('batch_id'));
        $this->assertSame([(int) $mill->id_batch, (int) $mill->id_batch], $inv->table('inv_pallet')->where('created_by', self::BY)->pluck('batch_id')->map(fn ($v) => (int) $v)->all());

        // Idempoten: kedua kalinya tidak membuat batch dan tidak menyentuh baris yang sudah bertaut.
        $again = $this->run_(true);
        $this->assertSame(0, $again['batches_created']);
        $this->assertSame(3, $again['batches_existing']);
        $this->assertSame(['cards' => 0, 'lines' => 0, 'pallets' => 0], $again['linked']);
        $this->assertSame(3, InvBatch::query()->whereIn('item_id', [self::ITEM_A, self::ITEM_B])->count());
    }

    public function test_the_artisan_command_reports_and_check_does_not_write(): void
    {
        $this->assertSame(0, Artisan::call('inv:backfill-batch', ['--check' => true, '--class' => 'RM', '--item' => [self::ITEM_A, self::ITEM_B]]));
        $this->assertStringContainsString('Pemeriksaan saja', Artisan::output());
        $this->assertSame(0, InvBatch::query()->whereIn('item_id', [self::ITEM_A, self::ITEM_B])->count());

        $this->assertSame(0, Artisan::call('inv:backfill-batch', ['--class' => 'RM', '--item' => [self::ITEM_A, self::ITEM_B]]));
        $this->assertStringContainsString('Batch dibuat: 3', Artisan::output());
        $this->assertSame(3, InvBatch::query()->whereIn('item_id', [self::ITEM_A, self::ITEM_B])->count());
        // created_by batch dari perintah = 'inv:backfill-batch'; dibersihkan oleh clean() lewat item_id.
        $this->assertSame(['inv:backfill-batch'], InvBatch::query()->whereIn('item_id', [self::ITEM_A, self::ITEM_B])->distinct()->pluck('created_by')->all());
    }
}
