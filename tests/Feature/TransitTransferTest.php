<?php

namespace Tests\Feature;

use App\Models\Inventory\InvPallet;
use App\Models\Master\MatRaw;
use App\Models\Master\WhLoc;
use App\Services\Inventory\PalletService;
use App\Services\Inventory\StockTransferService;
use App\Services\Inventory\VirtualLocationService;
use App\Services\Inventory\WarehouseStock;
use Tests\Feature\Concerns\BuildsTestWarehouse;
use Tests\TestCase;

// Transfer dua langkah lewat In Transit (v2.01, I-31): Ship memindahkan stok bin asal -> bin TRANSIT, Receive memindahkan TRANSIT -> bin tujuan,
// selisih menjadi Discrepancy yang diselesaikan ke LOSS atau kembali. Transfer DIRECT tidak berubah. Menulis ke database sungguhan (gedung uji ZZT-A / ZZT-B)
// dan MEMBERSIHKAN sendiri di tearDown().
class TransitTransferTest extends TestCase
{
    use BuildsTestWarehouse;

    private const BY = 'test_transit';

    private WhLoc $src;

    private WhLoc $dst;

    private WhLoc $transit;

    private WhLoc $loss;

    private MatRaw $mat;

    private StockTransferService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanFixture();
        $this->svc = new StockTransferService;
        $a = $this->makeWarehouse('ZZT-A');
        $b = $this->makeWarehouse('ZZT-B');
        $this->src = $this->makeBin($this->makeZone($a, 'STA', 'STORAGE'), 'ZZT-A-01');
        $this->dst = $this->makeBin($this->makeZone($b, 'STB', 'STORAGE'), 'ZZT-B-01');
        $virtual = new VirtualLocationService(self::BY);
        $this->transit = $virtual->transit();
        $this->loss = $virtual->loss();
        [$this->mat] = $this->freeMaterials(1);
        $this->seedStock($this->mat, $this->src, 100);
    }

    protected function tearDown(): void
    {
        $this->cleanFixture();
        $this->assertFixtureClean();
        parent::tearDown();
    }

    public function test_ship_moves_the_stock_to_the_transit_bin_and_receive_moves_it_on(): void
    {
        $doc = $this->draftTransfer($this->src, $this->dst, $this->mat, 40, 'TWO_STEP');

        $this->assertNull($this->svc->ship($doc, self::BY));
        $doc->refresh();
        $this->assertSame('SHIPPED', $doc->transit_status);
        $this->assertSame('CONFIRMED', $doc->status());
        $this->assertNotNull($doc->transfer_no);
        $this->assertEquals(60, $this->balance($this->src, $this->mat));
        $this->assertEquals(40, $this->balance($this->transit, $this->mat), 'The shipped stock sits in the In Transit bin.');
        $this->assertEquals(0, $this->balance($this->dst, $this->mat));

        $this->assertNull($this->svc->receive($doc, [], self::BY), 'No quantity given = received in full.');
        $doc->refresh();
        $this->assertSame('RECEIVED', $doc->transit_status);
        $this->assertEquals(0, $this->balance($this->transit, $this->mat));
        $this->assertEquals(40, $this->balance($this->dst, $this->mat));
        $this->assertEquals(40, (float) $doc->lines->first()->qty_received);
        $this->assertEquals(0, (float) $doc->lines->first()->qty_diff);
        $this->assertEquals(100, $this->balance($this->src, $this->mat) + $this->balance($this->dst, $this->mat), 'The item total did not change.');
    }

    public function test_a_short_receipt_leaves_the_difference_in_transit_until_it_is_booked_as_loss(): void
    {
        $doc = $this->draftTransfer($this->src, $this->dst, $this->mat, 40, 'TWO_STEP');
        $this->svc->ship($doc, self::BY);
        $line = $doc->lines()->first();

        $this->assertNull($this->svc->receive($doc, [$line->id_transfer_line => 35], self::BY));
        $doc->refresh();
        $this->assertSame('DISCREPANCY', $doc->transit_status);
        $this->assertEquals(5, (float) $line->refresh()->qty_diff);
        $this->assertEquals(35, $this->balance($this->dst, $this->mat));
        $this->assertEquals(5, $this->balance($this->transit, $this->mat), 'The missing 5 stays in In Transit.');

        $this->assertNotNull($this->svc->resolveDiscrepancy($doc, $line->id_transfer_line, 'NONSENSE', self::BY));
        $this->assertNull($this->svc->resolveDiscrepancy($doc, $line->id_transfer_line, 'LOSS', self::BY));
        $this->assertSame('CLOSED', $doc->refresh()->transit_status);
        $this->assertEquals(0, $this->balance($this->transit, $this->mat));
        $this->assertEquals(5, $this->balance($this->loss, $this->mat), 'Booked to the Loss bin.');
        $this->assertNotNull($this->svc->resolveDiscrepancy($doc, $line->id_transfer_line, 'LOSS', self::BY), 'A difference is settled only once.');
    }

    public function test_a_difference_can_be_returned_to_the_source_bin(): void
    {
        $doc = $this->draftTransfer($this->src, $this->dst, $this->mat, 40, 'TWO_STEP');
        $this->svc->ship($doc, self::BY);
        $line = $doc->lines()->first();
        $this->svc->receive($doc, [$line->id_transfer_line => 30], self::BY);

        $this->assertNull($this->svc->resolveDiscrepancy($doc, $line->id_transfer_line, 'RETURN', self::BY));
        $this->assertEquals(70, $this->balance($this->src, $this->mat));
        $this->assertEquals(30, $this->balance($this->dst, $this->mat));
        $this->assertEquals(0, $this->balance($this->transit, $this->mat));
        $this->assertSame('CLOSED', $doc->refresh()->transit_status);
    }

    public function test_receiving_more_than_was_sent_is_refused_and_nothing_moves(): void
    {
        $doc = $this->draftTransfer($this->src, $this->dst, $this->mat, 40, 'TWO_STEP');
        $this->svc->ship($doc, self::BY);
        $line = $doc->lines()->first();

        $this->assertNotNull($this->svc->receive($doc, [$line->id_transfer_line => 41], self::BY));
        $this->assertSame('SHIPPED', $doc->refresh()->transit_status);
        $this->assertEquals(40, $this->balance($this->transit, $this->mat));
    }

    public function test_a_shipped_transfer_can_be_cancelled_and_the_stock_goes_back(): void
    {
        $doc = $this->draftTransfer($this->src, $this->dst, $this->mat, 40, 'TWO_STEP');
        $this->svc->ship($doc, self::BY);

        $this->assertNull($this->svc->cancel($doc, 'Wrong truck', self::BY));
        $this->assertEquals(100, $this->balance($this->src, $this->mat));
        $this->assertEquals(0, $this->balance($this->transit, $this->mat));
        $this->assertSame('CANCELLED', $doc->refresh()->status());

        $received = $this->draftTransfer($this->src, $this->dst, $this->mat, 10, 'TWO_STEP');
        $this->svc->ship($received, self::BY);
        $this->svc->receive($received, [], self::BY);
        $this->assertNotNull($this->svc->cancel($received, 'Too late', self::BY), 'A received two-step transfer is not cancelled.');
    }

    public function test_only_a_two_step_draft_can_be_shipped_and_a_direct_transfer_still_moves_at_once(): void
    {
        $direct = $this->draftTransfer($this->src, $this->dst, $this->mat, 10, 'DIRECT');
        $this->assertNotNull($this->svc->ship($direct, self::BY));

        $this->assertNull($this->svc->confirm($direct, self::BY));
        $this->assertEquals(10, $this->balance($this->dst, $this->mat), 'Direct = one step, as before.');
        $this->assertNull($direct->refresh()->transit_status);

        $two = $this->draftTransfer($this->src, $this->dst, $this->mat, 10, 'TWO_STEP');
        $this->assertNull($this->svc->ship($two, self::BY));
        $this->assertNotNull($this->svc->ship($two, self::BY), 'Shipping twice is refused.');
    }

    public function test_a_pallet_follows_the_transfer_through_in_transit(): void
    {
        $pallet = PalletService::create(WarehouseStock::key('RM', (int) $this->mat->material_id, null, (int) $this->src->location_id), [['qty' => 100]], ['ref_doc_no' => 'SPB-TEST'], self::BY)->first();
        $doc = $this->draftTransfer($this->src, $this->dst, $this->mat, 100, 'TWO_STEP');

        $this->svc->ship($doc, self::BY);
        $this->assertSame($this->transit->location_id, InvPallet::query()->find($pallet->id_pallet)->location_id);

        $this->svc->receive($doc, [], self::BY);
        $this->assertSame($this->dst->location_id, InvPallet::query()->find($pallet->id_pallet)->location_id);
    }

    public function test_a_bin_locked_for_counting_cannot_be_shipped_from(): void
    {
        $this->src->forceFill(['count_lock' => true])->save();
        $doc = $this->draftTransfer($this->src, $this->dst, $this->mat, 10, 'TWO_STEP');

        $this->assertStringContainsString('locked for counting', (string) $this->svc->ship($doc, self::BY));
        $this->assertEquals(100, $this->balance($this->src, $this->mat));
    }
}
