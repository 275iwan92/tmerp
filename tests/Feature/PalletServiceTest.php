<?php

namespace Tests\Feature;

use App\Models\Inventory\InvPallet;
use App\Models\Inventory\InvPalletLog;
use App\Models\Inventory\InvTransfer;
use App\Models\Inventory\InvTransferLine;
use App\Models\Inventory\RmStockcard;
use App\Models\Master\FlowConfig;
use App\Models\Master\FlowConfigStep;
use App\Models\Master\FlowLog;
use App\Models\Master\MatRaw;
use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Master\WhZone;
use App\Services\Inventory\PalletService;
use App\Services\Inventory\StockTransferService;
use App\Services\Inventory\WarehouseStock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

// Putaran 2 Inventory & Warehouse (2026-10-02): palet berlabel (label kedatangan, label ulang, ikut pindah saat transfer),
// kedaluwarsa, kapasitas bin dari luas x tinggi, dan Stock Transfer lewat approval (modul flow TF).
// Menulis ke database sungguhan (gedung uji sendiri, satu bahan yang belum punya kartu stok) dan MEMBERSIHKAN sendiri di
// tearDown(); cur_stock master tidak disentuh.
class PalletServiceTest extends TestCase
{
    private const BY = 'test_pallet';

    private WhWarehouse $wh;

    private WhZone $zone;

    private WhLoc $src;

    private WhLoc $dst;

    private MatRaw $material;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
        $this->wh = WhWarehouse::create(['warehouse_code' => 'ZZP-A', 'warehouse_name' => 'Test ZZP-A', 'warehouse_type' => 'MIX', 'activate' => true, 'length_m' => 20, 'width_m' => 10,
            'floor_count' => 2, 'shape' => 'RECT', 'height_f1_m' => 6, 'height_f2_m' => 3, 'created_by' => self::BY, 'updated_by' => self::BY]);
        // Area 4 x 5 m = 20 m2, dua posisi lantai -> tapak 10 m2 per bin; tinggi tumpuk 2 m -> 20 m3 per bin.
        $this->zone = WhZone::create(['warehouse_id' => $this->wh->id_warehouse, 'floor_no' => 1, 'zone_code' => 'PPR', 'zone_name' => 'Test paper', 'zone_type' => 'STORAGE',
            'storage_mode' => 'FLOOR', 'map_x' => 0, 'map_y' => 0, 'map_w' => 4, 'map_h' => 5, 'stack_height_m' => 2, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $bin = fn (string $code, string $col) => WhLoc::create(['warehouse_id' => $this->wh->id_warehouse, 'zone_id' => $this->zone->id_zone, 'location_code' => $code, 'row_code' => 'PPR-L01',
            'col_code' => $col, 'stack_code' => '1', 'is_usable' => true, 'capacity_kg' => 1000, 'activate' => true, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $this->src = $bin('ZZP-A-PPR-L01-01', '01');
        $this->dst = $bin('ZZP-A-PPR-L01-02', '02');
        $this->material = MatRaw::withoutGlobalScopes()->where('activate', true)->whereNotIn('material_id', RmStockcard::query()->whereNotNull('material_id')->distinct()->pluck('material_id'))
            ->orderBy('material_id')->firstOrFail();
        // Saldo awal uji: 100 KG datang 10 hari lalu.
        (new RmStockcard)->forceFill(['doc_date' => now()->subDays(10), 'material_id' => $this->material->material_id, 'doc_type' => 'BB', 'location_id' => $this->src->location_id,
            'warehouse_id' => $this->wh->id_warehouse, 'qty' => 100, 'uom_code' => 'KG', 'reference_name' => 'test:pallet', 'reference_detail_id' => $this->src->location_id,
            'qty_bb' => 0, 'qty_in' => 100, 'qty_out' => 0, 'qty_adj' => 0, 'qty_eb' => 100, 'avg_unitprice' => 50, 'value_eb' => 5000, 'create_by' => self::BY, 'update_by' => self::BY])->save();
        $this->key = WarehouseStock::key('RM', (int) $this->material->material_id, null, (int) $this->src->location_id);
    }

    protected function tearDown(): void
    {
        $this->clean();
        $this->assertSame(0, InvPallet::query()->where('created_by', self::BY)->count());
        $this->assertSame(0, InvTransfer::query()->where('created_by', self::BY)->count());
        $this->assertSame(0, RmStockcard::query()->where('create_by', self::BY)->count());
        $this->assertSame(0, WhWarehouse::query()->where('created_by', self::BY)->count());
        $this->assertSame(0, WhLoc::query()->where('created_by', self::BY)->count());
        $this->assertSame(0, FlowConfig::query()->where('created_by', self::BY)->count());
        parent::tearDown();
    }

    private function clean(): void
    {
        $palletIds = InvPallet::query()->where('created_by', self::BY)->pluck('id_pallet')->all();
        InvPalletLog::query()->whereIn('pallet_id', $palletIds ?: [0])->delete();
        InvPallet::query()->whereIn('id_pallet', $palletIds ?: [0])->delete();
        $transferIds = InvTransfer::query()->where('created_by', self::BY)->pluck('id_transfer')->all();
        $configIds = FlowConfig::query()->where('created_by', self::BY)->pluck('flow_config_id')->all();
        FlowLog::query()->where('module_code', StockTransferService::FLOW)->where(fn ($q) => $q->whereIn('doc_id', $transferIds ?: [0])->orWhereIn('flow_config_id', $configIds ?: [0]))->delete();
        FlowConfigStep::query()->whereIn('flow_config_id', $configIds ?: [0])->delete();
        FlowConfig::query()->whereIn('flow_config_id', $configIds ?: [0])->delete();
        RmStockcard::query()->where('create_by', self::BY)->delete();
        InvTransfer::query()->whereIn('id_transfer', $transferIds ?: [0])->delete();
        $ids = WhWarehouse::query()->where('created_by', self::BY)->pluck('id_warehouse')->all();
        WhLoc::query()->whereIn('warehouse_id', $ids ?: [0])->delete();
        WhZone::query()->whereIn('warehouse_id', $ids ?: [0])->delete();
        WhWarehouse::query()->whereIn('id_warehouse', $ids ?: [0])->delete();
    }

    private function row(?WhLoc $bin = null): ?array
    {
        $bin ??= $this->src;

        return PalletService::worklist([$bin->location_id])->firstWhere('location_id', $bin->location_id);
    }

    private function pallets(array $qtys, array $extra = []): Collection
    {
        return PalletService::create($this->key, array_map(fn ($q) => ['qty' => $q] + $extra, $qtys), ['ref_doc_no' => 'SPB-TEST'], self::BY);
    }

    private function transfer(float $qty, ?int $palletId = null): InvTransfer
    {
        $t = InvTransfer::create(['transfer_date' => now()->toDateString(), 'from_warehouse_id' => $this->wh->id_warehouse, 'to_warehouse_id' => $this->wh->id_warehouse,
            'created_by' => self::BY, 'updated_by' => self::BY]);
        InvTransferLine::create(['transfer_id' => $t->id_transfer, 'line_no' => 1, 'stock_class' => 'RM', 'item_id' => $this->material->material_id,
            'item_code' => $this->material->material_code, 'item_name' => $this->material->material_name, 'from_location_id' => $this->src->location_id,
            'to_location_id' => $this->dst->location_id, 'qty' => $qty, 'uom_code' => 'KG', 'pallet_id' => $palletId, 'created_by' => self::BY, 'updated_by' => self::BY]);

        return $t;
    }

    public function test_stock_without_a_pallet_label_asks_for_an_arrival_label(): void
    {
        $row = $this->row();
        $this->assertSame('NEW', $row['need']);
        $this->assertSame(100.0, $row['unlabelled']);
        $this->assertSame(now()->subDays(10)->toDateString(), $row['arrived']);

        $made = $this->pallets([60, 40], ['supplier_pallet_code' => 'SUP-001', 'lot_no' => 'LOT-9', 'expiry_date' => now()->addDays(200)->toDateString()]);

        $this->assertCount(2, $made);
        $this->assertMatchesRegularExpression('/^PL\d{7}$/', $made[0]->pallet_no);
        $this->assertNotSame($made[0]->pallet_no, $made[1]->pallet_no);
        $this->assertSame('SUP-001', $made[0]->supplier_pallet_code);
        $this->assertSame('SPB-TEST', $made[0]->ref_doc_no);
        $this->assertSame(now()->subDays(10)->toDateString(), $made[0]->arrived_at->toDateString(), 'Arrival date defaults to the date the stock came into the bin.');
        $this->assertNull($this->row()['need']);
        $this->assertSame(2, InvPalletLog::query()->whereIn('pallet_id', $made->pluck('id_pallet'))->where('action', 'CREATED')->count());
    }

    public function test_pallets_can_not_hold_more_than_the_unlabelled_stock(): void
    {
        $this->pallets([70]);
        try {
            $this->pallets([31]);
            $this->fail('31 on a new pallet while only 30 is unlabelled must be refused.');
        } catch (RuntimeException) {
            $this->assertSame(1, InvPallet::query()->where('created_by', self::BY)->count());
        }
        $this->pallets([30]);
        $this->assertNull($this->row()['need']);
    }

    public function test_relabel_after_use_replaces_the_quantity_and_zero_closes_the_pallet(): void
    {
        [$a, $b] = $this->pallets([60, 40])->all();

        PalletService::relabel($a, 25, self::BY, 'taken for a work order');
        $this->assertSame(25.0, $a->refresh()->qty);
        $this->assertSame(60.0, $a->qty_initial);
        $this->assertSame(2, $a->label_count);
        $this->assertSame('RELABEL', $a->logs()->first()->action);
        // Label kini 65, stok 100: 35 muncul sebagai belum berlabel (bukan urusan mesin memutuskan kenapa).
        $this->assertSame('NEW', $this->row()['need']);

        PalletService::relabel($b, 0, self::BY);
        $this->assertSame('CLOSED', $b->refresh()->status);
        $this->assertSame(1, $this->row()['pallets']->count());

        $this->expectException(RuntimeException::class);
        PalletService::relabel($a, 61, self::BY);
    }

    public function test_pallets_follow_a_transfer_and_come_back_when_it_is_cancelled(): void
    {
        [$old, $new] = $this->pallets([60, 40])->all();
        $svc = new StockTransferService;

        // 60 = persis palet tertua -> palet itu ikut pindah.
        $t = $this->transfer(60);
        $this->assertNull($svc->confirm($t, self::BY));
        $this->assertSame((int) $this->dst->location_id, (int) $old->refresh()->location_id);
        $this->assertSame((int) $this->src->location_id, (int) $new->refresh()->location_id);
        $this->assertNull($this->row($this->src)['need']);
        $this->assertNull($this->row($this->dst)['need']);
        $this->assertSame($t->refresh()->transfer_no, InvPalletLog::query()->where('pallet_id', $old->id_pallet)->where('action', 'MOVED')->value('ref_no'));

        $this->assertNull($svc->cancel($t, 'Wrong bin', self::BY));
        $this->assertSame((int) $this->src->location_id, (int) $old->refresh()->location_id);

        // Sebagian isi satu palet: palet tetap di tempat; asal minta label ulang, tujuan minta label kedatangan.
        $this->assertNull($svc->confirm($this->transfer(30), self::BY));
        $this->assertSame((int) $this->src->location_id, (int) $old->refresh()->location_id);
        $this->assertSame('RELABEL', $this->row($this->src)['need']);
        $this->assertSame(30.0, $this->row($this->src)['excess']);
        $this->assertSame('NEW', $this->row($this->dst)['need']);
    }

    public function test_a_line_made_from_one_pallet_moves_that_pallet(): void
    {
        [$old, $new] = $this->pallets([60, 40])->all();

        $this->assertNull((new StockTransferService)->confirm($this->transfer(40, $new->id_pallet), self::BY));

        $this->assertSame((int) $this->dst->location_id, (int) $new->refresh()->location_id, 'The named pallet moves, not the oldest one.');
        $this->assertSame((int) $this->src->location_id, (int) $old->refresh()->location_id);
    }

    public function test_expiry_is_tagged_on_the_pallet_and_on_its_bin(): void
    {
        $this->pallets([50], ['expiry_date' => now()->subDay()->toDateString()]);
        $soon = $this->pallets([50], ['expiry_date' => now()->addDays(5)->toDateString()])->first();
        $days = WarehouseStock::expirySoonDays();

        $this->assertSame('SOON', $soon->expiryState($days));
        $summary = WarehouseStock::binSummaries(collect([$this->src]))[$this->src->location_id];
        $this->assertSame('EXPIRED', $summary['expiry_state'], 'The worst pallet decides the tag of the bin.');
        $this->assertSame(2, $summary['pallet_count']);
        $this->assertSame(now()->subDay()->toDateString(), $summary['items'][0]['expiry']);
    }

    public function test_bin_capacity_comes_from_floor_area_and_height(): void
    {
        $g = WarehouseStock::binGeometry(collect([$this->src]))[$this->src->location_id];
        $this->assertSame(['area_m2' => 10.0, 'height_m' => 2.0, 'volume_m3' => 20.0], $g);

        // Tanpa tinggi tumpuk di area: tinggi bersih lantai gedung (lantai 1 = 6 m).
        $this->zone->update(['stack_height_m' => null]);
        $g = WarehouseStock::binGeometry(collect([$this->src]))[$this->src->location_id];
        $this->assertSame(6.0, $g['height_m']);
        $this->assertSame(60.0, $g['volume_m3']);

        // Lembaran: 1.000 lembar 300 gsm 100 x 70 cm, tebal 0,4 mm -> 210 kg dan 0,28 m3.
        $paper = new MatRaw(['gram_weight' => 300, 'cm_length' => 100, 'cm_width' => 70, 'mm_thickness' => 0.4]);
        $this->assertSame(210.0, WarehouseStock::weightKg(1000, 'LBR', $paper, 'TEST'));
        $this->assertSame(0.28, WarehouseStock::volumeM3(1000, 'LBR', $paper, 'TEST'));
        $this->assertNull(WarehouseStock::volumeM3(100, 'KG', null, 'INK'));
    }

    public function test_with_approval_switched_on_the_stock_moves_only_after_the_last_approval(): void
    {
        if (! Schema::connection('tm2mst')->hasColumn('flow_config', 'final_status')) {
            $this->markTestSkipped('Setting Approval (flow_config.final_status) is not installed on this database yet.');
        }
        if (FlowConfig::query()->where('module_code', StockTransferService::FLOW)->exists()) {
            $this->markTestSkipped('This database has its own Stock Transfer approval setting; the test does not touch it.');
        }
        $position = DB::connection('tm2mst')->table('org_position')->where('activate', 1)->value('position_id');
        $config = FlowConfig::create(['module_code' => StockTransferService::FLOW, 'total_step' => 1, 'final_status' => 'CONFIRMED', 'reject_status' => 'REJECTED', 'activate' => true,
            'created_by' => self::BY, 'updated_by' => self::BY]);
        FlowConfigStep::create(['flow_config_id' => $config->flow_config_id, 'step_no' => 1, 'step_type' => 'APPROVAL', 'status_from' => 'SUBMITTED', 'status_to' => 'SUBMITTED',
            'status_to_reject' => 'REJECTED', 'position_id' => $position, 'min_amount' => 0, 'activate' => true, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $svc = new StockTransferService;
        $balance = fn (WhLoc $bin) => $svc->balance('RM', (int) $this->material->material_id, null, (int) $bin->location_id);

        $t = $this->transfer(40);
        $this->assertNull($svc->confirm($t, self::BY));
        $this->assertSame('SUBMITTED', $t->refresh()->status());
        $this->assertNotNull($t->transfer_no);
        $this->assertSame(100.0, $balance($this->src), 'Waiting for approval: nothing moves.');
        $this->assertSame(2000.0, (float) FlowLog::query()->where('module_code', 'TF')->where('doc_id', $t->id_transfer)->where('action', 'SUBMITTED')->value('amount_snapshot'));

        $this->assertNotNull($svc->post($t, self::BY), 'A transfer that waits for approval can not be posted as a Draft.');
        $this->assertNull($svc->post($t, self::BY, true));
        $this->assertSame('CONFIRMED', $t->refresh()->status());
        $this->assertSame('APPROVED', $t->flow_status);
        $this->assertSame(60.0, $balance($this->src));

        // Dibatalkan selagi menunggu approval: tanpa gerakan stok.
        $w = $this->transfer(10);
        $this->assertNull($svc->confirm($w, self::BY));
        $this->assertNull($svc->cancel($w, 'Not needed', self::BY));
        $this->assertSame('CANCELLED', $w->refresh()->status());
        $this->assertSame(60.0, $balance($this->src));
    }
}
