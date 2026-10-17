<?php

/*
 * REMARK
 * Tujuan       : Uji Maintenance menahan mesin pada penjadwalan mundur (MachineCalendarService, MaintenanceBlockProvider).
 * Sumber hulu  : Order Maintenance uji (TEST-*).
 * Input        : Tidak ada.
 * Output       : Lulus atau gagal; baris uji dihapus di tearDown.
 * Dipakai oleh : php artisan test --env=mockup.
 * Status       : Aktif. Uji geser manual (PdrEngine) dihapus 2026-10-18.
 */

namespace Tests\Feature;

use App\Models\Master\ProdMachine;
use App\Models\Master\SysHoliday;
use App\Models\Mnt\MntAsset;
use App\Models\Mnt\MntLog;
use App\Models\Mnt\MntOrder;
use App\Services\Maintenance\OrderService;
use App\Services\Scheduling\MachineCalendarService;
use App\Services\Scheduling\MaintenanceBlockProvider;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Maintenance menahan mesin di Scheduling: penjadwalan mundur melompati jendela order Maintenance.
// Jalankan terhadap database dummy (DB_MNT=ddtmnt dan ddt* lain). Baris buatan test (TEST-*) dihapus di tearDown.
class MaintenanceSchedulingBlockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::connection('tm2mnt')->hasTable('mnt_order')) {
            $this->markTestSkipped('Tabel Maintenance belum ada di database ini; jalankan dengan DB_MNT=ddtmnt.');
        }
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        if (Schema::connection('tm2mnt')->hasTable('mnt_order')) {
            $this->cleanup();
        }
        parent::tearDown();
    }

    private function cleanup(): void
    {
        $assets = MntAsset::where('asset_code', 'like', 'TEST-%')->pluck('id_asset');
        $orders = MntOrder::whereIn('asset_id', $assets)->pluck('id_order');
        MntLog::whereIn('doc_type', ['ORDER', 'ASSET'])->where(fn ($q) => $q->whereIn('doc_id', $orders)->orWhereIn('doc_id', $assets))->delete();
        MntOrder::whereIn('id_order', $orders)->delete();
        MntAsset::whereIn('id_asset', $assets)->delete();
    }

    /** Mesin tiruan tanpa histori roster (jam default 08:00-20:00; id logis, tidak perlu baris Master Mesin) dan hari kerja berikutnya (bukan Minggu / libur). */
    private function fixture(): array
    {
        $machine = new ProdMachine(['machine_id' => 999999]);
        $machine->machine_id = 999999;
        $day = Carbon::now()->addDay()->startOfDay();
        while ($day->isSunday() || SysHoliday::isHoliday($day)) {
            $day->addDay();
        }

        return [$machine, $day];
    }

    private function blockingOrder(ProdMachine $machine, Carbon $day, int $fromHour, int $toHour): MntOrder
    {
        $asset = MntAsset::create(['asset_code' => 'TEST-MTN', 'asset_name' => 'Test', 'asset_class' => 'MACHINE', 'machine_id' => $machine->machine_id, 'meter_type' => 'NONE', 'activate' => true]);
        $svc = new OrderService('tester');
        $o = $svc->create(['order_type' => 'PREVENTIVE', 'asset_id' => $asset->id_asset, 'description' => 'TEST block', 'needs_stop' => true, 'planned_start' => $day->copy()->setHour($fromHour)]);
        $o->update(['planned_end' => $day->copy()->setHour($toHour)]);
        $svc->confirm($o->refresh());

        return $o->refresh();
    }

    public function test_backward_scheduling_skips_the_maintenance_window(): void
    {
        [$machine, $day] = $this->fixture();
        $calendar = app(MachineCalendarService::class);
        $finish = $day->copy()->setHour(14);

        $before = $calendar->subtractWorkingTime($finish->copy(), CarbonInterval::hours(4), $machine->machine_id);
        $this->assertSame('10:00', $before->format('H:i'), 'without maintenance 4 working hours before 14:00 start at 10:00');

        $this->blockingOrder($machine, $day, 10, 12);
        $after = (new MachineCalendarService(new MaintenanceBlockProvider))->subtractWorkingTime($finish->copy(), CarbonInterval::hours(4), $machine->machine_id);
        $this->assertSame('08:00', $after->format('H:i'), '2 h after the window (12-14) plus 2 h before it (08-10)');
    }

    public function test_done_or_stopless_orders_do_not_block_the_machine(): void
    {
        [$machine, $day] = $this->fixture();
        $order = $this->blockingOrder($machine, $day, 10, 12);
        $provider = new MaintenanceBlockProvider;
        $this->assertCount(1, array_filter($provider->blocksFor($day->copy()->startOfDay(), $day->copy()->endOfDay(), [$machine->machine_id]), fn ($b) => $b['order_id'] === $order->id_order));

        $order->update(['needs_stop' => false]);
        $this->assertCount(0, array_filter((new MaintenanceBlockProvider)->blocksFor($day->copy()->startOfDay(), $day->copy()->endOfDay(), [$machine->machine_id]), fn ($b) => $b['order_id'] === $order->id_order));
    }
}
