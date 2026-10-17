<?php

/*
 * REMARK
 * Tujuan       : Uji layar Transit, hitung stok, subkon, titipan customer (200, form Receive dan Start Count lewat route).
 * Sumber hulu  : Gedung dan area uji ZZV-*, bahan bebas dari master.
 * Input        : Tidak ada.
 * Output       : Lulus atau gagal; baris uji dibersihkan di tearDown.
 * Dipakai oleh : php artisan test --env=mockup.
 * Status       : Aktif. Uji Tool Register dihapus 2026-10-18 bersama layarnya.
 */

namespace Tests\Feature;

use App\Models\Inventory\WhTask;
use App\Models\Master\MatRaw;
use App\Models\Master\WhLoc;
use App\Models\Master\WhZone;
use App\Models\Sys\SecUser;
use App\Services\Inventory\CountTaskService;
use App\Support\V2\Grid\Grids;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\Feature\Concerns\BuildsTestWarehouse;
use Tests\TestCase;

// Layar v2.01 Transit / hitung stok / subkon / titipan customer: semua terbuka (200), form Receive dan Start Count bekerja lewat route. Menulis ke database sungguhan (gedung ZZV-A) dan MEMBERSIHKAN sendiri di tearDown().
class TransitScreensTest extends TestCase
{
    use BuildsTestWarehouse;

    private const BY = 'test_transit_screens';

    private WhLoc $src;

    private WhLoc $dst;

    private MatRaw $mat;

    private WhZone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanFixture();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->actingAs(SecUser::query()->where('is_admin', 1)->firstOrFail());
        $a = $this->makeWarehouse('ZZV-A');
        $b = $this->makeWarehouse('ZZV-B');
        $this->zone = $this->makeZone($a, 'STV', 'STORAGE', ['count_every_days' => 30]);
        $this->src = $this->makeBin($this->zone, 'ZZV-A-01');
        $this->dst = $this->makeBin($this->makeZone($b, 'STW', 'STORAGE'), 'ZZV-B-01');
        [$this->mat] = $this->freeMaterials(1);
        $this->seedStock($this->mat, $this->src, 100);
    }

    protected function tearDown(): void
    {
        $this->cleanFixture();
        $this->assertFixtureClean();
        parent::tearDown();
    }

    public function test_every_new_screen_opens_and_is_in_the_menu(): void
    {
        foreach (['wh-in-transit', 'wh-count-tasks', 'subcon-send', 'stock-at-vendor', 'customer-material'] as $slug) {
            $this->assertNotNull(Grids::find($slug), "{$slug} is a registered grid");
            $this->get(route('v2.screen', $slug))->assertOk();
        }
        $this->get(route('v2.screen', 'subcon-send').'?new=1')->assertOk()->assertSee('Send to Subcontractor');
    }

    public function test_the_transfer_form_offers_two_step_and_the_receive_route_moves_the_stock(): void
    {
        $doc = $this->draftTransfer($this->src, $this->dst, $this->mat, 40, 'TWO_STEP');
        $this->get(route('v2.screen', 'stock-transfer').'?id='.$doc->id_transfer)->assertOk()->assertSee('Two-step');

        $this->post(route('v2.wh.transfer.save', $doc->id_transfer), ['action' => 'confirm', 'transit_mode' => 'TWO_STEP', 'to_warehouse_id' => $this->dst->warehouse_id])->assertRedirect();
        $this->assertSame('SHIPPED', $doc->refresh()->transit_status);

        $this->get(route('v2.screen', 'wh-in-transit').'?id='.$doc->id_transfer)->assertOk()->assertSee('Receive');
        $line = $doc->lines()->first();
        $this->post(route('v2.wh.transit.receive', $doc->id_transfer), ['received' => [$line->id_transfer_line => 35]])->assertRedirect();
        $this->assertSame('DISCREPANCY', $doc->refresh()->transit_status);
        $this->get(route('v2.screen', 'wh-in-transit').'?id='.$doc->id_transfer)->assertOk()->assertSee('Loss');

        $this->post(route('v2.wh.transit.resolve', $doc->id_transfer), ['line' => $line->id_transfer_line, 'how' => 'RETURN'])->assertRedirect();
        $this->assertSame('CLOSED', $doc->refresh()->transit_status);
        $this->assertEquals(65, $this->balance($this->src, $this->mat));
        $this->assertEquals(35, $this->balance($this->dst, $this->mat));
    }

    public function test_start_count_and_confirm_work_through_the_routes(): void
    {
        $this->post(route('v2.wh.count.start'), ['zone_id' => $this->zone->id_zone])->assertRedirect();
        $task = WhTask::query()->where('task_type', 'COUNT')->where('count_zone_id', $this->zone->id_zone)->where('status', 'OPEN')->firstOrFail();
        $page = $this->get(route('v2.screen', 'wh-count-tasks').'?id='.$task->id_task)->assertOk();
        $page->assertSee('Counted Quantity');

        $this->post(route('v2.wh.count.confirm', $task->id_task), ['counted_qty' => 90])->assertRedirect();
        $this->assertEquals(90, $this->balance($this->src, $this->mat));
        $this->assertSame('DONE', $task->refresh()->status);
        $this->assertFalse((bool) $this->src->refresh()->count_lock);
        $this->assertFalse((new CountTaskService)->dueZones()->contains('id_zone', $this->zone->id_zone));
    }
}
