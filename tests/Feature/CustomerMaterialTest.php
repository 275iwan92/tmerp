<?php

namespace Tests\Feature;

use App\Models\Inventory\RmStock;
use App\Models\Inventory\RmStockcard;
use App\Models\Master\CustCustomer;
use App\Models\Master\MatRaw;
use App\Models\Master\WhWarehouse;
use App\Models\Pur\PvLine;
use App\Models\Pur\PvReceipt;
use App\Services\Inventory\PickAllocator;
use App\Services\Pur\SpecialReceiptService;
use App\Support\V2\Grid\Inv\CustomerMaterialReport;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsTestWarehouse;
use Tests\TestCase;

// Material titipan customer v2.01 (D3): penerimaan tipe CUSTOMER dengan baris bahan menjadi stok RM berpemilik CUSTOMER di bin area CONSIGN, tanpa nilai
// dan tanpa jurnal. Menulis ke database sungguhan (gedung ZZM-A) dan MEMBERSIHKAN sendiri di tearDown().
class CustomerMaterialTest extends TestCase
{
    use BuildsTestWarehouse;

    private const BY = 'test_custmat';

    private WhWarehouse $wh;

    private MatRaw $mat;

    private CustCustomer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanFixture();
        $this->wh = $this->makeWarehouse('ZZM-A');
        [$this->mat] = $this->freeMaterials(1);
        $this->customer = CustCustomer::withoutGlobalScopes()->orderBy('id_customer')->firstOrFail();
    }

    protected function tearDown(): void
    {
        $this->cleanFixture();
        $this->assertFixtureClean();
        parent::tearDown();
    }

    private function receipt(float $qty, ?int $materialId): PvReceipt
    {
        $pv = PvReceipt::create(['doc_no' => null, 'i_receipt_type' => 'CUSTOMER', 'i_doc_date' => today(), 'i_customer_id' => $this->customer->id_customer, 'i_warehouse_code' => 'ZZM-A',
            'status' => 'DRAFT', 'created_by' => self::BY, 'updated_by' => self::BY]);
        PvLine::create(['pv_id' => $pv->pv_id, 'line_no' => 1, 'item_id' => $materialId, 'is_stock' => false, 'i_description' => 'Customer paper', 'i_qty_purchase' => $qty, 'i_uom_purchase' => 'KG',
            'c_qty_accepted' => $qty, 'qc_qty_status' => 'DONE', 'qc_quality_status' => 'PASS', 'created_by' => self::BY, 'updated_by' => self::BY]);

        return $pv;
    }

    private function vouchers(): int
    {
        return DB::connection('tm2acc')->table('fin_voucher')->count();
    }

    public function test_a_customer_receipt_becomes_customer_owned_stock_in_the_consign_bin_without_value_or_journal(): void
    {
        $bin = $this->makeBin($this->makeZone($this->wh, 'CSG', 'CONSIGN'), 'ZZM-A-01');
        $before = $this->vouchers();
        $pv = $this->receipt(30, $this->mat->material_id);

        $this->assertSame([], SpecialReceiptService::post($pv, self::BY));

        $card = RmStockcard::query()->where('material_id', $this->mat->material_id)->where('location_id', $bin->location_id)->firstOrFail();
        $this->assertSame('CUSTOMER', $card->owner_type);
        $this->assertSame((int) $this->customer->id_customer, (int) $card->owner_id);
        $this->assertEquals(30, (float) $card->qty_eb);
        $this->assertNull($card->value_in, 'Customer material has no value.');
        $this->assertEquals(0, (float) $card->value_eb);
        $this->assertSame($before, $this->vouchers(), 'No journal.');
        $this->assertNotNull($pv->refresh()->rm_stock_doc_id);
        $this->assertSame('POSTED', $pv->status);
        $this->assertSame('CUSTOMER', PickAllocator::ownerOf((int) $this->mat->material_id, (int) $bin->location_id, null)['type']);

        $row = collect((new CustomerMaterialReport)->rows())->firstWhere('item_code', $this->mat->material_code);
        $this->assertNotNull($row);
        $this->assertEquals(30, $row['qty']);
        $this->assertSame('ZZM-A-01', $row['bin']);
        $this->assertSame(trim($this->customer->customer_name), $row['customer']);
    }

    public function test_without_a_consign_bin_the_receipt_is_refused_and_nothing_is_posted(): void
    {
        $this->makeBin($this->makeZone($this->wh, 'STD', 'STORAGE'), 'ZZM-A-02');
        $pv = $this->receipt(30, $this->mat->material_id);

        $errors = SpecialReceiptService::post($pv, self::BY);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Consignment', $errors[0]);
        $this->assertSame('DRAFT', $pv->refresh()->status);
        $this->assertSame(0, RmStockcard::query()->where('create_by', self::BY)->count());
    }

    public function test_a_line_without_a_material_stays_a_note_and_makes_no_stock(): void
    {
        $this->makeBin($this->makeZone($this->wh, 'CSG', 'CONSIGN'), 'ZZM-A-01');
        $pv = $this->receipt(30, null);

        $this->assertSame([], SpecialReceiptService::post($pv, self::BY));
        $this->assertNull($pv->refresh()->rm_stock_doc_id);
        $this->assertSame(0, RmStock::query()->where('create_by', self::BY)->count());
    }
}
