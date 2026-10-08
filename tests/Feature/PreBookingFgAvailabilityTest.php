<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Inventory\FgStockcard;
use App\Models\Inventory\RmBooking;
use App\Models\Inventory\RmBookingAlloc;
use App\Models\Inventory\RmBookingD;
use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Master\WhZone;
use App\Services\Inventory\FgAvailability;
use Tests\TestCase;

// FgAvailability: satu sumber Available untuk barang jadi = stok bisa dipakai - stok FG yang dipegang booking Confirmed / Held.
// Menulis ke database sungguhan (produk fiktif di luar jangkauan nyata) dan MEMBERSIHKAN sendiri di tearDown().
class PreBookingFgAvailabilityTest extends TestCase
{
    private const BY = 'test_fgavail';

    private const PRODUCT = 999999007;

    private WhLoc $bin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
        $wh = WhWarehouse::create(['warehouse_code' => 'ZZF-A', 'warehouse_name' => 'Test ZZF-A', 'warehouse_type' => 'MIX', 'activate' => true, 'length_m' => 20, 'width_m' => 10,
            'floor_count' => 1, 'shape' => 'RECT', 'height_f1_m' => 6, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $zone = WhZone::create(['warehouse_id' => $wh->id_warehouse, 'floor_no' => 1, 'zone_code' => 'FG', 'zone_name' => 'Test FG', 'zone_type' => 'STORAGE', 'storage_mode' => 'FLOOR',
            'map_x' => 0, 'map_y' => 0, 'map_w' => 4, 'map_h' => 5, 'stack_height_m' => 2, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $this->bin = WhLoc::create(['warehouse_id' => $wh->id_warehouse, 'zone_id' => $zone->id_zone, 'location_code' => 'ZZF-FG-01', 'row_code' => 'FG', 'col_code' => '01', 'stack_code' => '1',
            'is_usable' => true, 'capacity_kg' => 100000, 'activate' => true, 'created_by' => self::BY, 'updated_by' => self::BY]);
        (new FgStockcard)->forceFill(['doc_date' => now()->subDays(3), 'product_id' => self::PRODUCT, 'doc_type' => 'BB', 'location_id' => $this->bin->location_id, 'warehouse_id' => $wh->id_warehouse,
            'qty' => 100, 'uom_code' => 'PCS', 'reference_name' => 'test:fgavail', 'reference_detail_id' => $this->bin->location_id, 'qty_bb' => 0, 'qty_in' => 100, 'qty_out' => 0, 'qty_adj' => 0,
            'qty_eb' => 100, 'avg_unitprice' => 5, 'value_eb' => 500, 'create_by' => self::BY, 'update_by' => self::BY])->save();
    }

    protected function tearDown(): void
    {
        $this->clean();
        $this->assertSame(0, FgStockcard::query()->where('create_by', self::BY)->count());
        $this->assertSame(0, RmBooking::query()->where('create_by', self::BY)->count());
        $this->assertSame(0, WhWarehouse::query()->where('created_by', self::BY)->count());
        parent::tearDown();
    }

    private function clean(): void
    {
        $ids = RmBooking::query()->where('create_by', self::BY)->pluck('booking_id')->all() ?: [0];
        RmBookingAlloc::query()->whereIn('booking_detail_id', RmBookingD::query()->whereIn('booking_id', $ids)->pluck('booking_detail_id')->all() ?: [0])->delete();
        RmBookingD::query()->whereIn('booking_id', $ids)->delete();
        RmBooking::query()->whereIn('booking_id', $ids)->delete();
        FgStockcard::query()->where('create_by', self::BY)->delete();
        $wh = WhWarehouse::query()->where('created_by', self::BY)->pluck('id_warehouse')->all() ?: [0];
        WhLoc::query()->whereIn('warehouse_id', $wh)->delete();
        WhZone::query()->whereIn('warehouse_id', $wh)->delete();
        WhWarehouse::query()->whereIn('id_warehouse', $wh)->delete();
    }

    private function booking(bool $confirmed, bool $cancelled, float $qty): RmBooking
    {
        $b = RmBooking::create(['booking_no' => 'ZZFG'.random_int(1000, 9999), 'booking_date' => now(), 'booking_type' => 'CO', 'doc_status' => $confirmed ? 'CONFIRMED' : 'DRAFT',
            'stat_confirm' => $confirmed ? 1 : 0, 'stat_cancel' => $cancelled ? 1 : 0, 'stat_closed' => 0, 'create_by' => self::BY]);
        $d = RmBookingD::create(['booking_id' => $b->booking_id, 'seq_no' => 1, 'product_id' => self::PRODUCT, 'material_role' => 'FG', 'uom_code' => 'PCS', 'qty_need' => $qty, 'line_status' => 'FG_STOCK',
            'qty_book' => 0, 'qty_purchase' => 0, 'qty_short' => 0, 'qty_replacement' => 0, 'qty_issued' => 0, 'stat_cancel' => 0, 'create_by' => self::BY]);
        RmBookingAlloc::create(['booking_detail_id' => $d->booking_detail_id, 'source_type' => 'FG', 'source_id' => self::PRODUCT, 'source_no' => 'FG stock', 'qty' => $qty, 'is_late' => 0, 'created_by' => self::BY]);

        return $b;
    }

    public function test_available_fg_is_onhand_minus_what_confirmed_bookings_hold(): void
    {
        $this->assertSame(['onhand' => 100.0, 'booked' => 0.0, 'available' => 100.0], FgAvailability::byProduct([self::PRODUCT])[self::PRODUCT]);

        $this->booking(true, false, 30);
        $this->booking(false, false, 20);   // usulan lunak: tidak mengurangi
        $this->booking(true, true, 10);     // batal: tidak mengurangi

        $r = FgAvailability::byProduct([self::PRODUCT])[self::PRODUCT];
        $this->assertSame(30.0, $r['booked']);
        $this->assertSame(70.0, $r['available']);
        $this->assertSame(70.0, FgAvailability::available(self::PRODUCT));
        $this->assertSame(0.0, FgAvailability::available(999999008), 'A product without stock has nothing available.');
        $this->assertArrayHasKey(self::PRODUCT, FgAvailability::byProduct());
    }
}
