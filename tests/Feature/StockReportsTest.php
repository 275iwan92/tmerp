<?php

namespace Tests\Feature;

use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysAppMenu;
use App\Services\Inventory\StockLedger;
use App\Support\V2\AppMenu;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Grid\Grids;
use Carbon\CarbonImmutable;
use Devlib\Lang;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Laporan stok semua kelas (Inventory > Warehouse > Report): Stock Card, Stock Recapitulation, Stock Summary, Stock Opname,
// Stock Loss & Gain, Stock in Inspection -- dan buku periodenya (App\Services\Inventory\StockLedger).
// Menulis ke database sungguhan: satu item fiktif (id di luar jangkauan nyata) dengan dokumen bertanggal tahun 2099 di gedung
// uji sendiri, dan MEMBERSIHKAN sendiri di tearDown(). Saldo item nyata tidak tersentuh.
class StockReportsTest extends TestCase
{
    private const BY = 'test_stock_reports';

    private const ITEM = 999999002;

    private const KEY = 'RM|999999002';

    private WhLoc $binA;

    private WhLoc $binB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
        StockLedger::forget();
        $wh = WhWarehouse::create(['warehouse_code' => 'ZZR-A', 'warehouse_name' => 'Test ZZR-A', 'warehouse_type' => 'MIX', 'activate' => true, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $bin = fn (string $code, bool $usable) => WhLoc::create(['warehouse_id' => $wh->id_warehouse, 'location_code' => $code, 'location_name' => $code, 'row_code' => $code, 'col_code' => '1',
            'stack_code' => '1', 'is_usable' => $usable, 'activate' => true, 'block_reason' => $usable ? null : 'Test block', 'created_by' => self::BY, 'updated_by' => self::BY]);
        $this->binA = $bin('ZZR-A-01', true);
        $this->binB = $bin('ZZR-A-02', false);
        $this->seedStock();
    }

    protected function tearDown(): void
    {
        $this->clean();
        foreach (['rm_stockcard' => 'create_by', 'rm_stock_d' => 'create_by', 'rm_stock' => 'create_by', 'inv_transfer_line' => 'created_by', 'inv_transfer' => 'created_by'] as $table => $column) {
            $this->assertSame(0, $this->inv()->table($table)->where($column, self::BY)->count(), $table.' masih berisi baris uji.');
        }
        $this->assertSame(0, DB::connection('tm2pur')->table('pv_receipt')->where('created_by', self::BY)->count());
        $this->assertSame(0, DB::connection('tm2pur')->table('pv_line')->where('created_by', self::BY)->count());
        $this->assertSame(0, WhWarehouse::query()->where('created_by', self::BY)->count());
        $this->assertSame(0, WhLoc::query()->where('created_by', self::BY)->count());
        StockLedger::forget();
        parent::tearDown();
    }

    private function inv()
    {
        return DB::connection('tm2inv');
    }

    private function clean(): void
    {
        foreach (['rm_stockcard', 'rm_stock_d', 'rm_stock'] as $table) {
            $this->inv()->table($table)->where('create_by', self::BY)->delete();
        }
        $this->inv()->table('inv_transfer_line')->where('created_by', self::BY)->delete();
        $this->inv()->table('inv_transfer')->where('created_by', self::BY)->delete();
        $pur = DB::connection('tm2pur');
        $pur->table('pv_line')->whereIn('pv_id', $pur->table('pv_receipt')->where('created_by', self::BY)->pluck('pv_id')->all() ?: [0])->delete();
        $pur->table('pv_receipt')->where('created_by', self::BY)->delete();
        WhLoc::query()->where('created_by', self::BY)->delete();
        WhWarehouse::query()->where('created_by', self::BY)->delete();
    }

    // Satu baris kartu stok item uji. $q = [masuk, keluar, penyesuaian]; $after = [saldo, harga rata-rata] posisi itu sesudah baris ini.
    private function card(string $date, string $type, WhLoc $bin, array $q, array $after, float $before, string $refName, int $refId): void
    {
        $this->inv()->table('rm_stockcard')->insert(['doc_date' => $date, 'material_id' => self::ITEM, 'doc_type' => $type, 'location_id' => $bin->location_id, 'warehouse_id' => $bin->warehouse_id,
            'qty' => $q[0] - $q[1] + $q[2], 'uom_code' => 'KG', 'reference_name' => $refName, 'reference_detail_id' => $refId, 'qty_bb' => $before, 'qty_in' => $q[0], 'qty_out' => $q[1],
            'qty_adj' => $q[2], 'qty_eb' => $after[0], 'avg_unitprice' => $after[1], 'value_eb' => $after[0] * $after[1], 'create_by' => self::BY, 'update_by' => self::BY]);
    }

    // Dokumen stok satu baris + (bila diposting) baris kartunya. Kembalian = id baris dokumen.
    private function document(string $no, string $date, int $headType, string $cat, string $status, WhLoc $bin, array $line): int
    {
        $docId = $this->inv()->table('rm_stock')->insertGetId(['doc_no' => $no, 'doc_date' => $date, 'doc_type' => $headType, 'doc_cat' => $cat, 'warehouse_id' => $bin->warehouse_id,
            'reason_code' => 'COUNT_ERROR', 'flow_status' => $status, 'stat_confirm' => $status === 'POSTED' ? 1 : 0, 'create_by' => self::BY, 'update_by' => self::BY]);

        return $this->inv()->table('rm_stock_d')->insertGetId(['doc_id' => $docId, 'seq_no' => 1, 'material_id' => self::ITEM, 'location_id' => $bin->location_id, 'uom_code' => 'KG',
            'create_by' => self::BY, 'update_by' => self::BY] + $line);
    }

    // Februari 2099: masuk 100 @ 50 (jadi saldo awal Maret). Maret 2099: masuk 50 @ 80, keluar 30, transfer 20 ke bin B (diblokir),
    // stock opname -5 di bin A, penyesuaian +2 di bin B, dan satu stock opname yang masih Draft (belum mengubah stok).
    private function seedStock(): void
    {
        $ref = 'tm2inv.rm_stock_d';
        $a = $this->binA;
        $b = $this->binB;
        $this->card('2099-02-10 08:00:00', 'IN', $a, [100, 0, 0], [100, 50], 0, $ref, $this->document('ZZR-IN1', '2099-02-10 08:00:00', 1, 'STOCK', 'POSTED', $a, ['qty' => 100, 'unit_price' => 50]));
        $this->card('2099-03-05 08:00:00', 'IN', $a, [50, 0, 0], [150, 60], 100, $ref, $this->document('ZZR-IN2', '2099-03-05 08:00:00', 1, 'STOCK', 'POSTED', $a, ['qty' => 50, 'unit_price' => 80]));
        $this->card('2099-03-10 08:00:00', 'OUT', $a, [0, 30, 0], [120, 60], 150, $ref, $this->document('ZZR-OUT1', '2099-03-10 08:00:00', -1, 'STOCK', 'POSTED', $a, ['qty' => 30]));

        $transferId = $this->inv()->table('inv_transfer')->insertGetId(['transfer_no' => 'ZZR-TF1', 'transfer_date' => '2099-03-12', 'from_warehouse_id' => $a->warehouse_id,
            'to_warehouse_id' => $b->warehouse_id, 'stat_confirm' => 1, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $lineId = $this->inv()->table('inv_transfer_line')->insertGetId(['transfer_id' => $transferId, 'stock_class' => 'RM', 'item_id' => self::ITEM, 'from_location_id' => $a->location_id,
            'to_location_id' => $b->location_id, 'qty' => 20, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $this->card('2099-03-12 08:00:00', 'TF', $a, [0, 20, 0], [100, 60], 120, 'tm2inv.inv_transfer_line:OUT', $lineId);
        $this->card('2099-03-12 08:00:00', 'TF', $b, [20, 0, 0], [20, 60], 0, 'tm2inv.inv_transfer_line:IN', $lineId);

        $this->card('2099-03-15 08:00:00', 'AD', $a, [0, 0, -5], [95, 60], 100, $ref,
            $this->document('ZZR-OP1', '2099-03-15 08:00:00', 1, 'OP', 'POSTED', $a, ['qty' => -5, 'qty_system' => 100, 'qty_physical' => 95, 'unit_price' => 60]));
        $this->card('2099-03-20 08:00:00', 'AD', $b, [0, 0, 2], [22, 60], 20, $ref,
            $this->document('ZZR-AD1', '2099-03-20 08:00:00', 1, 'AD', 'POSTED', $b, ['qty' => 2, 'qty_system' => 20, 'qty_physical' => 22, 'unit_price' => 60]));
        $this->document('ZZR-OP2', '2099-03-25 08:00:00', 1, 'OP', 'DRAFT', $a, ['qty' => 2, 'qty_system' => 95, 'qty_physical' => 97, 'unit_price' => 60]);
    }

    private function rows(string $slug, string $from = '2099-03-01', string $to = '2099-03-31', array $params = []): array
    {
        $grid = Grids::find($slug);
        $this->assertNotNull($grid, $slug);

        return $grid->between(CarbonImmutable::parse($from), CarbonImmutable::parse($to))->with($params)->rows();
    }

    private function find(array $rows, string $id): array
    {
        $row = collect($rows)->firstWhere('id', $id);
        $this->assertNotNull($row, 'Baris '.$id.' tidak ada di laporan.');

        return $row;
    }

    public function test_period_ledger_ties_opening_movements_and_closing(): void
    {
        $item = StockLedger::between(CarbonImmutable::parse('2099-03-01'), CarbonImmutable::parse('2099-03-31'))->items()[self::KEY];

        $this->assertSame([100.0, 50.0, 30.0, -3.0, 0.0, 117.0], [$item['opening_qty'], $item['in'], $item['out'], $item['adj'], $item['transfer'], $item['closing_qty']]);
        $this->assertEqualsWithDelta(5000.0, $item['opening_value'], 0.001);
        $this->assertEqualsWithDelta(4000.0, $item['in_value'], 0.001);
        $this->assertEqualsWithDelta(1800.0, $item['out_value'], 0.001);
        $this->assertEqualsWithDelta(-180.0, $item['adj_value'], 0.001);
        $this->assertEqualsWithDelta(0.0, $item['transfer_value'], 0.001);
        $this->assertEqualsWithDelta(7020.0, $item['closing_value'], 0.001);
        $this->assertSame(4, $item['moves'], 'A stock transfer is not a movement of the item.');

        // Periode sesudahnya: saldo akhir Maret = saldo awal April, tanpa pergerakan.
        $april = StockLedger::between(CarbonImmutable::parse('2099-04-01'), CarbonImmutable::parse('2099-04-30'))->items()[self::KEY];
        $this->assertSame([117.0, 117.0, 0], [$april['opening_qty'], $april['closing_qty'], $april['moves']]);
        $this->assertEqualsWithDelta(7020.0, $april['opening_value'], 0.001);
    }

    // Stock Card = rumus bersambung (user 2026-10-08): BB / EB tiap baris adalah saldo ITEM (semua lokasi), BB baris berikutnya = EB baris sebelumnya,
    // transfer = Out di lokasi asal + In di lokasi tujuan, lalu satu baris Total per item yang cocok dengan baris pertama dan terakhir.
    // Saldo satu lokasi (qty_eb kartu stok) tetap ada di kolom opsional Location Balance.
    public function test_stock_card_chains_bb_to_eb_across_locations_and_the_total_row_ties(): void
    {
        $rows = $this->rows('inv-rpt-card', params: ['item' => '#'.self::ITEM]);

        $this->assertSame(['In', 'Out', 'Transfer out', 'Transfer in', 'Adjustment', 'Adjustment', 'Total'], array_column($rows, 'move'));
        $a = 'Test ZZR-A · ZZR-A-01';
        $b = 'Test ZZR-A · ZZR-A-02';
        $this->assertSame([$a, $a, $a, $b, $a, $b, 'Total'], array_column($rows, 'location'));
        $this->assertSame([100.0, 150.0, 120.0, 100.0, 120.0, 115.0, 100.0], array_column($rows, 'bb'));
        $this->assertSame([50.0, 0.0, 0.0, 20.0, 0.0, 0.0, 70.0], array_column($rows, 'in'));
        $this->assertSame([0.0, 30.0, 20.0, 0.0, 0.0, 0.0, 50.0], array_column($rows, 'out'));
        $this->assertSame([0.0, 0.0, 0.0, 0.0, -5.0, 2.0, -3.0], array_column($rows, 'adj'));
        $this->assertSame([150.0, 120.0, 100.0, 120.0, 115.0, 117.0, 117.0], array_column($rows, 'eb'));
        // Saldo per lokasi tetap tersedia sebagai kolom opsional.
        $this->assertSame([150.0, 120.0, 100.0, 20.0, 95.0, 22.0], array_slice(array_column($rows, 'loc_eb'), 0, 6));

        // Bersambung: BB baris berikutnya = EB baris sebelumnya; Total = BB baris pertama dan EB baris terakhir; BB + In - Out + Adj = EB.
        $moves = array_slice($rows, 0, 6);
        for ($k = 1; $k < count($moves); $k++) {
            $this->assertSame($moves[$k - 1]['eb'], $moves[$k]['bb'], 'Baris '.$k.' tidak bersambung ke baris sebelumnya.');
        }
        $total = $rows[6];
        $this->assertSame($moves[0]['bb'], $total['bb']);
        $this->assertSame($moves[5]['eb'], $total['eb']);
        $this->assertSame('accent', $total['tone'], 'Rantai konsisten: baris Total tidak diberi warna bahaya.');
        foreach ($rows as $r) {
            $this->assertEqualsWithDelta($r['eb'], $r['bb'] + $r['in'] - $r['out'] + $r['adj'], 0.0001, $r['id']);
            // Versi gudang: harga dan nilai tidak ikut di baris sama sekali.
            $this->assertSame([], array_intersect(array_keys($r), ['value', 'avg_price', 'amount', 'price']));
        }
        $this->assertSame('ZZR-TF1', $rows[2]['doc_no']);
        $this->assertSame(['2099-03-05', '2099-03-10', '2099-03-12', '2099-03-12', '2099-03-15', '2099-03-20', null], array_column($rows, 'doc_date'));
        // Kepala kartu = nama item (dengan kode dan satuan), lalu periode.
        $this->assertStringContainsString('#'.self::ITEM.' · KG · ', $rows[0]['card']);
        $this->assertStringContainsString('2099', $rows[0]['card']);
        $this->assertCount(1, array_unique(array_column($rows, 'card')));

        // Tanpa saringan item: hanya item yang bergerak; April tidak memuat item uji, kecuali disaring namanya (hanya baris Total).
        $this->assertNotEmpty(array_filter($this->rows('inv-rpt-card'), fn ($r) => $r['item_code'] === '#'.self::ITEM));
        $this->assertSame([], array_filter($this->rows('inv-rpt-card', '2099-04-01', '2099-04-30'), fn ($r) => $r['item_code'] === '#'.self::ITEM));
        $april = $this->rows('inv-rpt-card', '2099-04-01', '2099-04-30', ['item' => '#'.self::ITEM]);
        $this->assertSame([['Total', 117.0, 0.0, 0.0, 0.0, 117.0]], array_map(fn ($r) => [$r['move'], $r['bb'], $r['in'], $r['out'], $r['adj'], $r['eb']], $april));
    }

    public function test_stock_card_screen_and_print_show_only_the_card_columns(): void
    {
        $grid = Grids::find('inv-rpt-card')->between(CarbonImmutable::parse('2099-03-01'), CarbonImmutable::parse('2099-03-31'))->with(['item' => '#'.self::ITEM]);

        $shown = array_values(array_filter($grid->columns(), fn ($c) => ($c['visible'] ?? true) !== false));
        $this->assertSame(['doc_date', 'location', 'bb', 'in', 'out', 'adj', 'eb'], array_column($shown, 'key'));
        $this->assertSame(['Date', 'Location', 'BB', 'In', 'Out', 'Adj', 'EB'], array_map(fn ($c) => Lang::plain($c['label']), $shown));
        $this->assertSame([], array_filter($grid->columns(), fn ($c) => ($c['sortable'] ?? true) !== false), 'Urutan kartu tidak boleh diubah.');
        $this->assertSame([], array_intersect(array_column($grid->columns(), 'key'), ['value', 'avg_price']));

        $print = $grid->printData();
        $this->assertSame(['doc_date', 'location', 'bb', 'in', 'out', 'adj', 'eb'], array_column($print['columns'], 'key'));
        $this->assertSame(['Item', 'Period'], array_slice(array_keys($print['criteria']), 0, 2), 'Kepala cetakan: item dulu, lalu periode.');
        $this->assertSame([['BB', 100.0], ['In', 70.0], ['Out', 50.0], ['Adj', -3.0], ['EB', 117.0]], array_map(fn ($s) => [$s['label'], $s['value']], $grid->printSummary()));
    }

    public function test_recapitulation_and_summary_agree(): void
    {
        $recap = $this->rows('inv-rpt-recap');
        $row = $this->find($recap, self::KEY);
        $this->assertSame([100.0, 50.0, 30.0, -3.0, 117.0, 'With movement', 'In stock'], [$row['opening'], $row['in'], $row['out'], $row['adj'], $row['closing'], $row['activity'], $row['stock']]);
        $this->assertEqualsWithDelta(7020.0, $row['closing_value'], 0.001);
        $this->assertEqualsWithDelta(60.0, $row['avg_price'], 0.001);
        $this->assertEqualsWithDelta(round(1800 / ((5000 + 7020) / 2), 2), $row['turnover'], 0.001);

        // Ringkasan = jumlah rekap: nilai awal + masuk - keluar + penyesuaian = nilai akhir di tiap baris, total sama dengan rekap.
        $summary = $this->rows('inv-rpt-summary');
        foreach ($summary as $s) {
            $this->assertEqualsWithDelta($s['closing_value'], $s['opening_value'] + $s['in_value'] - $s['out_value'] + $s['adj_value'], 0.01, $s['id']);
        }
        foreach (['opening_value', 'in_value', 'out_value', 'adj_value', 'closing_value'] as $k) {
            $this->assertEqualsWithDelta(array_sum(array_column($recap, $k)), array_sum(array_column($summary, $k)), 0.01, $k);
        }
        $this->assertSame(count($recap), (int) array_sum(array_column($summary, 'items')));
        // Tahun 2099 hanya berisi dokumen uji ini.
        $this->assertEqualsWithDelta(4000.0, array_sum(array_column($summary, 'in_value')), 0.01);
        $this->assertEqualsWithDelta(100.0, array_sum(array_column($summary, 'share')), 0.6);
    }

    public function test_opname_lists_every_count_and_loss_gain_only_what_was_posted(): void
    {
        $opname = collect($this->rows('inv-rpt-opname'))->keyBy('doc_no');
        $this->assertSame(['ZZR-AD1', 'ZZR-OP1', 'ZZR-OP2'], $opname->keys()->sort()->values()->all());
        $this->assertSame(['Physical count', 'POSTED', 100.0, 95.0, -5.0, 'Loss', 'ZZR-A-01'],
            array_map(fn (string $k) => $opname['ZZR-OP1'][$k], ['kind', 'status', 'system', 'physical', 'diff', 'result', 'bin']));
        $this->assertEqualsWithDelta(-300.0, $opname['ZZR-OP1']['value'], 0.001);
        $this->assertSame(['Adjustment', 'Gain'], [$opname['ZZR-AD1']['kind'], $opname['ZZR-AD1']['result']]);
        $this->assertSame(['DRAFT', 'Gain'], [$opname['ZZR-OP2']['status'], $opname['ZZR-OP2']['result']]);

        $rows = $this->rows('inv-rpt-loss-gain', '2099-01-01', '2099-12-31');
        $this->assertCount(1, $rows, 'Tahun 2099 hanya berisi satu item uji di satu bulan.');
        $row = $rows[0];
        $this->assertStringStartsWith('2099-03', $row['month']);
        $this->assertSame([2.0, 5.0, -3.0, 2, 'Loss'], [$row['gain'], $row['loss'], $row['net'], $row['documents'], $row['result']]);
        $this->assertEqualsWithDelta(120.0, $row['gain_value'], 0.001);
        $this->assertEqualsWithDelta(300.0, $row['loss_value'], 0.001);
        $this->assertEqualsWithDelta(-180.0, $row['net_value'], 0.001);
        $this->assertSame([], $this->rows('inv-rpt-loss-gain', '2099-04-01', '2099-12-31'));
    }

    public function test_inspection_lists_stock_in_a_blocked_bin(): void
    {
        $held = collect(Grids::find('inv-rpt-inspection')->rows())->firstWhere('reference', 'ZZR-A-02');

        $this->assertNotNull($held, 'Stok di bin yang diblokir harus tampil.');
        $this->assertSame(['Blocked bin', 22.0, 'Test block'], [$held['state'], $held['qty'], $held['note']]);
        $this->assertEqualsWithDelta(1320.0, $held['value'], 0.001);
        $this->assertNull(collect(Grids::find('inv-rpt-inspection')->rows())->firstWhere('reference', 'ZZR-A-01'), 'Bin yang bisa dipakai tidak ikut.');
    }

    public function test_inspection_lists_receipt_lines_waiting_for_qc_and_rejected_quantities(): void
    {
        $waitingBefore = $this->incomingWaiting();
        $pur = DB::connection('tm2pur');
        $pvId = $pur->table('pv_receipt')->insertGetId(['i_doc_date' => now()->subDays(4)->toDateString(), 'i_receipt_type' => 'PO', 'status' => 'QC_PENDING',
            'created_by' => self::BY, 'updated_by' => self::BY]);
        $line = fn (int $no, array $qc) => $pur->table('pv_line')->insertGetId(['pv_id' => $pvId, 'line_no' => $no, 'i_description' => 'ZZR receipt line '.$no, 'c_item_code' => 'ZZR-'.$no,
            'i_qty_purchase' => 100, 'i_uom_purchase' => 'KG', 'i_qty_stock' => 100, 'i_uom_stock' => 'KG', 'created_by' => self::BY, 'updated_by' => self::BY] + $qc);
        // Bawaan kolom QC = PENDING (bukan NULL): baris yang belum dinilai.
        $waiting = $line(1, []);
        $rejected = $line(2, ['qc_qty_status' => 'DONE', 'qc_quality_status' => 'PARTIAL', 'c_qty_accepted' => 90, 'c_qty_rejected' => 10, 'qc_at' => now()->subDays(2)]);
        $passed = $line(3, ['qc_qty_status' => 'DONE', 'qc_quality_status' => 'PASS', 'c_qty_accepted' => 100, 'c_qty_rejected' => 0, 'qc_at' => now()->subDays(2)]);

        $rows = collect(Grids::find('inv-rpt-inspection')->rows())->keyBy('id');
        $this->assertSame(['Waiting for inspection', 100.0, 4, 'Draft #'.$pvId], array_map(fn (string $k) => $rows['PV-'.$waiting][$k], ['state', 'qty', 'days', 'reference']));
        $this->assertSame(['Rejected, to return', 10.0, 2, 'danger'], array_map(fn (string $k) => $rows['PV-'.$rejected][$k], ['state', 'qty', 'days', 'tone']));
        $this->assertFalse($rows->has('PV-'.$passed), 'Baris yang lolos QC tidak tertahan.');
        // Widget Incoming QC memakai aturan "belum dinilai" yang sama.
        $this->assertSame($waitingBefore + 1, $this->incomingWaiting());
    }

    private function incomingWaiting(): int
    {
        $n = new Numbers(CarbonImmutable::now());

        return $n->incomingQc($n->from(), $n->to())['waiting'];
    }

    public function test_period_comes_from_the_query_string_and_reports_open_and_print(): void
    {
        $grid = Grids::find('inv-rpt-recap');
        $this->assertSame([now()->startOfMonth()->toDateString(), now()->toDateString()], array_map(fn ($d) => $d->toDateString(), $grid->period()));
        request()->query->set('from', '2099-03-31');
        request()->query->set('to', '2099-03-01');
        $this->assertSame(['2099-03-01', '2099-03-31'], array_map(fn ($d) => $d->toDateString(), Grids::find('inv-rpt-recap')->period()), 'Tanggal terbalik ditukar.');
        request()->query->set('from', 'not-a-date');
        $this->assertSame(now()->startOfMonth()->toDateString(), Grids::find('inv-rpt-recap')->period()[0]->toDateString());
        $this->assertNull(Grids::find('inv-rpt-inspection')->period());
        $this->assertNull(Grids::find('inv-rm-rpt-card')->printUrlOf(), 'Laporan lama tidak berubah: tanpa periode, tanpa Print.');

        $print = $this->rowsForPrint();
        $this->assertSame(['Period', 'Rows'], array_keys($print['criteria']));
        $this->assertNotContains('turnover', array_column($print['columns'], 'key'), 'Kolom tersembunyi tidak ikut dicetak.');
        $this->assertSame('item_code', collect($print['columns'])->firstWhere('key', 'item')['sub']);

        $this->actingAs(SecUser::query()->where('is_admin', 1)->firstOrFail());
        $query = '?from=2099-03-01&to=2099-03-31';
        foreach (['inv-rpt-card', 'inv-rpt-recap', 'inv-rpt-summary', 'inv-rpt-opname', 'inv-rpt-loss-gain', 'inv-rpt-inspection', 'inv-rpt-location'] as $slug) {
            $this->get(route('v2.screen', $slug).$query)->assertOk();
        }
        $this->get(route('v2.screen', 'inv-rpt-opname').$query)->assertSee('ZZR-OP1');
        $this->get(route('v2.report.print', 'inv-rpt-recap').$query)->assertOk()->assertSee('#'.self::ITEM);
        $this->get(route('v2.report.print', 'inv-rm-rpt-card'))->assertNotFound();
    }

    // Menu v2: laporan stok ada di pohon menu dan tiap barisnya membuka layar v2 yang benar.
    public function test_reports_are_reachable_from_the_v2_menu(): void
    {
        if (! SysAppMenu::query()->where('code', 'INV_WH_RPT_CARD')->where('activate', true)->exists()) {
            $this->markTestSkipped('Baris menu laporan stok belum ada di database ini.');
        }
        $this->actingAs(SecUser::query()->where('is_admin', 1)->firstOrFail());
        $codes = function (array $nodes) use (&$codes): array {
            $out = [];
            foreach ($nodes as $n) {
                $kids = $n['items'] ?? $n['children'] ?? null;
                $out = $kids === null ? [...$out, $n['code'] ?? ''] : [...$out, ...$codes($kids)];
            }

            return $out;
        };
        $inTree = $codes(AppMenu::modules(auth()->user()));

        // Stock by Location pindah ke modul Warehouse (menu 2.01 split), tidak lagi di grup laporan Inventory.
        foreach (['INV_WH_RPT_CARD' => 'inv-rpt-card', 'INV_WH_RPT_RECAP' => 'inv-rpt-recap', 'INV_WH_RPT_SUM' => 'inv-rpt-summary',
            'INV_WH_RPT_OPN' => 'inv-rpt-opname', 'INV_WH_RPT_LG' => 'inv-rpt-loss-gain', 'INV_WH_RPT_QC' => 'inv-rpt-inspection'] as $code => $slug) {
            $this->assertContains($code, $inTree, $code);
            $this->assertSame(route('v2.screen', $slug), AppMenu::resolve($code)[1], $code);
        }
    }

    private function rowsForPrint(): array
    {
        return Grids::find('inv-rpt-recap')->between(CarbonImmutable::parse('2099-03-01'), CarbonImmutable::parse('2099-03-31'))->printData();
    }
}
