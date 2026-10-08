<?php

namespace Tests\Feature;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\InvPallet;
use App\Models\Inventory\InvPalletLog;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\RmStockcard;
use App\Models\Inventory\RmStockD;
use App\Models\Master\MatRaw;
use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Services\Inventory\BatchService;
use App\Services\Inventory\PalletService;
use App\Services\Inventory\StockDocumentService;
use App\Services\Inventory\StockPostingService;
use App\Services\Inventory\WarehouseStock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Inventory & Warehouse v2.01, batch dan pemilik: baris kartu stok membawa batch_id dan pemilik dari baris dokumen; stok milik
// customer tidak dinilai (value_in kosong, harga rata-rata kosong) dan tidak ikut jurnal pemakaian; palet baru menaut batch dari lot-nya.
// Menulis ke database sungguhan (gedung uji sendiri, satu bahan yang belum punya kartu stok) dan MEMBERSIHKAN sendiri di tearDown(),
// termasuk cur_stock bahan itu dan voucher jurnal yang mungkin tertulis.
class BatchOwnerPostingTest extends TestCase
{
    private const BY = 'test_batch_owner';

    private WhWarehouse $warehouse;

    private WhLoc $bin;

    private MatRaw $material;

    private mixed $curStock;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
        $this->warehouse = WhWarehouse::create(['warehouse_code' => 'ZZB-A', 'warehouse_name' => 'Test ZZB-A', 'warehouse_type' => 'MIX', 'activate' => true,
            'length_m' => 20, 'width_m' => 10, 'floor_count' => 1, 'shape' => 'RECT', 'created_by' => self::BY, 'updated_by' => self::BY]);
        $this->bin = WhLoc::create(['warehouse_id' => $this->warehouse->id_warehouse, 'location_code' => 'ZZB-A-01', 'row_code' => 'ZZB-A-01', 'col_code' => '1',
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
        $this->assertSame(0, RmStockD::query()->where('create_by', self::BY)->count());
        $this->assertSame(0, InvBatch::query()->where('created_by', self::BY)->count());
        $this->assertSame(0, InvPallet::query()->where('created_by', self::BY)->count());
        $this->assertSame(0, WhWarehouse::query()->where('created_by', self::BY)->count());
        $this->assertSame(0, WhLoc::query()->where('created_by', self::BY)->count());
        $this->assertSame(0, DB::connection('tm2acc')->table('fin_voucher')->where('created_by', self::BY)->count());
        parent::tearDown();
    }

    private function clean(): void
    {
        $acc = DB::connection('tm2acc');
        $vouchers = $acc->table('fin_voucher')->where('created_by', self::BY)->pluck('voucher_id')->all();
        $acc->table('fin_voucher_line')->whereIn('voucher_id', $vouchers ?: [0])->delete();
        $acc->table('fin_voucher')->whereIn('voucher_id', $vouchers ?: [0])->delete();
        $palletIds = InvPallet::query()->where('created_by', self::BY)->pluck('id_pallet')->all();
        InvPalletLog::query()->whereIn('pallet_id', $palletIds ?: [0])->delete();
        InvPallet::query()->whereIn('id_pallet', $palletIds ?: [0])->delete();
        RmStockcard::query()->where('create_by', self::BY)->delete();
        RmStockD::query()->where('create_by', self::BY)->delete();
        RmStock::query()->where('create_by', self::BY)->delete();
        InvBatch::query()->where('created_by', self::BY)->delete();
        $ids = WhWarehouse::query()->where('created_by', self::BY)->pluck('id_warehouse')->all();
        WhLoc::query()->whereIn('warehouse_id', $ids ?: [0])->delete();
        WhWarehouse::query()->whereIn('id_warehouse', $ids ?: [0])->delete();
    }

    // Satu dokumen stok RM satu baris lalu diposting. $line = kolom tambahan baris (batch_id, owner_type, owner_id).
    private function postDoc(int $docType, float $qty, Carbon $at, float $price, array $line = []): array
    {
        $head = ['doc_no' => 'ZZB/'.(++$this->seq).'/'.uniqid(), 'doc_date' => $at, 'doc_type' => $docType, 'doc_cat' => 'STOCK', 'ref_doc_type' => 'MANUAL',
            'warehouse_id' => $this->warehouse->id_warehouse, 'notes' => 'test batch owner'];
        $lines = [['material_id' => $this->material->material_id, 'qty' => $qty, 'uom_code' => 'KG', 'unit_price' => $price, 'location_id' => $this->bin->location_id] + $line];
        $docId = app(StockDocumentService::class)->saveDraft('RM', null, $head, $lines, self::BY);

        return app(StockPostingService::class)->postDocument('RM', $docId, self::BY) + ['doc_id' => $docId];
    }

    private function card(int $docId): RmStockcard
    {
        $detail = RmStockD::query()->where('doc_id', $docId)->firstOrFail();

        return RmStockcard::query()->where('reference_name', 'tm2inv.rm_stock_d')->where('reference_detail_id', $detail->getKey())->firstOrFail();
    }

    private function batch(string $lot): InvBatch
    {
        return BatchService::create('RM', (int) $this->material->material_id, $lot, ['item_code' => $this->material->material_code, 'item_name' => $this->material->material_name], self::BY);
    }

    public function test_the_stock_card_row_carries_the_batch_and_the_company_owner_and_is_valued(): void
    {
        $batch = $this->batch('LOT-A');
        $result = $this->postDoc(1, 10, now()->subDays(3), 5, ['batch_id' => $batch->id_batch]);
        $this->assertTrue($result['posted'], (string) $result['error']);

        $card = $this->card($result['doc_id']);
        $this->assertSame((int) $batch->id_batch, (int) $card->batch_id);
        $this->assertSame('COMPANY', $card->owner_type);
        $this->assertNull($card->owner_id);
        $this->assertEqualsWithDelta(50.0, (float) $card->value_in, 0.0001);
        $this->assertEqualsWithDelta(5.0, (float) $card->avg_unitprice, 0.0001);
        $this->assertEqualsWithDelta(10.0, (float) $card->qty_eb, 0.0001);
    }

    public function test_a_line_without_batch_or_owner_keeps_working_as_before(): void
    {
        $result = $this->postDoc(1, 4, now()->subDays(2), 7);
        $card = $this->card($result['doc_id']);

        $this->assertNull($card->batch_id);
        $this->assertSame('COMPANY', $card->owner_type);
        $this->assertEqualsWithDelta(28.0, (float) $card->value_in, 0.0001);
    }

    public function test_customer_owned_stock_is_not_valued_and_not_journalled(): void
    {
        $batch = $this->batch('LOT-C');
        $in = $this->postDoc(1, 10, now()->subDays(3), 99, ['batch_id' => $batch->id_batch, 'owner_type' => 'CUSTOMER', 'owner_id' => 77]);
        $this->assertTrue($in['posted'], (string) $in['error']);
        $card = $this->card($in['doc_id']);
        $this->assertSame('CUSTOMER', $card->owner_type);
        $this->assertSame(77, (int) $card->owner_id);
        $this->assertSame((int) $batch->id_batch, (int) $card->batch_id);
        $this->assertNull($card->value_in, 'Customer-owned stock is not valued.');
        $this->assertTrue($card->avg_unitprice === null || (float) $card->avg_unitprice === 0.0, 'The average price of customer-owned stock stays empty or zero.');
        $this->assertTrue($card->value_eb === null || (float) $card->value_eb === 0.0);
        $this->assertEqualsWithDelta(10.0, (float) $card->qty_eb, 0.0001, 'The quantity is still tracked.');

        // Keluar: tidak ada jurnal pemakaian (tidak ada peringatan akun, tidak ada voucher).
        $out = $this->postDoc(-1, 4, now()->subDays(1), 0, ['batch_id' => $batch->id_batch, 'owner_type' => 'CUSTOMER', 'owner_id' => 77]);
        $this->assertTrue($out['posted'], (string) $out['error']);
        $this->assertArrayNotHasKey('journal_warning', $out);
        $this->assertSame(0, DB::connection('tm2acc')->table('fin_voucher')->where('source_type', 'STOCK_OUT_RM')->where('source_id', $out['doc_id'])->count());
        $this->assertEqualsWithDelta(6.0, (float) $this->card($out['doc_id'])->qty_eb, 0.0001);
    }

    public function test_company_stock_out_still_goes_to_the_usage_journal_for_contrast(): void
    {
        $this->assertTrue($this->postDoc(1, 10, now()->subDays(3), 5)['posted']);
        $out = $this->postDoc(-1, 2, now()->subDays(1), 0);
        $this->assertTrue($out['posted'], (string) $out['error']);

        // Akun HPP / persediaan bahan uji bisa belum diisi (peringatan) atau sudah diisi (voucher): salah satunya harus terjadi.
        $journalled = DB::connection('tm2acc')->table('fin_voucher')->where('source_type', 'STOCK_OUT_RM')->where('source_id', $out['doc_id'])->exists();
        $this->assertTrue($journalled || isset($out['journal_warning']), 'A company stock out is still offered to the usage journal.');
        if ($journalled) {
            // Voucher uji dibersihkan di clean(): tandai pembuatnya agar ikut terhapus.
            DB::connection('tm2acc')->table('fin_voucher')->where('source_type', 'STOCK_OUT_RM')->where('source_id', $out['doc_id'])->update(['created_by' => self::BY]);
        }
    }

    public function test_a_new_pallet_links_to_the_batch_of_its_lot(): void
    {
        $batch = $this->batch('LOT-P1');
        (new RmStockcard)->forceFill(['doc_date' => now()->subDays(10), 'material_id' => $this->material->material_id, 'doc_type' => 'BB', 'location_id' => $this->bin->location_id,
            'warehouse_id' => $this->warehouse->id_warehouse, 'qty' => 100, 'uom_code' => 'KG', 'reference_name' => 'test:batch', 'reference_detail_id' => $this->bin->location_id,
            'qty_bb' => 0, 'qty_in' => 100, 'qty_out' => 0, 'qty_adj' => 0, 'qty_eb' => 100, 'avg_unitprice' => 50, 'value_eb' => 5000, 'create_by' => self::BY, 'update_by' => self::BY])->save();
        $key = WarehouseStock::key('RM', (int) $this->material->material_id, null, (int) $this->bin->location_id);

        // Huruf besar-kecil dan spasi tepi pada lot tidak dibedakan; lot yang tidak punya batch = palet tanpa batch.
        $made = PalletService::create($key, [['qty' => 30, 'lot_no' => '  lot-p1 '], ['qty' => 20, 'lot_no' => 'UNKNOWN-LOT']], ['ref_doc_no' => 'SPB-TEST'], self::BY);

        $this->assertSame((int) $batch->id_batch, (int) $made[0]->batch_id);
        $this->assertNull($made[1]->batch_id);
    }
}
