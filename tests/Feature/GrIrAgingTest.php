<?php

namespace Tests\Feature;

use App\Services\Inventory\Valuation\GrIrAgingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// GR/IR aging: penerimaan belum ditagih (umur 0-30 / 31-60 / 61-90 / 90+) dan tagihan belum diterima, dicocokkan per PO, penerimaan tertua lebih dulu.
// Jalankan terhadap database dummy: powershell -NoProfile -File tools/test-ddt.ps1 tests/Feature/GrIrAgingTest.php
class GrIrAgingTest extends TestCase
{
    private const AS_OF = '2026-10-09';

    private const MARK = 'TESTGRIR';

    private int $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->supplier = (int) DB::connection('tm2mst')->table('sup_supplier')->min('id_supplier');
        if (! $this->supplier) {
            $this->markTestSkipped('Needs a supplier.');
        }
    }

    protected function tearDown(): void
    {
        $pv = DB::connection('tm2pur')->table('pv_receipt')->where('doc_no', 'like', self::MARK.'%')->pluck('pv_id');
        DB::connection('tm2pur')->table('pv_line')->whereIn('pv_id', $pv)->delete();
        DB::connection('tm2pur')->table('pv_receipt')->whereIn('pv_id', $pv)->delete();
        $inv = DB::connection('tm2acc')->table('fin_invoice_receipt')->where('notes', self::MARK)->pluck('id_invoice_receipt');
        DB::connection('tm2acc')->table('fin_invoice_receipt_line')->whereIn('invoice_receipt_id', $inv)->delete();
        DB::connection('tm2acc')->table('fin_invoice_receipt')->whereIn('id_invoice_receipt', $inv)->delete();
        parent::tearDown();
    }

    private function receipt(string $no, string $date, int $po, float $value, string $poNo): void
    {
        $pv = DB::connection('tm2pur')->table('pv_receipt')->insertGetId(['doc_no' => self::MARK.$no, 'i_receipt_type' => 'PO', 'i_doc_date' => $date, 'i_supplier_id' => $this->supplier, 'status' => 'POSTED']);
        DB::connection('tm2pur')->table('pv_line')->insert(['pv_id' => $pv, 'line_no' => 1, 'c_po_id' => $po, 'c_po_doc_no' => $poNo, 'item_id' => 1, 'is_stock' => 1, 'i_description' => 'test item',
            'i_qty_purchase' => 10, 'i_uom_purchase' => 'kg', 'c_qty_accepted' => 10, 'c_unit_price' => $value / 10]);
    }

    private function bill(int $po, string $poNo, string $date, float $amount): void
    {
        $head = DB::connection('tm2acc')->table('fin_invoice_receipt')->insertGetId(['receipt_date' => $date, 'supplier_id' => $this->supplier, 'notes' => self::MARK, 'stat_confirm' => 1]);
        DB::connection('tm2acc')->table('fin_invoice_receipt_line')->insert(['invoice_receipt_id' => $head, 'line_no' => 1, 'supplier_invoice_no' => 'T-'.$po, 'invoice_date' => $date,
            'po_id' => $po, 'po_no' => $poNo, 'amount' => $amount]);
    }

    /** @return array<string, array<string, mixed>> baris uji per kunci kind|po|doc */
    private function mine(): array
    {
        $rows = (new GrIrAgingService)->rows(CarbonImmutable::parse(self::AS_OF));
        $out = [];
        foreach ($rows as $r) {
            if (str_starts_with($r['po_no'], self::MARK)) {
                $out[$r['kind'].'|'.$r['po_no'].'|'.$r['doc_no']] = $r;
            }
        }

        return $out;
    }

    public function test_an_invoice_closes_the_oldest_receipts_first_and_the_rest_is_aged(): void
    {
        $po = 910001;
        $this->receipt('A', '2026-06-01', $po, 1000, self::MARK.'PO1');
        $this->receipt('B', '2026-08-20', $po, 500, self::MARK.'PO1');
        $this->receipt('C', '2026-10-01', $po, 300, self::MARK.'PO1');
        $this->bill($po, self::MARK.'PO1', '2026-09-01', 1200);

        $rows = $this->mine();

        $this->assertCount(2, $rows, 'receipt A is fully invoiced; B is partly invoiced and C is open');
        $b = $rows['Received not invoiced|'.self::MARK.'PO1|'.self::MARK.'B'];
        $this->assertSame(300.0, $b['amount']);
        $this->assertSame(50, $b['days']);
        $this->assertSame([0.0, 300.0, 0.0, 0.0], [$b['b1'], $b['b2'], $b['b3'], $b['b4']]);
        $c = $rows['Received not invoiced|'.self::MARK.'PO1|'.self::MARK.'C'];
        $this->assertSame([300.0, 0.0, 0.0, 0.0], [$c['b1'], $c['b2'], $c['b3'], $c['b4']]);
    }

    public function test_an_uninvoiced_old_receipt_falls_in_the_over_90_bucket(): void
    {
        $this->receipt('D', '2026-06-01', 910002, 800, self::MARK.'PO2');

        $row = $this->mine()['Received not invoiced|'.self::MARK.'PO2|'.self::MARK.'D'];

        $this->assertSame(800.0, $row['b4']);
        $this->assertSame('90+', $row['bucket']);
        $this->assertSame(130, $row['days']);
    }

    public function test_an_invoice_bigger_than_the_receipt_is_reported_as_invoiced_not_received(): void
    {
        $this->receipt('E', '2026-10-05', 910003, 100, self::MARK.'PO3');
        $this->bill(910003, self::MARK.'PO3', '2026-07-01', 250);
        $this->bill(910004, self::MARK.'PO4', '2026-09-20', 400);

        $rows = $this->mine();

        $this->assertArrayNotHasKey('Received not invoiced|'.self::MARK.'PO3|'.self::MARK.'E', $rows);
        $extra = $rows['Invoiced not received|'.self::MARK.'PO3|'];
        $this->assertSame([150.0, 100], [$extra['amount'], $extra['days']]);
        $this->assertSame(150.0, $extra['b4']);
        $alone = $rows['Invoiced not received|'.self::MARK.'PO4|'];
        $this->assertSame([400.0, 19, 400.0], [$alone['amount'], $alone['days'], $alone['b1']]);
    }

    public function test_receipts_after_the_as_of_date_are_left_out(): void
    {
        $this->receipt('F', '2026-10-20', 910005, 100, self::MARK.'PO5');

        $this->assertSame([], $this->mine());
    }
}
