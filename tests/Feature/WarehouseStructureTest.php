<?php

namespace Tests\Feature;

use App\Models\Master\SupSupplier;
use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Master\WhZone;
use App\Services\Inventory\BinGenerator;
use App\Services\Inventory\VirtualLocationService;
use App\Support\V2\Data\Warehouse;
use App\Support\V2\Grid\Inv\StorageAreaLookup;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Struktur gudang v2.01: jenis area virtual (otomatis is_virtual, tidak di peta, tanpa Generate Bins), aturan area di layar Storage Areas
// (Mixing, Pick Order, Count Every) dan VirtualLocationService (Loss, In Transit, subkon per supplier; idempoten).
// Menulis ke database sungguhan (gedung uji ZZS-A + gudang maya) dan MEMBERSIHKAN sendiri di tearDown().
class WarehouseStructureTest extends TestCase
{
    private const BY = 'test_whstruct';

    private WhWarehouse $wh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
        $this->wh = WhWarehouse::create(['warehouse_code' => 'ZZS-A', 'warehouse_name' => 'Test ZZS-A', 'warehouse_type' => 'MIX', 'activate' => true, 'length_m' => 20, 'width_m' => 10,
            'floor_count' => 1, 'shape' => 'RECT', 'created_by' => self::BY, 'updated_by' => self::BY]);
    }

    protected function tearDown(): void
    {
        $this->clean();
        $this->assertSame(0, WhLoc::query()->where('created_by', self::BY)->count());
        $this->assertSame(0, WhZone::query()->where('created_by', self::BY)->count());
        $this->assertSame(0, WhWarehouse::query()->where('created_by', self::BY)->count());
        parent::tearDown();
    }

    private function clean(): void
    {
        $ids = WhWarehouse::query()->where('created_by', self::BY)->pluck('id_warehouse')->all();
        WhLoc::query()->whereIn('warehouse_id', $ids ?: [0])->delete();
        WhZone::query()->whereIn('warehouse_id', $ids ?: [0])->delete();
        WhWarehouse::query()->whereIn('id_warehouse', $ids ?: [0])->delete();
    }

    private function row(array $over = []): array
    {
        return $over + ['id' => null, 'warehouse_id' => $this->wh->id_warehouse, 'floor_no' => 1, 'zone_code' => 'ZZA', 'zone_name' => 'Test area', 'zone_type' => 'STORAGE', 'storage_mode' => 'FLOOR',
            'stock_class' => 'RM', 'map_x' => 0, 'map_y' => 0, 'map_w' => 5, 'map_h' => 5, 'mix_rule' => 'ONE_ITEM', 'pick_order' => 'FEFO', 'count_every_days' => 30, 'activate' => true];
    }

    public function test_is_virtual_follows_the_area_type_whenever_the_zone_is_saved(): void
    {
        $zone = WhZone::create(['warehouse_id' => $this->wh->id_warehouse, 'zone_code' => 'ZZT', 'zone_name' => 'Transit', 'zone_type' => 'TRANSIT', 'storage_mode' => 'AREA', 'created_by' => self::BY]);
        $this->assertTrue($zone->refresh()->is_virtual);

        $zone->update(['zone_type' => 'STORAGE']);
        $this->assertFalse($zone->refresh()->is_virtual);

        foreach (WhZone::VIRTUAL_TYPES as $type) {
            $zone->update(['zone_type' => $type]);
            $this->assertTrue($zone->refresh()->is_virtual, $type);
        }
        foreach (array_diff(array_keys(WhZone::TYPES), WhZone::VIRTUAL_TYPES) as $type) {
            $zone->update(['zone_type' => $type]);
            $this->assertFalse($zone->refresh()->is_virtual, $type);
        }
    }

    public function test_storage_areas_screen_saves_the_area_rules(): void
    {
        $rows = (new StorageAreaLookup)->save([$this->row()]);

        $zone = WhZone::query()->where('warehouse_id', $this->wh->id_warehouse)->where('zone_code', 'ZZA')->firstOrFail();
        $this->assertSame('ONE_ITEM', $zone->mix_rule);
        $this->assertSame('FEFO', $zone->pick_order);
        $this->assertSame(30, $zone->count_every_days);
        $this->assertFalse($zone->is_virtual);
        $saved = collect($rows)->firstWhere('zone_code', 'ZZA');
        $this->assertSame('ONE_ITEM', $saved['mix_rule']);
        $this->assertFalse($saved['is_virtual']);
        $this->assertContains('mix_rule', array_column((new StorageAreaLookup)->columns(), 'key'));
    }

    public function test_the_area_rules_are_validated(): void
    {
        foreach ([['mix_rule' => 'EVERYTHING'], ['pick_order' => 'LIFO'], ['count_every_days' => 0], ['count_every_days' => 400]] as $bad) {
            try {
                (new StorageAreaLookup)->save([$this->row($bad)]);
                $this->fail('Saving '.json_encode($bad).' must be refused.');
            } catch (ValidationException) {
                $this->assertSame(0, WhZone::query()->where('warehouse_id', $this->wh->id_warehouse)->count());
            }
        }
        // Kosong = bawaan (Mixed items, FIFO), hitung tidak dijadwalkan.
        (new StorageAreaLookup)->save([$this->row(['mix_rule' => '', 'pick_order' => '', 'count_every_days' => null])]);
        $zone = WhZone::query()->where('warehouse_id', $this->wh->id_warehouse)->firstOrFail();
        $this->assertSame(['ANY', 'FIFO', null], [$zone->mix_rule, $zone->pick_order, $zone->count_every_days]);
    }

    public function test_a_virtual_area_is_saved_without_a_place_on_the_floor_plan_and_is_not_on_the_map(): void
    {
        // Tempat di denah (jauh di luar gedung 20 x 10 m) tidak diperiksa untuk area virtual; mode simpan dipaksa AREA.
        (new StorageAreaLookup)->save([$this->row(['zone_code' => 'ZZV', 'zone_type' => 'SUBCON', 'map_x' => 500, 'map_y' => 500, 'mix_rule' => 'ANY'])]);
        $zone = WhZone::query()->where('zone_code', 'ZZV')->where('warehouse_id', $this->wh->id_warehouse)->firstOrFail();

        $this->assertTrue($zone->is_virtual);
        $this->assertSame('AREA', $zone->storage_mode);
        $this->assertSame([], BinGenerator::plan($zone->load('warehouse'), ['rows_to' => 3, 'cols_to' => 3]));
        $this->assertSame(['created' => 0, 'skipped' => 0], BinGenerator::generate($zone, ['rows_to' => 3, 'cols_to' => 3], self::BY));
        $this->assertSame(0, WhLoc::query()->where('zone_id', $zone->id_zone)->count());

        $physical = WhZone::create(['warehouse_id' => $this->wh->id_warehouse, 'zone_code' => 'ZZP', 'zone_name' => 'Physical', 'zone_type' => 'STORAGE', 'storage_mode' => 'AREA', 'created_by' => self::BY]);
        $codes = collect(Warehouse::map($this->wh)['floors'])->pluck('zones')->flatten(1)->pluck('code')->all();
        $this->assertContains($physical->zone_code, $codes);
        $this->assertNotContains('ZZV', $codes, 'Virtual areas are not drawn on the map.');
    }

    public function test_virtual_locations_are_created_once_and_reused(): void
    {
        $service = new VirtualLocationService(self::BY);
        $suppliers = SupSupplier::withoutGlobalScopes()->orderBy('id_supplier')->limit(2)->get();
        if ($suppliers->count() < 2) {
            $this->markTestSkipped('Two suppliers are needed.');
        }

        $first = $service->ensure();
        $again = $service->ensure();
        $this->assertSame($first['warehouse']->id_warehouse, $again['warehouse']->id_warehouse);
        $this->assertSame('VIRTUAL', $first['warehouse']->warehouse_type);
        $this->assertSame($first['loss']->location_id, $again['loss']->location_id);
        $this->assertSame($first['transit']->location_id, $service->transit()->location_id);
        $this->assertNotSame($first['loss']->location_id, $first['transit']->location_id);

        $a = $service->vendor((int) $suppliers[0]->id_supplier);
        $b = $service->vendor((int) $suppliers[1]->id_supplier);
        $this->assertSame($a->location_id, $service->vendor((int) $suppliers[0]->id_supplier)->location_id);
        $this->assertNotSame($a->location_id, $b->location_id, 'One bin per supplier.');
        $this->assertSame($a->zone_id, $b->zone_id);

        $wh = $first['warehouse']->id_warehouse;
        $this->assertSame(['LOSS', 'SUBCON', 'TRANSIT'], WhZone::query()->where('warehouse_id', $wh)->orderBy('zone_type')->pluck('zone_type')->all());
        $this->assertSame(4, WhLoc::query()->where('warehouse_id', $wh)->count(), 'Loss, In Transit and two supplier bins; nothing duplicated.');
        $this->assertTrue((bool) WhZone::query()->where('warehouse_id', $wh)->where('zone_type', '!=', 'STORAGE')->get()->every(fn (WhZone $z) => $z->is_virtual));
        $this->assertSame($first['loss']->location_code, 'VIRT-LOSS');
        $this->assertStringStartsWith('VIRT-SUB-', $a->location_code);

        $map = Warehouse::map($first['warehouse']);
        $this->assertSame([], collect($map['floors'])->pluck('zones')->flatten(1)->all(), 'Nothing of the virtual building is drawn.');
    }

    public function test_an_unknown_supplier_has_no_virtual_bin(): void
    {
        $this->expectException(\RuntimeException::class);
        (new VirtualLocationService(self::BY))->vendor(999999999);
    }
}
