<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widgets\StockDocuments;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Widget Stock Documents (Numbers::stockDocuments): menghitung DOKUMEN stok per arah dari doc_type KARTU stok
// (IN / OUT / AD / BB / TF), bukan dari angka 1 / -1 di kepala dokumen. Menulis ke database sungguhan: dokumen uji bertanggal
// tahun 2099 dengan item dan lokasi yang tidak ada (saldo item nyata tidak tersentuh), dan MEMBERSIHKAN sendiri di tearDown().
class BoardStockDocumentsTest extends TestCase
{
    private const BY = 'test_board_stock_docs';

    private const FAKE_ID = 999999001;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
    }

    protected function tearDown(): void
    {
        $this->clean();
        foreach (['rm_stockcard' => 'create_by', 'fg_stockcard' => 'create_by', 'rm_stock_d' => 'create_by', 'fg_stock_d' => 'create_by', 'rm_stock' => 'create_by',
            'fg_stock' => 'create_by', 'inv_transfer_line' => 'created_by', 'inv_transfer' => 'created_by'] as $table => $column) {
            $this->assertSame(0, $this->inv()->table($table)->where($column, self::BY)->count(), $table.' masih berisi baris uji.');
        }
        parent::tearDown();
    }

    private function inv()
    {
        return DB::connection('tm2inv');
    }

    private function clean(): void
    {
        foreach (['rm_stockcard', 'fg_stockcard', 'rm_stock_d', 'fg_stock_d', 'rm_stock', 'fg_stock'] as $table) {
            $this->inv()->table($table)->where('create_by', self::BY)->delete();
        }
        $this->inv()->table('inv_transfer_line')->where('created_by', self::BY)->delete();
        $this->inv()->table('inv_transfer')->where('created_by', self::BY)->delete();
    }

    private function card(string $class, string $date, string $cardType, string $refName, int $refId): void
    {
        $this->inv()->table($class === 'FG' ? 'fg_stockcard' : 'rm_stockcard')->insert([
            'doc_date' => $date, $class === 'FG' ? 'product_id' : 'material_id' => self::FAKE_ID, 'doc_type' => $cardType, 'location_id' => self::FAKE_ID,
            'qty' => 1, 'reference_name' => $refName, 'reference_detail_id' => $refId, 'qty_eb' => 0, 'create_by' => self::BY, 'update_by' => self::BY]);
    }

    // Satu dokumen stok yang sudah diposting: kepala (doc_type angka), $lines baris, dan satu baris kartu per baris dokumen.
    private function document(string $class, string $no, string $date, int $headType, string $cat, string $cardType, int $lines = 1): void
    {
        [$head, $line, $item] = $class === 'FG' ? ['fg_stock', 'fg_stock_d', 'product_id'] : ['rm_stock', 'rm_stock_d', 'material_id'];
        $docId = $this->inv()->table($head)->insertGetId(['doc_no' => $no, 'doc_date' => $date, 'doc_type' => $headType, 'doc_cat' => $cat, 'stat_confirm' => 1,
            'create_by' => self::BY, 'update_by' => self::BY]);
        for ($i = 1; $i <= $lines; $i++) {
            $detailId = $this->inv()->table($line)->insertGetId(['doc_id' => $docId, 'seq_no' => $i, $item => self::FAKE_ID, 'qty' => 1, 'location_id' => self::FAKE_ID,
                'create_by' => self::BY, 'update_by' => self::BY]);
            $this->card($class, $date, $cardType, 'tm2inv.'.$line, $detailId);
        }
    }

    private function counted(string $from, string $to): array
    {
        return (new Numbers(CarbonImmutable::parse('2099-03-15 10:00:00')))->stockDocuments(CarbonImmutable::parse($from), CarbonImmutable::parse($to));
    }

    public function test_stock_documents_are_counted_by_stock_card_type_one_per_document(): void
    {
        $none = ['in' => 0, 'out' => 0, 'adjust' => 0, 'return' => 0];
        $this->assertSame($none, $this->counted('2099-01-01', '2099-12-31'));

        $this->document('RM', 'ZZT-SD-IN', '2099-03-10 08:00:00', 1, 'STOCK', 'IN', 3);
        $this->document('FG', 'ZZT-SD-IN', '2099-03-10 09:00:00', 1, 'STOCK', 'IN');
        $this->document('RM', 'ZZT-SD-OUT', '2099-03-11 08:00:00', -1, 'STOCK', 'OUT', 2);
        $this->document('RM', 'ZZT-SD-ADJ', '2099-03-12 08:00:00', 1, 'ADJ', 'AD');
        $this->document('RM', 'ZZT-SD-OPN', '2099-03-12 09:00:00', 1, 'OP', 'ADJ');
        $this->document('RM', 'ZZT-SD-RET', '2099-03-13 08:00:00', -1, 'RETURN', 'IN');
        // Saldo awal bukan penerimaan: kartu BB, dan data lama yang kartunya IN dengan doc_cat BB.
        $this->document('RM', 'ZZT-SD-BB', '2099-03-01 08:00:00', 1, 'BB', 'BB');
        $this->document('RM', 'ZZT-SD-BB2', '2099-03-01 09:00:00', 1, 'BB', 'IN');
        $this->document('RM', 'ZZT-SD-APR', '2099-04-01 00:00:00', 1, 'STOCK', 'IN');
        // Stock Transfer: dua baris kartu TF per baris transfer, bukan penerimaan dan bukan pengeluaran.
        $transferId = $this->inv()->table('inv_transfer')->insertGetId(['transfer_no' => 'ZZT-SD-TF', 'transfer_date' => '2099-03-14', 'from_warehouse_id' => self::FAKE_ID,
            'to_warehouse_id' => self::FAKE_ID, 'stat_confirm' => 1, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $lineId = $this->inv()->table('inv_transfer_line')->insertGetId(['transfer_id' => $transferId, 'stock_class' => 'RM', 'item_id' => self::FAKE_ID,
            'from_location_id' => self::FAKE_ID, 'to_location_id' => self::FAKE_ID, 'qty' => 1, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $this->card('RM', '2099-03-14 08:00:00', 'TF', 'tm2inv.inv_transfer_line:OUT', $lineId);
        $this->card('RM', '2099-03-14 08:00:00', 'TF', 'tm2inv.inv_transfer_line:IN', $lineId);
        // Baris kartu tanpa dokumen (ditulis langsung): bukan dokumen.
        $this->card('RM', '2099-03-15 08:00:00', 'IN', 'test:board_stock_docs', 1);

        // 14 baris kartu di bulan Maret, 6 dokumen yang dihitung.
        $this->assertSame(14, $this->inv()->table('v_inv_movement')->whereBetween('doc_date', ['2099-03-01', '2099-03-31 23:59:59'])->count());
        $this->assertSame(['in' => 2, 'out' => 1, 'adjust' => 2, 'return' => 1], $this->counted('2099-03-01', '2099-03-31'));
        $this->assertSame(['in' => 2, 'out' => 0, 'adjust' => 0, 'return' => 0], $this->counted('2099-03-10', '2099-03-10'));
        $this->assertSame(['in' => 1, 'out' => 0, 'adjust' => 0, 'return' => 0], $this->counted('2099-04-01', '2099-04-30'));

        // Widget: urutan seri = Goods in, Goods out, Adjustment / count, Return.
        $user = (new SecUser)->forceFill(['id_user' => self::FAKE_ID, 'login' => 'board.test.stock', 'name' => 'Board Test', 'is_admin' => true]);
        $data = (new StockDocuments)->build(new Numbers(CarbonImmutable::parse('2099-03-15 10:00:00'), 'monthly'), $user)['data'];
        $this->assertSame([2, 1, 2, 1], $data['series'][0]['data']);
    }
}
