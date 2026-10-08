<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Inventory\RmBooking;
use App\Models\Inventory\RmBookingD;
use App\Models\Inventory\RmStockcard;
use App\Models\Master\MatRaw;
use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Master\WhZone;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysConstant;
use App\Services\Planning\BookingMaintenance;
use App\Services\Planning\MaterialPlanningEngine;
use App\Support\V2\Data\MaterialPlanning;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

// Booking sejak costing (B-01..B-04, B-06): ProCost terbit tanpa CO -> pre-booking lunak B0 (B1 bila quotation terbit), satu header per rantai
// inquiry, Pick Item, label "Not CO yet" di Shortage Planner, Hold yang menahan stok lalu habis sendiri, CO Confirmed mengambil alih header yang
// sama, dan booking basi. Menulis ke database sungguhan (inquiry / costing / quotation / CO fiktif, satu bahan tanpa kartu stok) dan MEMBERSIHKAN
// sendiri di tearDown().
class PreBookingTest extends TestCase
{
    private const BY = 'test_prebooking';

    private WhWarehouse $wh;

    private WhLoc $bin;

    private MatRaw $paper;

    private int $inquiryId;

    private int $costingId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
        $this->wh = WhWarehouse::create(['warehouse_code' => 'ZZB-A', 'warehouse_name' => 'Test ZZB-A', 'warehouse_type' => 'MIX', 'activate' => true, 'length_m' => 20, 'width_m' => 10,
            'floor_count' => 1, 'shape' => 'RECT', 'height_f1_m' => 6, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $zone = WhZone::create(['warehouse_id' => $this->wh->id_warehouse, 'floor_no' => 1, 'zone_code' => 'PPR', 'zone_name' => 'Test paper', 'zone_type' => 'STORAGE', 'storage_mode' => 'FLOOR',
            'map_x' => 0, 'map_y' => 0, 'map_w' => 4, 'map_h' => 5, 'stack_height_m' => 2, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $this->bin = WhLoc::create(['warehouse_id' => $this->wh->id_warehouse, 'zone_id' => $zone->id_zone, 'location_code' => 'ZZB-PPR-01', 'row_code' => 'PPR', 'col_code' => '01', 'stack_code' => '1',
            'is_usable' => true, 'capacity_kg' => 100000, 'activate' => true, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $this->paper = MatRaw::withoutGlobalScopes()->where('activate', true)->whereNotIn('material_id', RmStockcard::query()->whereNotNull('material_id')->distinct()->pluck('material_id'))->orderBy('material_id')->firstOrFail();
        (new RmStockcard)->forceFill(['doc_date' => now()->subDays(5), 'material_id' => $this->paper->material_id, 'doc_type' => 'BB', 'location_id' => $this->bin->location_id, 'warehouse_id' => $this->wh->id_warehouse,
            'qty' => 100, 'uom_code' => 'SHEET', 'reference_name' => 'test:prebooking', 'reference_detail_id' => $this->bin->location_id, 'qty_bb' => 0, 'qty_in' => 100, 'qty_out' => 0, 'qty_adj' => 0, 'qty_eb' => 100,
            'avg_unitprice' => 10, 'value_eb' => 1000, 'create_by' => self::BY, 'update_by' => self::BY])->save();

        $sdi = DB::connection('tm2sdi');
        $this->inquiryId = $sdi->table('iqp_inquiry')->insertGetId(['inquiry_no' => 'ZZPRE1', 'inquiry_date' => now()->toDateString(), 'product_draft_name' => 'ZZ pre-booking product', 'stat_cancel' => 0, 'created_by' => self::BY]);
        $sdi->table('iqp_tier')->insert(['inquiry_id' => $this->inquiryId, 'tier_seq' => 1, 'qty' => 1000, 'is_primary' => 1, 'created_by' => self::BY]);
        $partId = $sdi->table('iqp_part')->insertGetId(['inquiry_id' => $this->inquiryId, 'part_seq' => 1, 'part_name' => 'Body', 'qty_per_sku' => 1, 'created_by' => self::BY]);
        $this->costingId = $sdi->table('iqp_costing')->insertGetId(['inquiry_id' => $this->inquiryId, 'doc_status' => 'PUBLISHED', 'published_flag' => 1, 'created_by' => self::BY]);
        $sdi->table('iqp_costing_part')->insert(['costing_id' => $this->costingId, 'part_id' => $partId, 'no_urut' => 1, 'i_panjang_plano_cm' => 65, 'i_lebar_plano_cm' => 90, 'i_cut_per_plano' => 8,
            'i_isi_per_lb' => 1, 'supplier_sheet_code' => 'ZZS', 'qty_per_set' => 1, 'created_by' => self::BY]);
        MaterialPlanningEngine::forget();
    }

    protected function tearDown(): void
    {
        $this->clean();
        MaterialPlanningEngine::forget();
        $this->assertSame(0, RmBooking::query()->where('create_by', self::BY)->count());
        $this->assertSame(0, RmBooking::query()->whereNotNull('inquiry_id')->whereNotIn('inquiry_id', DB::connection('tm2sdi')->table('iqp_inquiry')->select('id_inquiry'))->count(), 'No booking of a removed test inquiry is left.');
        $this->assertSame(0, RmStockcard::query()->where('create_by', self::BY)->count());
        foreach (['iqp_inquiry', 'iqp_tier', 'iqp_part', 'iqp_costing', 'iqp_costing_part', 'quo_quotation', 'quo_quotation_item', 'co_order', 'co_order_item'] as $table) {
            $this->assertSame(0, DB::connection('tm2sdi')->table($table)->where('created_by', self::BY)->count(), $table.' masih berisi baris uji.');
        }
        $this->assertSame(0, WhWarehouse::query()->where('created_by', self::BY)->count());
        parent::tearDown();
    }

    private function clean(): void
    {
        $sdi = DB::connection('tm2sdi');
        $inquiryIds = $sdi->table('iqp_inquiry')->where('created_by', self::BY)->pluck('id_inquiry')->all() ?: [0];
        // Booking yang ditulis lewat route (pengguna admin) bukan create_by BY: ikut dibersihkan lewat inquiry ujinya.
        $bookingIds = RmBooking::query()->where('create_by', self::BY)->orWhereIn('inquiry_id', $inquiryIds)->pluck('booking_id')->all() ?: [0];
        DB::connection('tm2inv')->table('rm_booking_alloc')->whereIn('booking_detail_id', RmBookingD::query()->whereIn('booking_id', $bookingIds)->pluck('booking_detail_id')->all() ?: [0])->delete();
        RmBookingD::query()->whereIn('booking_id', $bookingIds)->delete();
        RmBooking::query()->whereIn('booking_id', $bookingIds)->delete();
        $sdi->table('co_order_item')->where('created_by', self::BY)->delete();
        $sdi->table('co_order')->where('created_by', self::BY)->delete();
        $sdi->table('quo_quotation_item')->where('created_by', self::BY)->delete();
        $sdi->table('quo_quotation')->where('created_by', self::BY)->delete();
        $sdi->table('iqp_costing_part')->whereIn('costing_id', $sdi->table('iqp_costing')->where('created_by', self::BY)->pluck('id_costing')->all() ?: [0])->delete();
        $sdi->table('iqp_costing')->where('created_by', self::BY)->delete();
        $sdi->table('iqp_tier')->whereIn('inquiry_id', $inquiryIds)->delete();
        $sdi->table('iqp_part')->whereIn('inquiry_id', $inquiryIds)->delete();
        $sdi->table('iqp_inquiry')->whereIn('id_inquiry', $inquiryIds)->delete();
        RmStockcard::query()->where('create_by', self::BY)->delete();
        $ids = WhWarehouse::query()->where('created_by', self::BY)->pluck('id_warehouse')->all() ?: [0];
        WhLoc::query()->whereIn('warehouse_id', $ids)->delete();
        WhZone::query()->whereIn('warehouse_id', $ids)->delete();
        WhWarehouse::query()->whereIn('id_warehouse', $ids)->delete();
    }

    private function engine(): MaterialPlanningEngine
    {
        MaterialPlanningEngine::forget();

        return new MaterialPlanningEngine(self::BY);
    }

    private function demand(?MaterialPlanningEngine $e = null): ?array
    {
        return ($e ?? $this->engine())->preDemands()->firstWhere('inquiry_id', $this->inquiryId);
    }

    private function header(): ?RmBooking
    {
        return RmBooking::query()->where('chain_key', (string) $this->inquiryId)->where('stat_cancel', 0)->first();
    }

    private function available(): float
    {
        return (float) DB::connection('tm2inv')->table('v_rm_available')->where('material_id', $this->paper->material_id)->where('location_id', $this->bin->location_id)->value('qty_available');
    }

    private function quotation(string $validUntil, bool $cancelled = false): int
    {
        $sdi = DB::connection('tm2sdi');
        $q = $sdi->table('quo_quotation')->insertGetId(['quotation_no' => 'ZZQ'.random_int(1000, 9999), 'customer_id' => 1, 'issued_date' => now()->toDateString(), 'valid_until' => $validUntil,
            'doc_status' => 'PUBLISHED', 'stat_cancel' => $cancelled ? 1 : 0, 'created_by' => self::BY]);
        $sdi->table('quo_quotation_item')->insert(['quotation_id' => $q, 'source_type' => 'INQUIRY', 'inquiry_id' => $this->inquiryId, 'description' => 'ZZ item', 'created_by' => self::BY]);

        return $q;
    }

    public function test_published_costing_without_a_co_becomes_a_pre_booking_from_costing_size_columns_only(): void
    {
        $d = $this->demand();

        $this->assertNotNull($d, 'A published ProCost without a Customer Order is a pre-booking demand.');
        $this->assertSame('B0', $d['stage']);
        $this->assertSame('Not CO yet', $d['co_status']);
        $this->assertSame(MaterialPlanningEngine::TIER_PRE, $d['tier']);
        $this->assertEquals(1000, $d['qty']);
        $waste = (float) SysConstant::number('BOOKING_DEFAULT_WASTE_PCT', 5);
        $lines = $this->engine()->explodePre($d);
        $this->assertCount(1, $lines);
        $this->assertSame('SUBSTRATE', $lines[0]['role']);
        $this->assertSame('NEED_ITEM', $lines[0]['status'], 'The costing does not name the stock item: PPIC picks it.');
        $this->assertSame((int) ceil(ceil(1000 / 8) * (1 + $waste / 100)), $lines[0]['qty']);
        $this->assertStringContainsString('sheet ZZS', (string) $lines[0]['spec']);
        $this->assertStringContainsString('650 × 900 mm', (string) $lines[0]['spec']);
        $this->assertStringNotContainsStringIgnoringCase('price', json_encode($lines[0]), 'No costing price column is read.');
    }

    public function test_run_writes_one_soft_header_per_inquiry_and_does_not_duplicate_it(): void
    {
        $before = $this->available();
        $e = $this->engine();
        $e->runPre();
        $head = $this->header();

        $this->assertNotNull($head);
        $this->assertSame('PRE', $head->booking_type);
        $this->assertSame('B0', $head->stage);
        $this->assertNull($head->co_id);
        $this->assertSame($this->costingId, (int) $head->costing_id);
        $this->assertSame(0, (int) $head->stat_confirm);
        $this->assertCount(1, $head->lines);

        $this->engine()->runPre();
        $this->assertSame(1, RmBooking::query()->where('chain_key', (string) $this->inquiryId)->where('stat_cancel', 0)->count());
        $this->assertSame($head->booking_id, $this->header()->booking_id, 'The same header is rewritten, not a second booking.');
        $this->assertSame($before, $this->available(), 'A pre-booking does not reduce Available.');
    }

    public function test_pick_item_covers_from_stock_and_the_material_is_labelled_not_co_yet(): void
    {
        $e = $this->engine();
        $e->runPre();
        $line = $this->header()->lines->first();

        $e = $this->engine();
        $e->pick($line, (int) $this->paper->material_id);

        $line = $this->header()->lines->first()->refresh();
        $this->assertSame((int) $this->paper->material_id, (int) $line->material_id);
        $this->assertSame('PICK', $line->note_detail);
        $this->assertEquals(100, $line->allocs->where('source_type', 'STOCK')->sum('qty'));
        $this->assertGreaterThan(0, (float) $line->qty_short);
        $row = collect(MaterialPlanning::materialRows())->firstWhere('id', (int) $this->paper->material_id);
        $this->assertSame('Not CO yet', $row['stage'], 'B-04: a shortage that only comes from a pre-booking is labelled.');
        $this->assertEquals(100, $this->available(), 'Still soft: stock is not reduced.');
        $coRow = collect(MaterialPlanning::coRows())->firstWhere('co', 'ZZPRE1');
        $this->assertSame('Not CO yet', $coRow['co_status']);
        $this->assertSame('PRE-BOOKING', $coRow['booking']);
    }

    public function test_quotation_lifts_to_b1_and_an_expired_quotation_ends_the_chain(): void
    {
        $this->engine()->runPre();
        $q = $this->quotation(now()->addDays(10)->toDateString());
        $d = $this->demand();
        $this->assertSame('B1', $d['stage']);
        $this->assertSame(MaterialPlanningEngine::TIER_QUOTATION, $d['tier']);

        $this->engine()->runPre();
        $this->assertSame('B1', $this->header()->stage);
        $this->assertSame(1, RmBooking::query()->where('chain_key', (string) $this->inquiryId)->where('stat_cancel', 0)->count(), 'Same header B0 to B1.');

        DB::connection('tm2sdi')->table('quo_quotation')->where('id_quotation', $q)->update(['valid_until' => now()->subDay()->toDateString()]);
        $this->assertNull($this->demand(), 'An expired quotation ends the pre-booking.');
        $this->engine()->runPre();
        $this->assertNull($this->header(), 'The soft header is released.');
    }

    public function test_hold_locks_the_stock_and_ends_by_itself_after_the_hold_days(): void
    {
        $e = $this->engine();
        $e->runPre();
        $e = $this->engine();
        $e->pick($this->header()->lines->first(), (int) $this->paper->material_id);
        $head = $this->header();

        $this->engine()->hold($head, 'critical paper');

        $head->refresh();
        $this->assertSame('HELD', $head->doc_status);
        $this->assertSame(1, (int) $head->stat_confirm);
        $this->assertSame(self::BY, $head->hold_by);
        $days = (int) SysConstant::number('INV_BOOKING_HOLD_WORKDAYS', 3);
        $this->assertTrue(CarbonImmutable::parse($head->hold_until)->gte(CarbonImmutable::today()->addDays($days)), 'hold_until is at least the hold days ahead.');
        $this->assertEquals(0, $this->available(), 'A hold keeps the stock like a hard booking.');
        $this->assertNull($this->demand(), 'A held inquiry is read from the frozen booking, not counted twice.');
        $held = collect($this->engine()->plan()['lines'])->where('type', 'PRE')->where('inquiry_id', $this->inquiryId);
        $this->assertCount(1, $held);
        $this->assertSame(MaterialPlanningEngine::TIER_CONFIRMED, $held->first()['tier']);

        $res = (new BookingMaintenance(self::BY))->run(true, CarbonImmutable::parse($head->hold_until)->addDay());
        $this->assertContains($head->booking_no, $res['holds']);
        $this->assertEquals(0, $this->available(), 'Dry run changes nothing.');
        $res = (new BookingMaintenance(self::BY))->run(false, CarbonImmutable::parse($head->hold_until)->addDay());
        $this->assertContains($head->booking_no, $res['holds']);
        $this->assertEquals(100, $this->available(), 'The hold ended by itself: stock is available again.');
        $this->assertSame('DRAFT', $this->header()->doc_status);
    }

    public function test_hold_is_refused_for_a_booking_that_belongs_to_a_customer_order(): void
    {
        $this->engine()->runPre();
        $head = $this->header();
        $head->update(['co_id' => 987654]);

        $this->expectException(HttpException::class);
        $this->engine()->hold($head);
    }

    public function test_confirmed_customer_order_takes_over_the_same_header(): void
    {
        $this->engine()->runPre();
        $headId = $this->header()->booking_id;
        $sdi = DB::connection('tm2sdi');
        $co = $sdi->table('co_order')->insertGetId(['co_no' => 'ZZCO'.random_int(1000, 9999), 'co_date' => now()->toDateString(), 'doc_status' => 'CONFIRMED', 'stat_cancel' => 0, 'created_by' => self::BY]);
        $sdi->table('co_order_item')->insert(['order_id' => $co, 'line_kind' => 'PRODUCT', 'inquiry_id' => $this->inquiryId, 'description' => 'ZZ line', 'qty' => 1000, 'created_by' => self::BY]);

        $this->assertNull($this->demand(), 'An inquiry with a live CO is handled by the CO path, never counted twice.');
        $booking = $this->engine()->persist($co);

        $this->assertSame($headId, $booking->booking_id, 'One chain, one header: the pre-booking header becomes the CO booking.');
        $this->assertSame($co, (int) $booking->co_id);
        $this->assertSame('CO', $booking->booking_type);
        $this->assertSame('B2', $booking->stage);
        $this->assertSame((string) $this->inquiryId, $booking->chain_key);
        $this->assertSame(1, RmBooking::query()->where('chain_key', (string) $this->inquiryId)->where('stat_cancel', 0)->count());
    }

    public function test_stale_confirmed_booking_is_warned_then_released(): void
    {
        $head = RmBooking::create(['booking_no' => 'ZZBKSTALE', 'booking_date' => now(), 'booking_type' => 'CO', 'co_id' => 987001, 'doc_status' => 'CONFIRMED', 'stat_confirm' => 1,
            'stat_cancel' => 0, 'stat_closed' => 0, 'create_by' => self::BY]);
        RmBookingD::create(['booking_id' => $head->booking_id, 'seq_no' => 1, 'material_id' => $this->paper->material_id, 'material_role' => 'SUBSTRATE', 'uom_code' => 'SHEET', 'qty_need' => 50,
            'need_date' => now()->toDateString(), 'line_status' => 'STOCK', 'qty_book' => 0, 'qty_purchase' => 0, 'qty_short' => 0, 'qty_replacement' => 0, 'qty_issued' => 0, 'stat_cancel' => 0, 'create_by' => self::BY]);
        $warn = (int) SysConstant::number('INV_BOOKING_STALE_WARN_DAYS', 7);
        $release = (int) SysConstant::number('INV_BOOKING_STALE_RELEASE_DAYS', 30);
        $svc = new BookingMaintenance(self::BY);

        $this->assertSame(['holds' => [], 'warned' => [], 'released' => []], array_map(fn ($l) => array_values(array_intersect($l, ['ZZBKSTALE'])), $svc->run(false, CarbonImmutable::today()->addDays($warn))));
        $res = $svc->run(false, CarbonImmutable::today()->addDays($warn + 1));
        $this->assertContains('ZZBKSTALE', $res['warned']);
        $this->assertNotNull($head->refresh()->stale_warned_on);
        $this->assertNotContains('ZZBKSTALE', $svc->run(false, CarbonImmutable::today()->addDays($warn + 2))['warned'], 'The warning is sent once.');
        $this->assertSame(0, (int) $head->refresh()->stat_cancel);

        $res = $svc->run(false, CarbonImmutable::today()->addDays($release + 1));
        $this->assertContains('ZZBKSTALE', $res['released']);
        $this->assertSame(1, (int) $head->refresh()->stat_cancel, 'After the release days the booking is released and the stock is free again.');
    }

    public function test_pre_booking_screens_and_routes_work(): void
    {
        $this->actingAs(SecUser::query()->where('is_admin', 1)->firstOrFail());

        $this->post(route('v2.planning.prebooking.run'))->assertRedirect();
        $head = $this->header();
        $this->assertNotNull($head);
        $this->get(route('v2.screen', 'material-prebooking'))->assertOk()->assertSee('ZZPRE1');
        $url = route('v2.screen', 'material-prebooking').'?id='.$head->booking_id;
        $this->get($url)->assertOk()->assertSee($head->booking_no)->assertSee('Pick Item');
        $this->post(route('v2.planning.prebooking.pick', $head->booking_id), ['line_id' => $head->lines->first()->booking_detail_id, 'material_id' => $this->paper->material_id])->assertRedirect($url);
        $this->post(route('v2.planning.prebooking.act', $head->booking_id), ['action' => 'hold', 'reason' => 'test'])->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('Release Hold');
        $this->post(route('v2.planning.prebooking.act', $head->booking_id), ['action' => 'release'])->assertRedirect($url);
        $this->assertSame('DRAFT', $this->header()->doc_status);
        $this->get(route('v2.screen', 'material-booking'))->assertOk();
        $this->get(route('v2.screen', 'material-planning'))->assertOk();
    }
}
