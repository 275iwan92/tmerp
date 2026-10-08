<?php

namespace Tests\Feature;

use App\Models\Inventory\InvSubconIssue;
use App\Models\Inventory\RmStockcard;
use App\Models\Master\MatRaw;
use App\Models\Master\SupSupplier;
use App\Models\Master\WhLoc;
use App\Models\Pur\PoLine;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PvLine;
use App\Models\Pur\PvReceipt;
use App\Services\Inventory\SubconStockRefused;
use App\Services\Inventory\SubconStockService;
use App\Services\Inventory\VirtualLocationService;
use App\Support\V2\Grid\Inv\StockAtVendorReport;
use Tests\Feature\Concerns\BuildsTestWarehouse;
use Tests\TestCase;

// Bahan di tangan subkon v2.01 (D2, D22): kirim memindahkan stok ke bin SUBCON vendor (tetap milik perusahaan dan bernilai), penerimaan hasil subkon
// mengurangi saldo vendor sebanding, sisanya dikembalikan atau dibatalkan. Menulis ke database sungguhan (gedung ZZS-A, PO uji) dan MEMBERSIHKAN sendiri.
class SubconStockTest extends TestCase
{
    use BuildsTestWarehouse;

    private const BY = 'test_subcon';

    private WhLoc $bin;

    private WhLoc $vendorBin;

    private MatRaw $mat;

    private PoOrder $po;

    private PoLine $poLine;

    private SupSupplier $supplier;

    private SubconStockService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanFixture();
        $this->svc = new SubconStockService;
        $this->bin = $this->makeBin($this->makeZone($this->makeWarehouse('ZZS-A'), 'STS', 'STORAGE'), 'ZZS-A-01');
        [$this->mat] = $this->freeMaterials(1);
        $this->seedStock($this->mat, $this->bin, 100);
        $this->supplier = SupSupplier::query()->where('activate', true)->orderBy('id_supplier')->firstOrFail();
        $this->po = PoOrder::create(['doc_no' => 'ZZS-PO1', 'i_doc_date' => today(), 'i_supplier_id' => $this->supplier->id_supplier, 'i_po_type' => 'SUBCONTRACT', 'i_procure_type' => 'ONE_TIME',
            'i_fx_rate' => 1, 'status' => 'SENT', 'created_by' => self::BY, 'updated_by' => self::BY]);
        $this->poLine = PoLine::create(['po_id' => $this->po->po_id, 'line_no' => 1, 'i_description' => 'Subcon test', 'i_line_class' => 'SERVICE', 'i_qty' => 100, 'i_uom' => 'LBR',
            'i_price' => 1000, 'i_disc_pct' => 0, 'is_stock' => false, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $this->vendorBin = (new VirtualLocationService(self::BY))->vendor((int) $this->supplier->id_supplier);
    }

    protected function tearDown(): void
    {
        $this->cleanFixture();
        $this->assertFixtureClean();
        parent::tearDown();
    }

    private function send(float $qty = 40, ?string $due = null): InvSubconIssue
    {
        return $this->svc->send((int) $this->po->po_id, (int) $this->bin->location_id, (int) $this->mat->material_id, $qty, (int) $this->poLine->po_line_id, $due, null, self::BY);
    }

    // Penerimaan SUBCON terposting sebanyak $qty dari baris PO uji (tanpa jurnal: yang diuji hanya efek ke stok vendor).
    private function receipt(float $qty): PvReceipt
    {
        $pv = PvReceipt::create(['doc_no' => 'ZZS-PV'.random_int(100, 999), 'i_receipt_type' => 'SUBCON', 'i_doc_date' => today(), 'i_supplier_id' => $this->supplier->id_supplier, 'status' => 'POSTED',
            'created_by' => self::BY, 'updated_by' => self::BY]);
        PvLine::create(['pv_id' => $pv->pv_id, 'line_no' => 1, 'po_line_id' => $this->poLine->po_line_id, 'c_po_id' => $this->po->po_id, 'i_description' => 'Subcon test', 'is_stock' => false,
            'i_qty_purchase' => $qty, 'i_uom_purchase' => 'LBR', 'qc_qty_status' => 'DONE', 'qc_quality_status' => 'PASS', 'created_by' => self::BY, 'updated_by' => self::BY]);

        return $pv;
    }

    public function test_send_moves_the_stock_to_the_vendor_bin_and_keeps_it_valued(): void
    {
        $issue = $this->send(40, now()->addDays(7)->toDateString());

        $this->assertMatchesRegularExpression('/^SC\d{7}$/', $issue->issue_no);
        $this->assertSame($this->po->po_no ?? 'ZZS-PO1', $issue->po_no);
        $this->assertSame('SENT', $issue->status);
        $this->assertEquals(60, $this->balance($this->bin, $this->mat));
        $this->assertEquals(40, $this->balance($this->vendorBin, $this->mat));
        $card = RmStockcard::query()->where('material_id', $this->mat->material_id)->where('location_id', $this->vendorBin->location_id)->orderByDesc('doc_id')->first();
        $this->assertSame('COMPANY', $card->owner_type, 'Ownership stays with the company.');
        $this->assertEquals(400, (float) $card->value_eb, 'The value (40 x 10) moves with the stock.');
        $this->assertSame($this->vendorBin->location_id, $issue->lines->first()->vendor_location_id);
        $this->assertEquals(40, $issue->lines->first()->atVendor());
    }

    public function test_send_is_refused_for_a_closed_or_non_subcon_po_too_much_or_zero(): void
    {
        $this->po->forceFill(['i_po_type' => 'LOCAL'])->save();
        $this->assertRefused(fn () => $this->send(10));
        $this->po->forceFill(['i_po_type' => 'SUBCONTRACT', 'stat_cancel' => true])->save();
        $this->assertRefused(fn () => $this->send(10));
        $this->po->forceFill(['stat_cancel' => false])->save();
        $this->assertRefused(fn () => $this->send(101));
        $this->assertRefused(fn () => $this->send(0));
        $this->assertEquals(100, $this->balance($this->bin, $this->mat));
        $this->assertSame(0, InvSubconIssue::query()->where('created_by', self::BY)->count(), 'A refused send leaves no document behind.');
    }

    private function assertRefused(callable $do): void
    {
        try {
            $do();
            $this->fail('Expected the send to be refused.');
        } catch (SubconStockRefused) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_stock_at_vendor_report_lists_what_is_left_with_its_age(): void
    {
        $issue = $this->send(40, now()->subDay()->toDateString());
        $row = collect((new StockAtVendorReport)->rows())->firstWhere('send', $issue->issue_no);

        $this->assertNotNull($row);
        $this->assertEquals(40, $row['qty']);
        $this->assertSame(0, $row['age']);
        $this->assertSame('Overdue', $row['check'], 'The due date passed yesterday.');
        $this->assertSame(trim($this->supplier->supplier_name), $row['supplier']);
    }

    public function test_the_subcon_receipt_reduces_the_vendor_balance_in_proportion(): void
    {
        $issue = $this->send(40);

        $done = $this->svc->settleReceipt($this->receipt(25), self::BY);
        $this->assertEquals([$issue->issue_no => 10.0], $done, '25 of the 100 on the PO came back, so a quarter of the 40 sent is used.');
        $this->assertEquals(30, $this->balance($this->vendorBin, $this->mat));
        $this->assertEquals(10, $issue->lines()->first()->qty_consumed);
        $this->assertSame('SENT', $issue->refresh()->status);

        $this->svc->settleReceipt($this->receipt(75), self::BY);
        $this->assertEquals(0, $this->balance($this->vendorBin, $this->mat));
        $this->assertSame('CLOSED', $issue->refresh()->status, 'Everything is used, so the send is closed.');
        $this->assertNull(collect((new StockAtVendorReport)->rows())->firstWhere('send', $issue->issue_no));
    }

    public function test_unused_material_is_returned_to_a_bin_and_cannot_be_returned_twice(): void
    {
        $issue = $this->send(40);
        $line = $issue->lines()->first();

        $this->svc->returnUnused($line, 15, (int) $this->bin->location_id, self::BY);
        $this->assertEquals(75, $this->balance($this->bin, $this->mat));
        $this->assertEquals(25, $this->balance($this->vendorBin, $this->mat));
        $this->assertEquals(15, $line->refresh()->qty_returned);

        $this->assertRefused(fn () => $this->svc->returnUnused($line, 26, (int) $this->bin->location_id, self::BY));
        $this->svc->consume($line, 25, self::BY);
        $this->assertSame('CLOSED', $issue->refresh()->status);
        $this->assertEquals(0, $this->balance($this->vendorBin, $this->mat));
    }

    public function test_a_send_is_cancelled_only_while_nothing_was_used_or_returned(): void
    {
        $issue = $this->send(40);
        $this->svc->cancel($issue, 'Wrong vendor', self::BY);
        $this->assertEquals(100, $this->balance($this->bin, $this->mat));
        $this->assertEquals(0, $this->balance($this->vendorBin, $this->mat));
        $this->assertSame('CANCELLED', $issue->refresh()->status);

        $second = $this->send(10);
        $this->svc->returnUnused($second->lines()->first(), 2, (int) $this->bin->location_id, self::BY);
        $this->assertRefused(fn () => $this->svc->cancel($second, 'Too late', self::BY));
    }
}
