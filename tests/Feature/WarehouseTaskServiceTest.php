<?php

namespace Tests\Feature;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\InvPallet;
use App\Models\Inventory\InvPalletLog;
use App\Models\Inventory\InvTransfer;
use App\Models\Inventory\InvTransferLine;
use App\Models\Inventory\RmStockcard;
use App\Models\Inventory\WhTask;
use App\Models\Master\MatRaw;
use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Master\WhZone;
use App\Models\Sys\SecUser;
use App\Services\Inventory\BatchService;
use App\Services\Inventory\BinRules;
use App\Services\Inventory\PalletService;
use App\Services\Inventory\PutawayPlanner;
use App\Services\Inventory\StockTransferService;
use App\Services\Inventory\WarehouseStock;
use App\Services\Inventory\WarehouseTaskRefused;
use App\Services\Inventory\WarehouseTaskService;
use App\Support\V2\Data\ReceivingPutaway;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Receiving & Putaway v2.01: tugas putaway dari palet di area Receiving, usulan bin (kapasitas, Mixing, batch), konfirmasi dengan pindai
// yang memindahkan stok lewat dokumen Stock Transfer asli, alasan wajib bila bin berbeda, dan dock-to-line untuk batch Conditional.
// Menulis ke database sungguhan (gedung uji ZZW-A, dua bahan yang belum punya kartu stok) dan MEMBERSIHKAN sendiri di tearDown().
// Gedung uji dipakai karena usulan mengutamakan gedung yang sama dengan palet, jadi hasilnya tidak bergantung pada data dummy lain.
class WarehouseTaskServiceTest extends TestCase
{
    private const BY = 'test_whtask';

    private WhWarehouse $wh;

    private WhLoc $rcv;

    private WhLoc $binA;

    private WhLoc $binB;

    private WhLoc $small;

    private WhLoc $oneItem;

    private WhLoc $wip;

    private MatRaw $matA;

    private MatRaw $matB;

    private WarehouseTaskService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
        $this->service = new WarehouseTaskService;
        $this->wh = WhWarehouse::create(['warehouse_code' => 'ZZW-A', 'warehouse_name' => 'Test ZZW-A', 'warehouse_type' => 'MIX', 'activate' => true, 'length_m' => 30, 'width_m' => 20,
            'floor_count' => 1, 'shape' => 'RECT', 'created_by' => self::BY, 'updated_by' => self::BY]);
        $zone = fn (string $code, string $type, array $extra = []) => WhZone::create(array_replace(['warehouse_id' => $this->wh->id_warehouse, 'floor_no' => 1, 'zone_code' => $code, 'zone_name' => 'Test '.$code,
            'zone_type' => $type, 'storage_mode' => 'AREA', 'stock_class' => 'RM', 'map_x' => 0, 'map_y' => 0, 'map_w' => 5, 'map_h' => 5, 'created_by' => self::BY, 'updated_by' => self::BY], $extra));
        $bin = fn (WhZone $z, string $code, array $extra = []) => WhLoc::create(array_replace(['warehouse_id' => $this->wh->id_warehouse, 'zone_id' => $z->id_zone, 'location_code' => 'ZZW-A-'.$code,
            'row_code' => $code, 'col_code' => '01', 'stack_code' => '1', 'is_usable' => true, 'capacity_kg' => 1000, 'activate' => true, 'created_by' => self::BY, 'updated_by' => self::BY], $extra));
        $this->rcv = $bin($zone('RCV', 'RECEIVING', ['sort_order' => 1]), 'RCV');
        $std = $zone('STD', 'STORAGE', ['sort_order' => 2]);
        $this->binA = $bin($std, 'A01');
        $this->binB = $bin($std, 'A02');
        $this->small = $bin($zone('SML', 'STORAGE', ['sort_order' => 3]), 'S01', ['capacity_kg' => 150]);
        $this->oneItem = $bin($zone('ONE', 'STORAGE', ['sort_order' => 4, 'mix_rule' => 'ONE_ITEM', 'pick_order' => 'FEFO']), 'O01');
        $this->wip = $bin($zone('WIP', 'WIP', ['sort_order' => 5, 'stock_class' => 'ANY']), 'WIP');

        [$this->matA, $this->matB] = MatRaw::withoutGlobalScopes()->where('activate', true)->whereNotIn('material_id', RmStockcard::query()->whereNotNull('material_id')->distinct()->pluck('material_id'))
            ->orderBy('material_id')->limit(2)->get()->all();
    }

    protected function tearDown(): void
    {
        $this->clean();
        $this->assertSame(0, WhTask::query()->where('created_by', self::BY)->count());
        $this->assertSame(0, InvPallet::query()->where('created_by', self::BY)->count());
        $this->assertSame(0, InvTransfer::query()->where('created_by', self::BY)->count());
        $this->assertSame(0, RmStockcard::query()->where('create_by', self::BY)->count());
        $this->assertSame(0, InvBatch::query()->where('created_by', self::BY)->count());
        $this->assertSame(0, WhLoc::query()->where('created_by', self::BY)->count());
        $this->assertSame(0, WhZone::query()->where('created_by', self::BY)->count());
        $this->assertSame(0, WhWarehouse::query()->where('created_by', self::BY)->count());
        parent::tearDown();
    }

    private function clean(): void
    {
        $palletIds = InvPallet::query()->where('created_by', self::BY)->pluck('id_pallet')->all();
        WhTask::query()->where('created_by', self::BY)->delete();
        WhTask::query()->whereIn('pallet_id', $palletIds ?: [0])->delete();
        InvPalletLog::query()->whereIn('pallet_id', $palletIds ?: [0])->delete();
        InvPallet::query()->whereIn('id_pallet', $palletIds ?: [0])->delete();
        $transferIds = InvTransfer::query()->where('created_by', self::BY)->pluck('id_transfer')->all();
        InvTransferLine::query()->whereIn('transfer_id', $transferIds ?: [0])->delete();
        RmStockcard::query()->where('create_by', self::BY)->delete();
        InvTransfer::query()->whereIn('id_transfer', $transferIds ?: [0])->delete();
        InvBatch::query()->where('created_by', self::BY)->delete();
        $ids = WhWarehouse::query()->where('created_by', self::BY)->pluck('id_warehouse')->all();
        WhLoc::query()->whereIn('warehouse_id', $ids ?: [0])->delete();
        WhZone::query()->whereIn('warehouse_id', $ids ?: [0])->delete();
        WhWarehouse::query()->whereIn('id_warehouse', $ids ?: [0])->delete();
    }

    // Saldo awal bahan di satu bin (kartu stok) + satu palet berlabel di atasnya.
    private function pallet(MatRaw $m, float $qty, ?WhLoc $at = null, ?string $batchStatus = 'RELEASED'): InvPallet
    {
        $at ??= $this->rcv;
        (new RmStockcard)->forceFill(['doc_date' => now()->subDay(), 'material_id' => $m->material_id, 'doc_type' => 'BB', 'location_id' => $at->location_id, 'warehouse_id' => $this->wh->id_warehouse,
            'qty' => $qty, 'uom_code' => 'KG', 'reference_name' => 'test:whtask:'.$m->material_id.':'.$at->location_id, 'reference_detail_id' => $at->location_id, 'qty_bb' => 0, 'qty_in' => $qty, 'qty_out' => 0, 'qty_adj' => 0,
            'qty_eb' => $qty, 'avg_unitprice' => 10, 'value_eb' => $qty * 10, 'create_by' => self::BY, 'update_by' => self::BY])->save();
        $p = PalletService::create(WarehouseStock::key('RM', (int) $m->material_id, null, (int) $at->location_id), [['qty' => $qty]], ['ref_doc_no' => 'SPB-TEST'], self::BY)->first();
        if ($batchStatus) {
            $batch = BatchService::create('RM', (int) $m->material_id, 'LOT-'.$p->id_pallet, ['item_code' => $m->material_code, 'item_name' => $m->material_name, 'qc_status' => $batchStatus], self::BY);
            $p->forceFill(['batch_id' => $batch->id_batch])->save();
        }

        return $p->refresh();
    }

    private function taskOf(InvPallet $p): WhTask
    {
        $this->service->sync(self::BY);

        return WhTask::query()->where('pallet_id', $p->id_pallet)->where('status', 'OPEN')->firstOrFail();
    }

    private function top(WhTask $t): ?array
    {
        return $this->service->suggestions($t, null, null, null, 1)[0] ?? null;
    }

    private function binQty(WhLoc $bin, MatRaw $m): float
    {
        return (new StockTransferService)->balance('RM', (int) $m->material_id, null, (int) $bin->location_id);
    }

    public function test_sync_makes_one_open_task_per_pallet_in_receiving_and_is_idempotent(): void
    {
        $p = $this->pallet($this->matA, 200);
        $stored = $this->pallet($this->matB, 50, $this->binA);

        $first = $this->service->sync(self::BY);
        $this->assertGreaterThanOrEqual(1, $first['created']);
        $this->assertSame(0, $this->service->sync(self::BY)['created']);
        $this->assertSame(1, WhTask::query()->where('pallet_id', $p->id_pallet)->count());
        $this->assertSame(0, WhTask::query()->where('pallet_id', $stored->id_pallet)->count(), 'A pallet already in a storage bin needs no putaway.');

        $task = WhTask::query()->where('pallet_id', $p->id_pallet)->first();
        $this->assertSame('PUTAWAY', $task->task_type);
        $this->assertSame('OPEN', $task->status);
        $this->assertSame('SPB-TEST', $task->ref_doc_no);
        $this->assertSame($p->batch_id, $task->batch_id);
        $this->assertMatchesRegularExpression('/^WT\d{7}$/', $task->task_no);
    }

    public function test_the_suggestion_is_an_empty_bin_in_the_same_building_and_respects_capacity(): void
    {
        $t = $this->taskOf($this->pallet($this->matA, 200));

        $top = $this->top($t);
        $this->assertSame($this->binA->location_id, $top['bin']->location_id, 'Area order first, then bin code.');
        $this->assertStringContainsString('empty bin', $top['why']);

        $inMine = collect($this->service->suggestions($t, null, null, null, 10))->pluck('bin.location_id');
        $this->assertContains($this->binB->location_id, $inMine->all());
        $this->assertNotContains($this->small->location_id, $inMine->all(), 'Bin S01 holds 150 kg at most, the pallet is 200 kg.');
    }

    public function test_bins_suggested_for_earlier_pallets_in_one_run_are_counted_as_taken(): void
    {
        $this->binA->update(['capacity_pallet' => 1]);
        $one = $this->pallet($this->matA, 100);
        $two = $this->pallet($this->matB, 100);
        $this->service->sync(self::BY);
        $planner = new PutawayPlanner;
        $batchOf = fn (InvPallet $p) => InvBatch::query()->find($p->batch_id);

        $first = $planner->suggest($one, $batchOf($one), 1)[0];
        $planner->reserve($first['bin'], $one);
        $second = $planner->suggest($two, $batchOf($two), 1)[0];

        $this->assertSame($this->binA->location_id, $first['bin']->location_id);
        $this->assertNotSame($first['bin']->location_id, $second['bin']->location_id, 'Bin A01 takes one pallet only.');
    }

    public function test_one_item_per_bin_blocks_a_second_item(): void
    {
        $this->pallet($this->matB, 40, $this->oneItem);
        $p = $this->pallet($this->matA, 100);
        $t = $this->taskOf($p);

        $this->assertNotNull(BinRules::mixViolation($this->oneItem, WhZone::find($this->oneItem->zone_id), 'RM', (int) $this->matA->material_id, null, null));
        $this->assertNull(BinRules::mixViolation($this->oneItem, WhZone::find($this->oneItem->zone_id), 'RM', (int) $this->matB->material_id, null, null));
        $this->assertNotContains($this->oneItem->location_id, collect($this->service->suggestions($t, null, null, null, 20))->pluck('bin.location_id')->all());

        $this->expectException(WarehouseTaskRefused::class);
        $this->service->confirm($t, $p->pallet_no, $this->oneItem->location_code, 'forced', self::BY);
    }

    public function test_the_stock_transfer_service_also_enforces_the_mixing_rule(): void
    {
        $this->pallet($this->matB, 40, $this->oneItem);
        $p = $this->pallet($this->matA, 100);
        $t = InvTransfer::create(['transfer_date' => now()->toDateString(), 'from_warehouse_id' => $this->wh->id_warehouse, 'to_warehouse_id' => $this->wh->id_warehouse, 'created_by' => self::BY, 'updated_by' => self::BY]);
        InvTransferLine::create(['transfer_id' => $t->id_transfer, 'line_no' => 1, 'stock_class' => 'RM', 'item_id' => $this->matA->material_id, 'item_code' => $this->matA->material_code,
            'item_name' => $this->matA->material_name, 'from_location_id' => $this->rcv->location_id, 'to_location_id' => $this->oneItem->location_id, 'qty' => 100, 'uom_code' => 'KG',
            'pallet_id' => $p->id_pallet, 'created_by' => self::BY, 'updated_by' => self::BY]);

        $error = (new StockTransferService)->confirm($t, self::BY);

        $this->assertNotNull($error);
        $this->assertStringContainsString('one item only', $error);
        $this->assertSame(100.0, $this->binQty($this->rcv, $this->matA), 'Nothing moved.');
    }

    public function test_one_batch_per_bin_compares_the_batch_of_the_pallets_in_the_bin(): void
    {
        $this->oneItem->zone->update(['mix_rule' => 'ONE_BATCH']);
        $inBin = $this->pallet($this->matA, 30, $this->oneItem);
        $other = $this->pallet($this->matA, 70);
        $zone = WhZone::find($this->oneItem->zone_id);

        $this->assertNotSame($inBin->batch_id, $other->batch_id);
        $this->assertNotNull(BinRules::mixViolation($this->oneItem, $zone, 'RM', (int) $this->matA->material_id, null, (int) $other->batch_id));
        $this->assertNull(BinRules::mixViolation($this->oneItem, $zone, 'RM', (int) $this->matA->material_id, null, (int) $inBin->batch_id));
    }

    public function test_a_rejected_batch_gets_no_bin_and_a_quarantined_batch_waits(): void
    {
        $rejected = $this->taskOf($this->pallet($this->matA, 100, null, 'REJECTED'));
        $waiting = $this->taskOf($this->pallet($this->matB, 100, null, 'QUARANTINE'));

        $this->assertSame(WarehouseTaskService::BLOCKED, WarehouseTaskService::state($rejected, $rejected->batch));
        $this->assertSame([], $this->service->suggestions($rejected));
        $this->assertSame(WarehouseTaskService::WAITING, WarehouseTaskService::state($waiting, $waiting->batch));
        $this->assertSame([], $this->service->suggestions($waiting));

        $pallet = InvPallet::query()->find($waiting->pallet_id);
        try {
            $this->service->confirm($waiting, $pallet->pallet_no, $this->binA->location_code, 'x', self::BY);
            $this->fail('A pallet waiting for sampling must not be put away.');
        } catch (WarehouseTaskRefused) {
            $this->assertSame('OPEN', $waiting->refresh()->status);
        }

        // Sampling lulus: tugas yang sama menjadi siap.
        BatchService::setStatus($waiting->batch, 'RELEASED', self::BY);
        $this->assertSame(WarehouseTaskService::READY, WarehouseTaskService::state($waiting, $waiting->refresh()->batch));
        $this->assertNotEmpty($this->service->suggestions($waiting));
    }

    public function test_confirming_with_the_scans_moves_the_stock_through_a_real_transfer(): void
    {
        $p = $this->pallet($this->matA, 200);
        $t = $this->taskOf($p);
        $bin = $this->top($t)['bin'];

        $done = $this->service->confirm($t, $p->pallet_no, $bin->location_code, null, self::BY);

        $this->assertSame('DONE', $done->status);
        $this->assertSame($bin->location_id, $done->to_location_id);
        $this->assertSame(self::BY, $done->confirmed_by);
        $this->assertSame(0.0, $this->binQty($this->rcv, $this->matA));
        $this->assertSame(200.0, $this->binQty($bin, $this->matA));
        $this->assertSame((int) $bin->location_id, (int) $p->refresh()->location_id, 'The pallet label follows the transfer.');
        $transfer = InvTransfer::query()->find($done->transfer_id);
        $this->assertSame('CONFIRMED', $transfer->status());
        $this->assertSame(1, $transfer->lines()->count());
        $this->assertSame((int) $p->id_pallet, (int) $transfer->lines()->first()->pallet_id);
        $this->assertNull($done->reason);
        $this->assertSame(0, $this->service->sync(self::BY)['created'], 'The moved pallet is no longer in a receiving area.');

        $this->expectException(WarehouseTaskRefused::class);
        $this->service->confirm($done, $p->pallet_no, $bin->location_code, null, self::BY);
    }

    public function test_a_bin_other_than_the_suggestion_needs_a_reason(): void
    {
        $p = $this->pallet($this->matA, 200);
        $t = $this->taskOf($p);
        $other = $this->binB;
        $this->assertNotSame($other->location_id, $this->top($t)['bin']->location_id);

        try {
            $this->service->confirm($t, $p->pallet_no, $other->location_code, '  ', self::BY);
            $this->fail('A different bin without a reason must be refused.');
        } catch (WarehouseTaskRefused) {
            $this->assertSame('OPEN', $t->refresh()->status);
            $this->assertSame(200.0, $this->binQty($this->rcv, $this->matA));
        }

        $done = $this->service->confirm($t, $p->pallet_no, $other->location_code, 'Close to the cutting machine', self::BY);
        $this->assertSame('Close to the cutting machine', $done->reason);
        $this->assertSame(200.0, $this->binQty($other, $this->matA));
    }

    public function test_wrong_scans_are_refused_and_change_nothing(): void
    {
        $p = $this->pallet($this->matA, 100);
        $t = $this->taskOf($p);
        $bin = $this->top($t)['bin'];

        foreach ([['PL0000000', $bin->location_code], [$p->pallet_no, 'NOT-A-BIN'], [$p->pallet_no, $this->rcv->location_code], [$p->pallet_no, $this->wip->location_code]] as [$scanPallet, $scanBin]) {
            try {
                $this->service->confirm($t, $scanPallet, $scanBin, 'reason', self::BY);
                $this->fail("Scan {$scanPallet} / {$scanBin} must be refused.");
            } catch (WarehouseTaskRefused) {
                $this->assertSame('OPEN', $t->refresh()->status);
            }
        }
        $this->assertSame(100.0, $this->binQty($this->rcv, $this->matA));
        // Kode QR label (PLT: / LOC:) dikenali sama dengan kode polos.
        $done = $this->service->confirm($t, 'PLT:'.$p->pallet_no, 'LOC:'.$bin->location_code, null, self::BY);
        $this->assertSame('DONE', $done->status);
    }

    public function test_a_blocked_or_count_locked_bin_is_never_accepted(): void
    {
        $p = $this->pallet($this->matA, 100);
        $t = $this->taskOf($p);
        $this->binB->forceFill(['count_lock' => true])->save();
        $this->assertNotContains($this->binB->location_id, collect($this->service->suggestions($t, null, null, null, 20))->pluck('bin.location_id')->all());

        $this->expectException(WarehouseTaskRefused::class);
        $this->service->confirm($t, $p->pallet_no, $this->binB->location_code, 'reason', self::BY);
    }

    public function test_a_conditional_batch_goes_straight_to_the_line_area(): void
    {
        $p = $this->pallet($this->matA, 24, null, 'CONDITIONAL');
        $t = $this->taskOf($p);

        $this->assertSame(WarehouseTaskService::TO_LINE, WarehouseTaskService::state($t, $t->batch));
        $top = $this->top($t);
        $this->assertSame($this->wip->location_id, $top['bin']->location_id);
        $this->assertSame('dock to line', $top['why']);

        // Bin penyimpanan biasa bukan tujuan dock-to-line.
        try {
            $this->service->confirm($t, $p->pallet_no, $this->binA->location_code, 'reason', self::BY);
            $this->fail('Dock to line goes to a WIP or staging bin.');
        } catch (WarehouseTaskRefused) {
            $this->assertSame('OPEN', $t->refresh()->status);
        }
        $done = $this->service->confirm($t, $p->pallet_no, $this->wip->location_code, null, self::BY);
        $this->assertSame('DONE', $done->status);
        $this->assertSame(24.0, $this->binQty($this->wip, $this->matA));
    }

    public function test_open_tasks_are_cancelled_when_the_pallet_left_the_receiving_area(): void
    {
        $p = $this->pallet($this->matA, 100);
        $t = $this->taskOf($p);
        $p->forceFill(['location_id' => $this->binA->location_id])->save();

        $res = $this->service->sync(self::BY);

        $this->assertGreaterThanOrEqual(1, $res['cancelled']);
        $this->assertSame('CANCELLED', $t->refresh()->status);
    }

    public function test_the_screen_rows_show_quality_action_and_suggested_bin(): void
    {
        $this->taskOf($this->pallet($this->matA, 100));
        $this->taskOf($this->pallet($this->matB, 100, null, 'QUARANTINE'));

        $rows = collect(ReceivingPutaway::rows())->keyBy('item_code');
        $ready = $rows[$this->matA->material_code];
        $wait = $rows[$this->matB->material_code];

        $this->assertSame('READY', $ready['state']);
        $this->assertSame('Putaway', $ready['action']);
        $this->assertSame('Released', $ready['quality']);
        $this->assertSame($this->binA->location_code, $ready['suggested']);
        $this->assertSame('WAITING', $wait['state']);
        $this->assertSame('Waiting sampling', $wait['quality']);
        $this->assertSame('Waiting QC', $wait['action']);
        $this->assertSame('—', $wait['suggested']);
    }

    public function test_the_confirm_route_moves_the_stock_and_reports_a_refusal(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $user = SecUser::where('is_admin', true)->firstOrFail();
        $p = $this->pallet($this->matA, 100);
        $t = $this->taskOf($p);

        $screen = route('v2.screen', 'wh-receiving-putaway');
        $this->actingAs($user)->get($screen)->assertOk()->assertSee('How the bin is suggested')->assertSee($p->pallet_no);
        $this->actingAs($user)->get($screen.'?task='.$t->id_task)->assertOk()->assertSee('Scan pallet')->assertSee($this->binA->location_code);

        $bad = $this->actingAs($user)->post(route('v2.wh.task.confirm', $t->id_task), ['scan_pallet' => $p->pallet_no, 'scan_bin' => 'NOT-A-BIN']);
        $bad->assertSessionHasErrors('task');
        $this->assertSame('OPEN', $t->refresh()->status);

        $ok = $this->actingAs($user)->post(route('v2.wh.task.confirm', $t->id_task), ['scan_pallet' => $p->pallet_no, 'scan_bin' => $this->top($t)['bin']->location_code]);
        $ok->assertRedirect(route('v2.screen', 'wh-receiving-putaway'));
        $this->assertSame('DONE', $t->refresh()->status);
        $this->assertNotNull(DB::connection('tm2inv')->table('inv_transfer')->where('id_transfer', $t->transfer_id)->value('transfer_no'));
    }
}
