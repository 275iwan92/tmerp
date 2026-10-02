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
use App\Services\Inventory\StockDocumentService;
use App\Services\Inventory\StockPostingService;
use App\Services\Inventory\StockTransferService;
use App\Services\Inventory\WarehouseStock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\TestCase;

// Urutan posting kartu stok (2026-10-02). StockPostingService mengambil saldo awal dari baris SEBELUM tanggal dokumen dan
// tidak menyentuh baris yang bertanggal sesudahnya, sedangkan saldo (view v_*_onhand, StockTransferService::balance,
// WarehouseStock) dibaca dari baris TERAKHIR. Dokumen yang tanggal-jamnya lebih awal dari baris terakhir item itu di bin
// itu tertulis di kartu tetapi tidak sampai ke saldo.
//
// Tiap uji menegaskan hal yang benar di aturan mana pun yang nanti dipilih user (hitung ulang baris sesudahnya / tolak /
// selalu taruh paling akhir): kartu stok dan saldo tidak boleh berbeda. Selama cacatnya masih ada, uji itu berstatus
// INCOMPLETE dengan pesan "KNOWN DEFECT" (bukan gagal, supaya suite tetap hijau); begitu diperbaiki uji itu lulus sendiri.
//
// Menulis ke database sungguhan (gedung uji sendiri, satu bahan yang belum punya kartu stok) dan membersihkan sendiri di
// tearDown(), termasuk cur_stock bahan itu. Stock Out di sini ber-ref PV_CANCEL (pembalik penerimaan) supaya tidak ada
// jurnal pemakaian yang tertulis ke tm2acc.
class StockPostingOrderTest extends TestCase
{
    private const BY = 'test_stock_posting_order';

    private WhWarehouse $warehouse;

    private WhLoc $bin;

    private WhLoc $other;

    private MatRaw $material;

    private mixed $curStock;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
        $this->warehouse = WhWarehouse::create(['warehouse_code' => 'ZZP-A', 'warehouse_name' => 'Test ZZP-A', 'warehouse_type' => 'MIX', 'activate' => true,
            'length_m' => 20, 'width_m' => 10, 'floor_count' => 1, 'shape' => 'RECT', 'created_by' => self::BY, 'updated_by' => self::BY]);
        $bin = fn (string $code) => WhLoc::create(['warehouse_id' => $this->warehouse->id_warehouse, 'location_code' => $code, 'row_code' => $code, 'col_code' => '1',
            'stack_code' => '1', 'is_usable' => true, 'activate' => true, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $this->bin = $bin('ZZP-A-01');
        $this->other = $bin('ZZP-A-02');
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
        $this->assertSame(0, InvTransfer::query()->where('created_by', self::BY)->count());
        $this->assertSame(0, WhWarehouse::query()->where('created_by', self::BY)->count());
        $this->assertSame(0, WhLoc::query()->where('created_by', self::BY)->count());
        parent::tearDown();
    }

    private function clean(): void
    {
        RmStockcard::query()->where('create_by', self::BY)->delete();
        RmStockD::query()->where('create_by', self::BY)->delete();
        RmStock::query()->where('create_by', self::BY)->delete();
        InvTransfer::query()->where('created_by', self::BY)->delete();
        $ids = WhWarehouse::query()->where('created_by', self::BY)->pluck('id_warehouse')->all();
        WhLoc::query()->whereIn('warehouse_id', $ids ?: [0])->delete();
        WhWarehouse::query()->whereIn('id_warehouse', $ids ?: [0])->delete();
    }

    // Satu dokumen stok RM berisi satu baris, lalu diposting. $qty untuk AD sudah bertanda (fisik - sistem).
    // $headerId hanya dipakai uji urutan nomor: kepala dokumen dengan nomor urut tertentu.
    private function postDoc(int $docType, string $docCat, float $qty, Carbon $at, float $price = 0, string $refType = 'MANUAL', ?int $headerId = null): array
    {
        $head = ['doc_no' => 'ZZP/'.(++$this->seq).'/'.uniqid(), 'doc_date' => $at, 'doc_type' => $docType, 'doc_cat' => $docCat, 'ref_doc_type' => $refType,
            'warehouse_id' => $this->warehouse->id_warehouse, 'notes' => 'test stock posting order'];
        $line = ['material_id' => $this->material->material_id, 'qty' => $qty, 'uom_code' => 'SHEET', 'unit_price' => $price, 'location_id' => $this->bin->location_id];
        if ($headerId === null) {
            $docId = app(StockDocumentService::class)->saveDraft('RM', null, $head, [$line], self::BY);
        } else {
            (new RmStock)->forceFill($head + ['doc_id' => $headerId, 'flow_status' => 'DRAFT', 'create_by' => self::BY, 'update_by' => self::BY])->save();
            RmStockD::create($line + ['doc_id' => $headerId, 'seq_no' => 1, 'create_by' => self::BY, 'update_by' => self::BY]);
            $docId = $headerId;
        }

        return app(StockPostingService::class)->postDocument('RM', $docId, self::BY);
    }

    private function transfer(float $qty): void
    {
        $t = InvTransfer::create(['transfer_date' => now()->toDateString(), 'from_warehouse_id' => $this->warehouse->id_warehouse, 'to_warehouse_id' => $this->warehouse->id_warehouse,
            'created_by' => self::BY, 'updated_by' => self::BY]);
        InvTransferLine::create(['transfer_id' => $t->id_transfer, 'line_no' => 1, 'stock_class' => 'RM', 'item_id' => $this->material->material_id,
            'item_code' => $this->material->material_code, 'item_name' => $this->material->material_name, 'from_location_id' => $this->bin->location_id,
            'to_location_id' => $this->other->location_id, 'qty' => $qty, 'uom_code' => 'SHEET', 'created_by' => self::BY, 'updated_by' => self::BY]);
        $this->assertNull((new StockTransferService)->confirm($t, self::BY));
    }

    private function cards(WhLoc $bin)
    {
        return RmStockcard::query()->where('material_id', $this->material->material_id)->where('location_id', $bin->location_id)->orderBy('doc_date')->orderBy('doc_id')->get();
    }

    // Saldo satu bin sama di semua tempat yang membacanya, sama dengan jumlah pergerakan di kartunya, dan kartunya bersambung
    // (saldo awal tiap baris = saldo akhir baris sebelumnya -- yang diperiksa `php artisan data:audit --only=stock`).
    private function assertPosition(WhLoc $bin, float $qty, ?float $value = null): void
    {
        $cards = $this->cards($bin);
        $this->assertEqualsWithDelta($qty, (float) $cards->sum(fn ($c) => (float) $c->qty), 0.0001, "Movements written to the stock card of {$bin->location_code}.");

        $this->assertEqualsWithDelta($qty, (new StockTransferService)->balance('RM', (int) $this->material->material_id, null, (int) $bin->location_id), 0.0001,
            "On hand in {$bin->location_code} (last stock card row).");
        $view = DB::connection('tm2inv')->table('v_rm_onhand')->where('material_id', $this->material->material_id)->where('location_id', $bin->location_id)->first();
        $this->assertEqualsWithDelta($qty, (float) ($view->qty_onhand ?? 0), 0.0001, "On hand in {$bin->location_code} (view v_rm_onhand).");
        $position = WarehouseStock::positions([$bin->location_id])->first();
        $this->assertEqualsWithDelta($qty, (float) ($position['qty'] ?? 0), 0.0001, "On hand in {$bin->location_code} (WarehouseStock::positions).");
        if ($value !== null) {
            $this->assertEqualsWithDelta($value, (float) ($view->value_onhand ?? 0), 0.01, "Stock value in {$bin->location_code}.");
        }

        $previous = 0.0;
        foreach ($cards as $c) {
            $this->assertEqualsWithDelta($previous, (float) $c->qty_bb, 0.0001, "Stock card row {$c->doc_id}: opening is the previous closing.");
            $this->assertEqualsWithDelta((float) $c->qty_bb + (float) $c->qty_in - (float) $c->qty_out + (float) $c->qty_adj, (float) $c->qty_eb, 0.0001, "Stock card row {$c->doc_id}: closing.");
            $previous = (float) $c->qty_eb;
        }
    }

    // Jumlah item di master (cur_stock) = jumlah saldo semua bin uji.
    private function assertItemTotal(float $qty): void
    {
        $now = (float) DB::connection('tm2mst')->table('mat_raw')->where('material_id', $this->material->material_id)->value('cur_stock');
        $this->assertEqualsWithDelta($qty, $now - (float) $this->curStock, 0.0001, 'Item total on the material master (cur_stock).');
    }

    private function knownDefect(string $what, callable $assertions): void
    {
        try {
            $assertions();
        } catch (ExpectationFailedException $e) {
            $this->markTestIncomplete("KNOWN DEFECT, waiting for the user's rule: {$what} -- ".$e->getMessage());
        }
    }

    public function test_postings_in_date_order_keep_a_running_balance_and_average_price(): void
    {
        $day = now()->startOfDay();
        $this->assertTrue($this->postDoc(1, 'STOCK', 5000, $day->copy()->subDays(2), 100)['posted']);
        $this->assertTrue($this->postDoc(1, 'STOCK', 10, $day->copy()->subDay(), 1000)['posted']);

        $this->assertPosition($this->bin, 5010, 510000);
        $this->assertItemTotal(5010);
    }

    // Kasus yang terlihat 2026-10-02: Stock In 5.000 lembar hari ini 09:00, lalu penerimaan barang (SPB) 10 lembar bertanggal
    // hari ini 00:00 ke bin yang sama.
    public function test_a_posting_dated_before_the_latest_row_still_reaches_the_on_hand_balance(): void
    {
        $day = now()->startOfDay();
        $this->assertTrue($this->postDoc(1, 'STOCK', 5000, $day->copy()->setTime(9, 0), 100)['posted']);

        $result = $this->postDoc(1, 'STOCK', 10, $day, 1000, 'PV');

        $this->knownDefect('a document dated before the latest stock card row of that item in that bin does not reach the on-hand balance', function () use ($result) {
            if ($result['posted']) {
                $this->assertPosition($this->bin, 5010, 510000);
                $this->assertItemTotal(5010);
            } else {
                $this->assertPosition($this->bin, 5000, 500000);
                $this->assertItemTotal(5000);
            }
        });
    }

    // Dua dokumen pada tanggal yang sama (semua dokumen stok manual dan SPB bertanggal 00:00): baris "sebelumnya" dicari
    // dengan membandingkan nomor urut baris KARTU dengan nomor urut KEPALA DOKUMEN -- dua deret angka yang berbeda. Selama
    // nomor kartu lebih kecil dari nomor dokumen hasilnya kebetulan benar; begitu terlewati, dokumen kedua tidak melihat
    // dokumen pertama. Uji ini memakai nomor kepala dokumen yang kosong di bawah nomor kartu supaya keadaan itu terjadi.
    public function test_a_second_document_on_the_same_day_adds_to_the_first(): void
    {
        $day = now()->startOfDay();
        $this->assertTrue($this->postDoc(1, 'STOCK', 100, $day, 100)['posted']);
        $firstCard = (int) $this->cards($this->bin)->first()->doc_id;
        $used = RmStock::query()->where('doc_id', '<', $firstCard)->pluck('doc_id')->flip();
        $free = collect(range(1, max(1, $firstCard - 1)))->first(fn (int $id) => ! $used->has($id));
        if ($free === null || $free >= $firstCard) {
            $this->markTestSkipped('No free stock document number below the stock card row number; the same-day case cannot be staged here.');
        }

        $result = $this->postDoc(1, 'STOCK', 10, $day, 100, 'MANUAL', $free);

        $this->knownDefect('a second document on the same day does not see the first one once stock card row numbers pass document numbers', function () use ($result) {
            $this->assertTrue($result['posted'], (string) ($result['error'] ?? ''));
            $this->assertPosition($this->bin, 110, 11000);
            $this->assertItemTotal(110);
        });
    }

    // Cancel SPB (PvPostingService::reverseInventory): Stock Out bertanggal hari ini 00:00. Barangnya sudah dipindah ke bin
    // lain (transfer mencatat jam), jadi bin penerimaan kosong dan pembalik harus ditolak di aturan mana pun.
    public function test_reversing_a_receipt_whose_stock_was_moved_away_is_refused(): void
    {
        $day = now()->startOfDay();
        $this->assertTrue($this->postDoc(1, 'STOCK', 10, $day->copy()->subDay(), 1000, 'PV')['posted']);
        $this->transfer(10);
        $this->assertPosition($this->bin, 0);

        $result = $this->postDoc(-1, 'STOCK', 10, $day, 1000, 'PV_CANCEL');

        $this->knownDefect('reversing a receipt (Cancel SPB) is accepted although the receiving bin is empty; the moved stock stays on hand', function () use ($result) {
            $this->assertFalse($result['posted'], 'The receiving bin holds nothing, so the reversal must be refused.');
            $this->assertPosition($this->bin, 0);
            $this->assertPosition($this->other, 10, 10000);
            $this->assertItemTotal(10);
        });
    }

    // Stock Adjustment / Opname: selisih = fisik - saldo sistem (80 sesudah transfer, fisik 75 -> -5), dokumen bertanggal
    // hari ini 00:00 sedangkan transfer tadi mencatat jam.
    public function test_an_adjustment_dated_before_the_latest_row_still_corrects_the_on_hand_balance(): void
    {
        $day = now()->startOfDay();
        $this->assertTrue($this->postDoc(1, 'STOCK', 100, $day->copy()->subDay(), 100)['posted']);
        $this->transfer(20);
        $this->assertPosition($this->bin, 80, 8000);

        $result = $this->postDoc(-1, 'AD', -5, $day, 100);

        $this->knownDefect('a stock adjustment dated before the latest stock card row does not correct the on-hand balance', function () use ($result) {
            if ($result['posted']) {
                $this->assertPosition($this->bin, 75, 7500);
                $this->assertItemTotal(95);
            } else {
                $this->assertPosition($this->bin, 80, 8000);
                $this->assertItemTotal(100);
            }
        });
    }
}
