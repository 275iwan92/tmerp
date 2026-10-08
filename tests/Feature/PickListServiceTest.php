<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\InvPallet;
use App\Models\Inventory\InvPalletLog;
use App\Models\Inventory\RmBooking;
use App\Models\Inventory\RmBookingD;
use App\Models\Inventory\RmStockcard;
use App\Models\Inventory\WhPickList;
use App\Models\Master\MatRaw;
use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Master\WhZone;
use App\Models\Sys\SecUser;
use App\Services\Inventory\BatchService;
use App\Services\Inventory\PalletService;
use App\Services\Inventory\PickAllocator;
use App\Services\Inventory\PickListRefused;
use App\Services\Inventory\PickListService;
use App\Services\Inventory\PickRequirement;
use App\Services\Inventory\WarehouseStock;
use App\Support\V2\Data\PickLists;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Pick List (W-05, B-12): alokasi palet / batch / bin menurut aturan area, batch yang boleh, syarat spesifikasi, pemilik stok,
// kekurangan SHORT, scan palet lalu bin, dan Confirm Pick = Stock Out RM ke WO.
// Menulis ke database sungguhan (gedung uji sendiri + dua bahan yang belum punya kartu stok) dan MEMBERSIHKAN sendiri di tearDown(),
// termasuk mengembalikan cur_stock master bahan yang disentuh posting Stock Out.
class PickListServiceTest extends TestCase
{
    private const BY = 'test_picklist';

    private const PRODUCT = 999999001;

    private WhWarehouse $wh;

    private WhLoc $paperBin;

    private WhLoc $paperBin2;

    private WhLoc $qcBin;

    private WhLoc $inkBin;

    private WhLoc $stagingBin;

    private MatRaw $paper;

    private MatRaw $ink;

    /** @var array<int, mixed> cur_stock asli bahan uji (id => nilai) */
    private array $curStock = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
        $this->wh = WhWarehouse::create(['warehouse_code' => 'ZZK-A', 'warehouse_name' => 'Test ZZK-A', 'warehouse_type' => 'MIX', 'activate' => true, 'length_m' => 20, 'width_m' => 10,
            'floor_count' => 1, 'shape' => 'RECT', 'height_f1_m' => 6, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $zone = fn (string $code, string $type, string $order) => WhZone::create(['warehouse_id' => $this->wh->id_warehouse, 'floor_no' => 1, 'zone_code' => $code, 'zone_name' => 'Test '.$code,
            'zone_type' => $type, 'storage_mode' => 'FLOOR', 'map_x' => 0, 'map_y' => 0, 'map_w' => 4, 'map_h' => 5, 'stack_height_m' => 2, 'pick_order' => $order, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $bin = fn (WhZone $z, string $code, string $col) => WhLoc::create(['warehouse_id' => $this->wh->id_warehouse, 'zone_id' => $z->id_zone, 'location_code' => $code, 'row_code' => $z->zone_code,
            'col_code' => $col, 'stack_code' => '1', 'is_usable' => true, 'capacity_kg' => 100000, 'activate' => true, 'created_by' => self::BY, 'updated_by' => self::BY]);
        [$ppr, $ink, $qc, $stg] = [$zone('PPR', 'STORAGE', 'FIFO'), $zone('INK', 'STORAGE', 'FEFO'), $zone('QCH', 'QC_HOLD', 'FIFO'), $zone('STG', 'STAGING', 'FIFO')];
        $this->paperBin = $bin($ppr, 'ZZK-PPR-01', '01');
        $this->paperBin2 = $bin($ppr, 'ZZK-PPR-02', '02');
        $this->qcBin = $bin($qc, 'ZZK-QCH-01', '03');
        $this->inkBin = $bin($ink, 'ZZK-INK-01', '04');
        $this->stagingBin = $bin($stg, 'ZZK-STG-01', '05');

        $free = MatRaw::withoutGlobalScopes()->where('activate', true)->whereNotIn('material_id', RmStockcard::query()->whereNotNull('material_id')->distinct()->pluck('material_id'))->orderBy('material_id')->limit(2)->get();
        [$this->paper, $this->ink] = [$free[0], $free[1]];
        foreach ($free as $m) {
            $this->curStock[$m->material_id] = DB::connection('tm2mst')->table('mat_raw')->where('material_id', $m->material_id)->value('cur_stock');
        }
    }

    protected function tearDown(): void
    {
        $this->clean();
        foreach ([InvPallet::class => 'created_by', InvBatch::class => 'created_by', WhPickList::class => 'created_by', RmBooking::class => 'create_by', RmStockcard::class => 'create_by',
            WhWarehouse::class => 'created_by', WhLoc::class => 'created_by', WhZone::class => 'created_by'] as $model => $column) {
            $this->assertSame(0, $model::query()->where($column, self::BY)->count(), $model.' masih berisi baris uji.');
        }
        $this->assertSame(0, DB::connection('tm2inv')->table('rm_stock')->where('create_by', self::BY)->count());
        $this->assertSame(0, DB::connection('tm2inv')->table('rm_stock_d')->where('create_by', self::BY)->count());
        $this->assertSame(0, DB::connection('tm2prd')->table('prd_wo')->where('created_by', self::BY)->count());
        $this->assertSame(0, DB::connection('tm2acc')->table('fin_voucher')->where('created_by', self::BY)->count());
        parent::tearDown();
    }

    private function clean(): void
    {
        // Posting Stock Out menggeser cur_stock master bahan (COALESCE dari NULL): kembalikan nilai aslinya.
        foreach ($this->curStock as $id => $value) {
            DB::connection('tm2mst')->table('mat_raw')->where('material_id', $id)->update(['cur_stock' => $value]);
        }
        $inv = DB::connection('tm2inv');
        $listIds = WhPickList::query()->where('created_by', self::BY)->pluck('id_pick_list')->all();
        $inv->table('wh_pick_list_line')->whereIn('pick_list_id', $listIds ?: [0])->delete();
        WhPickList::query()->whereIn('id_pick_list', $listIds ?: [0])->delete();
        $palletIds = InvPallet::query()->where('created_by', self::BY)->pluck('id_pallet')->all();
        InvPalletLog::query()->whereIn('pallet_id', $palletIds ?: [0])->delete();
        InvPallet::query()->whereIn('id_pallet', $palletIds ?: [0])->delete();
        $bookingIds = RmBooking::query()->where('create_by', self::BY)->pluck('booking_id')->all();
        RmBookingD::query()->whereIn('booking_id', $bookingIds ?: [0])->delete();
        RmBooking::query()->whereIn('booking_id', $bookingIds ?: [0])->delete();
        $voucherIds = DB::connection('tm2acc')->table('fin_voucher')->where('created_by', self::BY)->pluck('voucher_id')->all();
        DB::connection('tm2acc')->table('fin_voucher_line')->whereIn('voucher_id', $voucherIds ?: [0])->delete();
        DB::connection('tm2acc')->table('fin_voucher')->whereIn('voucher_id', $voucherIds ?: [0])->delete();
        $docIds = $inv->table('rm_stock')->where('create_by', self::BY)->pluck('doc_id')->all();
        $inv->table('rm_stock_d')->whereIn('doc_id', $docIds ?: [0])->delete();
        RmStockcard::query()->where('create_by', self::BY)->delete();
        $inv->table('rm_stock')->whereIn('doc_id', $docIds ?: [0])->delete();
        InvBatch::query()->where('created_by', self::BY)->delete();
        DB::connection('tm2prd')->table('prd_wo')->where('created_by', self::BY)->delete();
        $ids = WhWarehouse::query()->where('created_by', self::BY)->pluck('id_warehouse')->all();
        WhLoc::query()->whereIn('warehouse_id', $ids ?: [0])->delete();
        WhZone::query()->whereIn('warehouse_id', $ids ?: [0])->delete();
        WhWarehouse::query()->whereIn('id_warehouse', $ids ?: [0])->delete();
    }

    private function batch(string $status, array $attrs = []): InvBatch
    {
        return BatchService::create('RM', 1, null, ['qc_status' => $status] + $attrs, self::BY);
    }

    /** Palet berstok di satu bin: saldo kartu stok + palet berlabel (batch + tanggal datang + kedaluwarsa). */
    private function stock(MatRaw $m, WhLoc $bin, float $qty, ?InvBatch $batch, int $arrivedDaysAgo, ?string $expiry = null, string $uom = 'SHEET'): InvPallet
    {
        $before = (float) RmStockcard::query()->where('create_by', self::BY)->where('material_id', $m->material_id)->where('location_id', $bin->location_id)->sum('qty_in');
        (new RmStockcard)->forceFill(['doc_date' => now()->subDays($arrivedDaysAgo), 'material_id' => $m->material_id, 'doc_type' => 'BB', 'location_id' => $bin->location_id,
            'warehouse_id' => $this->wh->id_warehouse, 'qty' => $qty, 'uom_code' => $uom, 'reference_name' => 'test:picklist', 'reference_detail_id' => $bin->location_id * 1000 + random_int(1, 999),
            'qty_bb' => $before, 'qty_in' => $qty, 'qty_out' => 0, 'qty_adj' => 0, 'qty_eb' => $before + $qty, 'avg_unitprice' => 10, 'value_eb' => ($before + $qty) * 10, 'batch_id' => $batch?->id_batch,
            'create_by' => self::BY, 'update_by' => self::BY])->save();
        $pallet = PalletService::create(WarehouseStock::key('RM', (int) $m->material_id, null, (int) $bin->location_id), [['qty' => $qty, 'expiry_date' => $expiry]],
            ['arrived_at' => now()->subDays($arrivedDaysAgo)->toDateString()], self::BY)->first();
        $pallet->forceFill(['batch_id' => $batch?->id_batch])->save();

        return $pallet->refresh();
    }

    /** WO + booking Confirmed dengan baris-baris [material, role, qty, spec]. */
    private function workOrder(string $no, array $needs): array
    {
        $woId = DB::connection('tm2prd')->table('prd_wo')->insertGetId(['wo_no' => $no, 'wo_type' => 'MTO', 'source_type' => 'CO_CONFIRMED', 'product_id' => self::PRODUCT, 'qty' => 100,
            'due_date' => now()->addDays(5), 'created_by' => self::BY]);
        $b = RmBooking::create(['booking_no' => 'ZZ'.$no, 'booking_date' => now(), 'booking_type' => 'CO', 'wo_id' => $woId, 'doc_status' => 'CONFIRMED', 'stat_confirm' => 1,
            'stat_cancel' => 0, 'stat_closed' => 0, 'create_by' => self::BY]);
        foreach ($needs as $i => [$m, $role, $qty, $spec]) {
            RmBookingD::create(['booking_id' => $b->booking_id, 'seq_no' => $i + 1, 'product_id' => self::PRODUCT, 'material_id' => $m->material_id, 'material_role' => $role,
                'uom_code' => $role === 'INK' ? 'KG' : 'SHEET', 'qty_need' => $qty, 'need_date' => now()->addDays(3)->toDateString(), 'line_status' => 'STOCK', 'spec_text' => $spec,
                'qty_book' => 0, 'qty_purchase' => 0, 'qty_short' => 0, 'qty_replacement' => 0, 'qty_issued' => 0, 'stat_cancel' => 0, 'create_by' => self::BY]);
        }

        return [$woId, $b];
    }

    public function test_paper_is_allocated_fifo_and_only_from_allowed_batches(): void
    {
        $released = $this->batch('RELEASED', ['grain_direction' => 'LONG', 'certification' => 'FSC']);
        $old = $this->stock($this->paper, $this->paperBin, 1250, $released, 20);
        $new = $this->stock($this->paper, $this->paperBin2, 2000, $released, 10);
        $this->stock($this->paper, $this->qcBin, 5000, $this->batch('QUARANTINE'), 30);
        $this->stock($this->paper, $this->paperBin, 800, $this->batch('REJECTED'), 5);
        [$woId] = $this->workOrder('ZZWO1', [[$this->paper, 'SUBSTRATE', 3000, null]]);

        $list = PickListService::create($woId, self::BY);

        $this->assertMatchesRegularExpression('/^PK\d{7}$/', $list->pick_no);
        $this->assertSame('DRAFT', $list->status);
        $this->assertSame($this->stagingBin->location_id, (int) $list->deliver_to_location_id, 'Staging bin is chosen by default.');
        $lines = $list->lines;
        $this->assertCount(2, $lines, 'Quarantine, Rejected and QC hold zone stock is never allocated.');
        $this->assertSame([$old->id_pallet, $new->id_pallet], $lines->pluck('pallet_id')->all(), 'FIFO: the oldest received pallet first.');
        $this->assertSame([1250.0, 1750.0], $lines->pluck('qty_allocated')->all());
        $this->assertSame(3000.0, $lines->first()->qty_need);
        $this->assertSame(['ALLOCATED', 'ALLOCATED'], $lines->pluck('status')->all());
    }

    public function test_ink_uses_fefo_and_conditional_batches_follow_the_class_rule(): void
    {
        $late = $this->stock($this->ink, $this->inkBin, 20, $this->batch('RELEASED'), 40, now()->addDays(90)->toDateString(), 'KG');
        $soon = $this->stock($this->ink, $this->inkBin, 20, $this->batch('CONDITIONAL'), 30, now()->addDays(10)->toDateString(), 'KG');
        $food = $this->stock($this->ink, $this->inkBin, 20, $this->batch('CONDITIONAL', ['food_grade' => true]), 20, now()->addDays(2)->toDateString(), 'KG');
        [$woId] = $this->workOrder('ZZWO2', [[$this->ink, 'INK', 18, null]]);

        $list = PickListService::create($woId, self::BY);

        $this->assertSame([$soon->id_pallet], $list->lines->pluck('pallet_id')->all(), 'FEFO picks the earliest expiry that is allowed; a food grade Conditional batch (class A) is skipped.');
        $this->assertStringContainsString('Conditional batch allowed', (string) $list->lines->first()->note);
        $this->assertNotContains($food->id_pallet, $list->lines->pluck('pallet_id')->all());
        $this->assertNotContains($late->id_pallet, $list->lines->pluck('pallet_id')->all());
    }

    public function test_spec_requirements_grain_and_certification_must_match_the_batch(): void
    {
        $this->assertSame('LONG', PickRequirement::fromSpec('Art Paper 310 g long grain FSC')->grain);
        $this->assertSame('FSC', PickRequirement::fromSpec('Art Paper 310 g long grain FSC')->certification);
        $this->assertTrue(PickRequirement::fromSpec('Art Paper 310 g')->isEmpty());

        $this->stock($this->paper, $this->paperBin, 1000, $this->batch('RELEASED', ['grain_direction' => 'SHORT', 'certification' => 'NONE']), 5);
        $match = $this->stock($this->paper, $this->paperBin2, 1000, $this->batch('RELEASED', ['grain_direction' => 'LONG', 'certification' => 'FSC']), 8);
        [$woId] = $this->workOrder('ZZWO3', [[$this->paper, 'SUBSTRATE', 1500, 'Art Paper 310 g long grain FSC']]);

        $list = PickListService::create($woId, self::BY);

        $this->assertSame([$match->id_pallet], $list->lines->where('status', 'ALLOCATED')->pluck('pallet_id')->all());
        $short = $list->lines->firstWhere('status', 'SHORT');
        $this->assertNotNull($short, 'The part of the need that no allowed batch can cover is SHORT.');
        $this->assertSame('LONG grain · FSC', $short->requirement);
    }

    public function test_shortage_is_marked_short_and_confirm_is_refused(): void
    {
        $this->stock($this->paper, $this->paperBin, 400, $this->batch('RELEASED'), 5);
        [$woId] = $this->workOrder('ZZWO4', [[$this->paper, 'SUBSTRATE', 1000, null]]);

        $list = PickListService::create($woId, self::BY);

        $this->assertSame(['ALLOCATED', 'SHORT'], $list->lines->pluck('status')->all());
        $this->assertSame(0.0, $list->lines->last()->qty_allocated);
        $this->expectException(PickListRefused::class);
        PickListService::confirm($list, self::BY);
    }

    public function test_stock_owned_by_another_party_is_not_allocated_unless_it_is_the_customer_of_the_order(): void
    {
        $this->stock($this->paper, $this->paperBin, 500, $this->batch('RELEASED'), 5);
        DB::connection('tm2inv')->table('rm_stockcard')->where('create_by', self::BY)->update(['owner_type' => 'CUSTOMER', 'owner_id' => 4242]);

        $this->assertCount(0, (new PickAllocator(99))->candidates((int) $this->paper->material_id, new PickRequirement));
        $this->assertCount(0, (new PickAllocator)->candidates((int) $this->paper->material_id, new PickRequirement));
        $this->assertCount(1, (new PickAllocator(4242))->candidates((int) $this->paper->material_id, new PickRequirement), 'Consigned stock of the customer of the order is allowed.');
    }

    public function test_pallets_held_by_an_open_pick_list_are_not_allocated_again(): void
    {
        $this->stock($this->paper, $this->paperBin, 1000, $this->batch('RELEASED'), 5);
        [$wo1] = $this->workOrder('ZZWO5', [[$this->paper, 'SUBSTRATE', 700, null]]);
        [$wo2] = $this->workOrder('ZZWO6', [[$this->paper, 'SUBSTRATE', 700, null]]);

        $first = PickListService::create($wo1, self::BY);
        $second = PickListService::create($wo2, self::BY);

        $this->assertSame(['ALLOCATED'], $first->lines->pluck('status')->all());
        $this->assertSame(['ALLOCATED', 'SHORT'], $second->lines->pluck('status')->all());
        $this->assertSame(300.0, $second->lines->first()->qty_allocated, 'Only what the first list left on the pallet.');

        PickListService::cancel($first, 'Test cancel', self::BY);
        $again = PickListService::reallocate($second, self::BY);
        $this->assertSame(['ALLOCATED'], $again->lines->pluck('status')->all(), 'After the first list is cancelled the second one can take the pallet.');
        $this->assertSame(700.0, $again->lines->first()->qty_allocated);
    }

    public function test_second_pick_list_for_the_same_work_order_is_refused_while_the_first_is_open(): void
    {
        $this->stock($this->paper, $this->paperBin, 1000, $this->batch('RELEASED'), 5);
        [$woId] = $this->workOrder('ZZWO7', [[$this->paper, 'SUBSTRATE', 100, null]]);
        PickListService::create($woId, self::BY);

        $this->expectException(PickListRefused::class);
        PickListService::create($woId, self::BY);
    }

    public function test_work_order_without_confirmed_booking_is_refused(): void
    {
        $woId = DB::connection('tm2prd')->table('prd_wo')->insertGetId(['wo_no' => 'ZZWO8', 'wo_type' => 'MTO', 'source_type' => 'CO_CONFIRMED', 'product_id' => self::PRODUCT, 'qty' => 1,
            'due_date' => now()->addDay(), 'created_by' => self::BY]);

        $this->expectException(PickListRefused::class);
        PickListService::create($woId, self::BY);
    }

    public function test_scan_pallet_then_bin_then_confirm_pick_issues_the_stock_to_the_work_order(): void
    {
        $batch = $this->batch('RELEASED');
        $pallet = $this->stock($this->paper, $this->paperBin, 1250, $batch, 10);
        [$woId, $booking] = $this->workOrder('ZZWO9', [[$this->paper, 'SUBSTRATE', 1000, null]]);
        $list = PickListService::create($woId, self::BY);
        $line = $list->lines->first();

        try {
            PickListService::scanBin($line, $this->paperBin->location_code, self::BY);
            $this->fail('Bin before pallet must be refused.');
        } catch (PickListRefused) {
        }
        try {
            PickListService::scanPallet($line, 'PL0000000', self::BY);
            $this->fail('Wrong pallet must be refused.');
        } catch (PickListRefused $e) {
            $this->assertStringContainsString($this->paperBin->location_code, $e->getMessage());
        }
        try {
            PickListService::confirm($list, self::BY);
            $this->fail('Confirm before scanning must be refused.');
        } catch (PickListRefused) {
        }

        PickListService::scanPallet($line, 'PLT:'.strtolower($pallet->pallet_no), self::BY);
        $this->assertSame('IN_PROGRESS', $list->refresh()->status);
        try {
            PickListService::scanBin($line->refresh(), 'WRONG-BIN', self::BY);
            $this->fail('Wrong bin must be refused.');
        } catch (PickListRefused) {
        }
        PickListService::scanBin($line->refresh(), 'http://tmerp.test/track?code=LOC:'.$this->paperBin->location_code, self::BY);
        $this->assertSame('PICKED', $line->refresh()->status);
        $this->assertSame(1000.0, $line->qty_picked);

        $result = PickListService::confirm($list, self::BY);

        $this->assertCount(1, $result['docs']);
        $list->refresh();
        $this->assertSame('DONE', $list->status);
        $this->assertSame($result['docs'][0], $list->stock_out_doc_no);
        $doc = DB::connection('tm2inv')->table('rm_stock')->where('doc_id', $list->stock_out_doc_id)->first();
        $this->assertSame('POSTED', $doc->flow_status);
        $this->assertSame($woId, (int) $doc->wo_id);
        $this->assertSame(-1, (int) $doc->doc_type);
        $this->assertEquals(1000, DB::connection('tm2inv')->table('rm_stockcard')->where('material_id', $this->paper->material_id)->where('location_id', $this->paperBin->location_id)->where('doc_type', 'OUT')->sum('qty_out'));
        $this->assertEquals(250, $pallet->refresh()->qty, 'The pallet keeps what was not picked.');
        $this->assertEquals(1000, RmBookingD::query()->where('booking_id', $booking->booking_id)->value('qty_issued'));
        $this->assertSame(1, DB::connection('tm2inv')->table('rm_stock_d')->where('doc_id', $list->stock_out_doc_id)->where('batch_id', $batch->id_batch)->where('owner_type', 'COMPANY')->count());

        $this->expectException(PickListRefused::class);
        PickListService::confirm($list, self::BY);
    }

    public function test_cancel_needs_a_reason_and_releases_every_line(): void
    {
        $this->stock($this->paper, $this->paperBin, 500, $this->batch('RELEASED'), 5);
        [$woId] = $this->workOrder('ZZWO10', [[$this->paper, 'SUBSTRATE', 100, null]]);
        $list = PickListService::create($woId, self::BY);

        try {
            PickListService::cancel($list, '  ', self::BY);
            $this->fail('A reason is required.');
        } catch (PickListRefused) {
        }
        PickListService::cancel($list, 'Job cancelled', self::BY);

        $this->assertSame('CANCELLED', $list->refresh()->status);
        $this->assertSame(['CANCELLED'], $list->lines->pluck('status')->all());
        $this->assertSame('Job cancelled', $list->cancel_reason);
    }

    public function test_scan_box_takes_the_pallet_then_the_bin_and_refuses_an_unexpected_code(): void
    {
        $pallet = $this->stock($this->paper, $this->paperBin, 500, $this->batch('RELEASED'), 5);
        [$woId] = $this->workOrder('ZZWO11', [[$this->paper, 'SUBSTRATE', 200, null]]);
        $list = PickListService::create($woId, self::BY);

        try {
            PickListService::scan($list, $this->paperBin->location_code, self::BY);
            $this->fail('The bin is not expected before the pallet.');
        } catch (PickListRefused) {
        }
        $this->assertSame('pallet', PickListService::scan($list, $pallet->pallet_no, self::BY)['kind']);
        $this->assertSame('bin', PickListService::scan($list->refresh(), $this->paperBin->location_code, self::BY)['kind']);
        $this->assertSame('PICKED', $list->lines()->first()->status);
        try {
            PickListService::scan($list, $pallet->pallet_no, self::BY);
            $this->fail('A picked line is not scanned again.');
        } catch (PickListRefused) {
        }
    }

    public function test_screens_routes_and_print_work_for_a_pick_list(): void
    {
        $this->actingAs(SecUser::query()->where('is_admin', 1)->firstOrFail());
        $pallet = $this->stock($this->paper, $this->paperBin, 500, $this->batch('RELEASED', ['grain_direction' => 'LONG']), 5);
        [$woId] = $this->workOrder('ZZWO12', [[$this->paper, 'SUBSTRATE', 600, null]]);

        $this->get(route('v2.screen', 'wh-pick-list'))->assertOk();
        $this->get(route('v2.screen', 'wh-pick-list').'?new=1')->assertOk()->assertSee('Work Order');

        $this->post(route('v2.wh.pick.create'), ['wo_id' => $woId])->assertRedirect();
        $list = WhPickList::query()->where('wo_id', $woId)->firstOrFail();
        $url = route('v2.screen', 'wh-pick-list').'?id='.$list->id_pick_list;
        $this->get($url)->assertOk()->assertSee($list->pick_no)->assertSee($pallet->pallet_no)->assertSee('Allocation That Could Not Be Covered');
        $this->get(route('v2.print', ['type' => 'pick-list', 'id' => $list->id_pick_list]))->assertOk()->assertSee($list->pick_no);

        $this->post(route('v2.wh.pick.scan', $list->id_pick_list), ['code' => 'NOT-A-CODE'])->assertRedirect($url)->assertSessionHasErrors('scan');
        $this->post(route('v2.wh.pick.scan', $list->id_pick_list), ['code' => $pallet->pallet_no])->assertRedirect($url)->assertSessionHas('v2_flash');
        $this->post(route('v2.wh.pick.act', $list->id_pick_list), ['action' => 'confirm'])->assertRedirect($url)->assertSessionHasErrors('pick');
        $this->post(route('v2.wh.pick.act', $list->id_pick_list), ['action' => 'cancel', '_reason' => 'Test screen cancel'])->assertRedirect($url)->assertSessionHas('v2_flash');
        $this->assertSame('CANCELLED', $list->refresh()->status);
        $this->get($url)->assertOk()->assertSee('Test screen cancel');
    }

    public function test_map_layer_lists_the_allocated_bins_in_pick_order(): void
    {
        $this->stock($this->paper, $this->paperBin, 300, $this->batch('RELEASED'), 20);
        $this->stock($this->paper, $this->paperBin2, 300, $this->batch('RELEASED'), 10);
        [$woId] = $this->workOrder('ZZWO13', [[$this->paper, 'SUBSTRATE', 500, null]]);
        $list = PickListService::create($woId, self::BY);

        $layer = PickLists::mapLayer($list->id_pick_list);

        $this->assertSame($list->pick_no, $layer['pick']);
        $this->assertSame([$this->paperBin->location_code, $this->paperBin2->location_code], array_column($layer['bins'], 'code'));
        $this->assertSame([1, 2], array_column($layer['bins'], 'order'));
        $this->assertSame([300.0, 200.0], array_column($layer['bins'], 'qty'));
        $this->assertSame($this->stagingBin->location_code, $layer['deliver_to']['code']);
        $this->assertNull(PickLists::mapLayer(0)['pick']);
    }
}
