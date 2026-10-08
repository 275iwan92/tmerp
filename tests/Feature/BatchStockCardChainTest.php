<?php

namespace Tests\Feature;

use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Support\V2\Grid\Grids;
use App\Support\V2\Grid\Inv\RmStockCardReport;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Stock Card per kelas (Raw Material / Semi Finished Goods / Finished Goods) memakai rumus bersambung yang sama dengan Stock Card semua
// kelas (user 2026-10-08): BB baris berikutnya = EB baris sebelumnya, dihitung PER ITEM dengan saldo item semua lokasi. Saldo satu lokasi
// tetap ada di kolom opsional tersembunyi Location Balance. Data FIKTIF (dua item, tahun 2099) di gedung uji sendiri; dibersihkan di tearDown().
class BatchStockCardChainTest extends TestCase
{
    private const BY = 'test_card_chain';

    private const A = 999000201;

    private const B = 999000202;

    private WhLoc $binA;

    private WhLoc $binB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
        $wh = WhWarehouse::create(['warehouse_code' => 'ZZC-A', 'warehouse_name' => 'Test ZZC-A', 'warehouse_type' => 'MIX', 'activate' => true, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $bin = fn (string $code) => WhLoc::create(['warehouse_id' => $wh->id_warehouse, 'location_code' => $code, 'location_name' => $code, 'row_code' => $code, 'col_code' => '1',
            'stack_code' => '1', 'is_usable' => true, 'activate' => true, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $this->binA = $bin('ZZC-A-01');
        $this->binB = $bin('ZZC-A-02');

        // Item A (kronologis): +100 @A, +50 @B, -30 @A, transfer 20 A->B, penyesuaian -5 @A. Item B: +7 @B paling awal, di sela item A.
        $this->card(self::B, '2099-01-01', 'IN', $this->binB, [7, 0, 0], 0, 7, 1);
        $this->card(self::A, '2099-02-01', 'IN', $this->binA, [100, 0, 0], 0, 100, 2);
        $this->card(self::A, '2099-02-02', 'IN', $this->binB, [50, 0, 0], 0, 50, 3);
        $this->card(self::A, '2099-02-03', 'OUT', $this->binA, [0, 30, 0], 100, 70, 4);
        $this->card(self::A, '2099-02-04', 'TF', $this->binA, [0, 20, 0], 70, 50, 5);
        $this->card(self::A, '2099-02-04', 'TF', $this->binB, [20, 0, 0], 50, 70, 6);
        $this->card(self::A, '2099-02-05', 'AD', $this->binA, [0, 0, -5], 50, 45, 7);
    }

    protected function tearDown(): void
    {
        $this->clean();
        $this->assertSame(0, DB::connection('tm2inv')->table('rm_stockcard')->where('create_by', self::BY)->count());
        $this->assertSame(0, WhWarehouse::query()->where('created_by', self::BY)->count());
        $this->assertSame(0, WhLoc::query()->where('created_by', self::BY)->count());
        parent::tearDown();
    }

    private function clean(): void
    {
        DB::connection('tm2inv')->table('rm_stockcard')->where('create_by', self::BY)->delete();
        WhLoc::query()->where('created_by', self::BY)->delete();
        WhWarehouse::query()->where('created_by', self::BY)->delete();
    }

    // $q = [masuk, keluar, penyesuaian]; $before / $after = saldo posisi (lokasi) sebelum / sesudah baris.
    private function card(int $item, string $date, string $type, WhLoc $bin, array $q, float $before, float $after, int $ref): void
    {
        DB::connection('tm2inv')->table('rm_stockcard')->insert(['doc_date' => $date.' 08:00:00', 'material_id' => $item, 'doc_type' => $type, 'location_id' => $bin->location_id,
            'warehouse_id' => $bin->warehouse_id, 'qty' => $q[0] - $q[1] + $q[2], 'uom_code' => 'KG', 'lot_no' => 'ZZ'.$item, 'reference_name' => 'test:chain', 'reference_detail_id' => 880000 + $ref, 'qty_bb' => $before,
            'qty_in' => $q[0], 'qty_out' => $q[1], 'qty_adj' => $q[2], 'qty_eb' => $after, 'create_by' => self::BY, 'update_by' => self::BY]);
    }

    /** @return list<array<string, mixed>> baris satu item, terbaru di atas (urutan laporan) */
    private function itemRows(array $rows, int $item): array
    {
        // Kartu uji diberi lot 'ZZ<id item>': item fiktif tidak punya nama / kode di master, lot yang membedakannya.
        return array_values(array_filter($rows, fn (array $r) => $r['lot'] === 'ZZ'.$item));
    }

    public function test_the_class_stock_card_chains_bb_to_eb_per_item_across_locations(): void
    {
        $rows = Grids::find('inv-rm-rpt-card')->rows();

        $a = $this->itemRows($rows, self::A);
        $this->assertCount(6, $a);
        $this->assertSame(['Adjustment', 'Transfer in', 'Transfer out', 'Out', 'In', 'In'], array_column($a, 'move'), 'Newest first.');
        $this->assertSame([120.0, 100.0, 120.0, 150.0, 100.0, 0.0], array_column($a, 'bb'));
        $this->assertSame([115.0, 120.0, 100.0, 120.0, 150.0, 100.0], array_column($a, 'eb'));
        // Saldo satu lokasi tetap tersedia: kolom opsional tersembunyi.
        $this->assertSame([45.0, 70.0, 50.0, 70.0, 50.0, 100.0], array_column($a, 'loc_balance'));
        $this->assertSame([50.0, 50.0, 70.0, 100.0, 0.0, 0.0], array_column($a, 'loc_begin'));

        // Item lain tidak ikut mempengaruhi rantai.
        $b = $this->itemRows($rows, self::B);
        $this->assertSame([[0.0, 7.0]], array_map(fn ($r) => [$r['bb'], $r['eb']], $b));

        // Bersambung: BB baris berikutnya (lebih baru) = EB baris sebelumnya, BB + In - Out + Adj = EB.
        for ($k = 0; $k < count($a) - 1; $k++) {
            $this->assertSame($a[$k + 1]['eb'], $a[$k]['bb'], 'Row '.$k.' does not continue from the older row.');
        }
        foreach ($a as $r) {
            $this->assertEqualsWithDelta($r['eb'], $r['bb'] + $r['in'] - $r['out'] + $r['adj'], 0.0001);
        }
    }

    public function test_the_columns_are_bb_and_eb_with_the_location_balance_hidden(): void
    {
        $cols = collect(Grids::find('inv-rm-rpt-card')->columns())->keyBy('key');

        $this->assertSame(raw('BB'), $cols['bb']['label']);
        $this->assertSame(raw('EB'), $cols['eb']['label']);
        $this->assertFalse($cols['loc_balance']['visible']);
        $this->assertFalse($cols['loc_begin']['visible']);
        $this->assertSame('Location Balance', $cols['loc_balance']['label']);
        $this->assertArrayNotHasKey('begin', $cols->all());
        $this->assertArrayNotHasKey('balance', $cols->all());
        foreach (['inv-sfg-rpt-card', 'inv-fg-rpt-card'] as $slug) {
            $this->assertSame(raw('BB'), collect(Grids::find($slug)->columns())->firstWhere('key', 'bb')['label'], $slug);
        }
    }

    public function test_when_only_the_newest_rows_are_shown_bb_still_starts_from_the_real_balance_before_them(): void
    {
        // Hanya 3 baris terbaru kartu RM yang tampil: ketiganya milik item A (tahun 2099 paling baru).
        $few = new class extends RmStockCardReport
        {
            protected int $limit = 3;
        };
        $a = $this->itemRows($few->rows(), self::A);

        $this->assertCount(3, $a);
        $this->assertSame([120.0, 100.0, 120.0], array_column($a, 'bb'), 'BB of the oldest shown row = the real balance before it, not zero.');
        $this->assertSame([115.0, 120.0, 100.0], array_column($a, 'eb'));
    }
}
