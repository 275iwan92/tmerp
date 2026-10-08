<?php

namespace Tests\Feature;

use App\Models\Inventory\RmStock;
use App\Models\Inventory\WhTask;
use App\Models\Master\MatRaw;
use App\Models\Master\WhLoc;
use App\Models\Master\WhZone;
use App\Services\Inventory\CountTaskService;
use App\Services\Inventory\StockTransferService;
use App\Services\Inventory\WarehouseTaskRefused;
use Tests\Feature\Concerns\BuildsTestWarehouse;
use Tests\TestCase;

// Hitung stok v2.01: area yang jatuh tempo (wh_zone.count_every_days), tugas COUNT per posisi, kunci bin (transfer ke / dari bin terkunci ditolak),
// hasil hitung yang berbeda membuat dokumen Stock Opname yang diposting, bin dibuka lagi bila selesai. Menulis ke database sungguhan (gedung ZZC-A)
// dan MEMBERSIHKAN sendiri di tearDown().
class CountLockTest extends TestCase
{
    use BuildsTestWarehouse;

    private const BY = 'test_countlock';

    private WhZone $zone;

    private WhLoc $binA;

    private WhLoc $binB;

    private WhLoc $other;

    private MatRaw $matA;

    private MatRaw $matB;

    private CountTaskService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanFixture();
        $this->svc = new CountTaskService;
        $wh = $this->makeWarehouse('ZZC-A');
        $this->zone = $this->makeZone($wh, 'CNT', 'STORAGE', ['count_every_days' => 30]);
        $this->binA = $this->makeBin($this->zone, 'ZZC-A-01');
        $this->binB = $this->makeBin($this->zone, 'ZZC-A-02');
        $this->other = $this->makeBin($this->makeZone($wh, 'OTH', 'STORAGE'), 'ZZC-A-09');
        [$this->matA, $this->matB] = $this->freeMaterials(2);
        $this->seedStock($this->matA, $this->binA, 100);
        $this->seedStock($this->matB, $this->binB, 50);
    }

    protected function tearDown(): void
    {
        $this->cleanFixture();
        $this->assertFixtureClean();
        parent::tearDown();
    }

    private function taskFor(MatRaw $m): WhTask
    {
        return WhTask::query()->where('task_type', 'COUNT')->where('item_id', $m->material_id)->where('status', 'OPEN')->firstOrFail();
    }

    public function test_a_zone_that_was_never_counted_is_due_and_starting_makes_a_task_per_position_and_locks_the_bins(): void
    {
        $this->assertTrue($this->svc->dueZones()->contains('id_zone', $this->zone->id_zone));
        $this->assertFalse($this->svc->dueZones()->contains('zone_code', 'OTH'), 'An area without Count Every is never due.');

        $this->assertSame(2, $this->svc->start($this->zone, self::BY));
        $a = $this->taskFor($this->matA);
        $this->assertSame('COUNT', $a->task_type);
        $this->assertEquals(100, (float) $a->system_qty);
        $this->assertSame($this->binA->location_id, $a->from_location_id);
        $this->assertTrue((bool) $this->binA->refresh()->count_lock);
        $this->assertTrue((bool) $this->binB->refresh()->count_lock);
        $this->assertFalse((bool) $this->other->refresh()->count_lock);

        $this->assertFalse($this->svc->dueZones()->contains('id_zone', $this->zone->id_zone), 'Not due again while it is being counted.');
        $this->expectException(WarehouseTaskRefused::class);
        $this->svc->start($this->zone, self::BY);
    }

    public function test_a_transfer_from_or_to_a_locked_bin_is_refused(): void
    {
        $this->svc->start($this->zone, self::BY);
        $transfers = new StockTransferService;

        $out = $this->draftTransfer($this->binA, $this->other, $this->matA, 10);
        $this->assertStringContainsString('locked for counting', (string) $transfers->confirm($out, self::BY));
        $in = $this->draftTransfer($this->other, $this->binB, $this->matB, 1);
        $this->seedStock($this->matB, $this->other, 5);
        $this->assertStringContainsString('locked for counting', (string) $transfers->confirm($in, self::BY));
        $this->assertEquals(100, $this->balance($this->binA, $this->matA));
    }

    public function test_a_matching_count_makes_no_document_and_unlocks_the_bin_when_its_tasks_are_done(): void
    {
        $this->svc->start($this->zone, self::BY);
        $done = $this->svc->confirm($this->taskFor($this->matA), 100, self::BY);

        $this->assertSame('DONE', $done->status);
        $this->assertNull($done->adjustment_doc_no);
        $this->assertFalse((bool) $this->binA->refresh()->count_lock, 'Its only task is done, so the bin is open again.');
        $this->assertTrue((bool) $this->binB->refresh()->count_lock);
        $this->assertSame(0, RmStock::query()->where('create_by', self::BY)->count());
    }

    public function test_a_difference_makes_a_posted_stock_opname_that_corrects_the_balance(): void
    {
        $this->svc->start($this->zone, self::BY);
        $done = $this->svc->confirm($this->taskFor($this->matB), 46, self::BY);

        $this->assertNotNull($done->adjustment_doc_no);
        $doc = RmStock::query()->with('lines')->findOrFail($done->adjustment_doc_id);
        $this->assertSame('OP', $doc->doc_cat);
        $this->assertTrue((bool) $doc->stat_confirm);
        $this->assertEquals(-4, (float) $doc->lines->first()->qty);
        $this->assertEquals(50, (float) $doc->lines->first()->qty_system);
        $this->assertEquals(46, (float) $doc->lines->first()->qty_physical);
        $this->assertEquals(46, $this->balance($this->binB, $this->matB));
        $this->assertEquals(46, (float) $done->counted_qty);
    }

    public function test_a_surplus_is_booked_and_a_negative_count_is_refused(): void
    {
        $this->svc->start($this->zone, self::BY);
        $task = $this->taskFor($this->matA);

        try {
            $this->svc->confirm($task, -1, self::BY);
            $this->fail('A negative count must be refused.');
        } catch (WarehouseTaskRefused) {
            $this->assertSame('OPEN', $task->refresh()->status);
        }
        $this->svc->confirm($task, 103, self::BY);
        $this->assertEquals(103, $this->balance($this->binA, $this->matA));
    }

    public function test_a_cancelled_task_unlocks_the_bin_and_a_finished_zone_is_not_due_before_its_interval(): void
    {
        $this->svc->start($this->zone, self::BY);
        $this->svc->cancel($this->taskFor($this->matA), 'Not needed', self::BY);
        $this->svc->confirm($this->taskFor($this->matB), 50, self::BY);

        $this->assertFalse((bool) $this->binA->refresh()->count_lock);
        $this->assertFalse((bool) $this->binB->refresh()->count_lock);
        $this->assertFalse($this->svc->dueZones()->contains('id_zone', $this->zone->id_zone), 'Counted a moment ago, the 30 days have not passed.');

        WhTask::query()->where('created_by', self::BY)->update(['created_at' => now()->subDays(31)]);
        $this->assertTrue($this->svc->dueZones()->contains('id_zone', $this->zone->id_zone), 'After Count Every days it is due again.');
    }

    public function test_the_artisan_command_lists_and_starts_due_areas(): void
    {
        $this->artisan('inv:count-due', ['--dry-run' => true])->expectsOutputToContain('CNT')->assertSuccessful();
        $this->assertSame(0, WhTask::query()->where('created_by', self::BY)->count(), 'Dry run creates nothing.');

        $this->artisan('inv:count-due', ['--by' => self::BY, '--zone' => 'CNT'])->assertSuccessful();
        $this->assertSame(2, WhTask::query()->where('created_by', self::BY)->where('task_type', 'COUNT')->count());
        $this->assertTrue((bool) $this->binA->refresh()->count_lock);
    }
}
