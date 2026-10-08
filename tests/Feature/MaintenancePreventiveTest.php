<?php

namespace Tests\Feature;

use App\Models\Master\ProdMachine;
use App\Models\Master\ProdMachineDowntime;
use App\Models\Mnt\MntAsset;
use App\Models\Mnt\MntDailyCheck;
use App\Models\Mnt\MntLog;
use App\Models\Mnt\MntMeterReading;
use App\Models\Mnt\MntOrder;
use App\Models\Mnt\MntPlan;
use App\Models\Mnt\MntRequest;
use App\Models\Mnt\MntWatchPoint;
use App\Models\Sys\SecUser;
use App\Services\Maintenance\ComplianceService;
use App\Services\Maintenance\DailyCheckService;
use App\Services\Maintenance\MeterService;
use App\Services\Maintenance\OrderService;
use App\Services\Maintenance\PlanService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Maintenance Fase 2 (Preventive): jadwal plan, PMO dari plan, siklus order (nomor PMO, downtime mesin), meter, Daily Check, kepatuhan PM.
// Jalankan terhadap database dummy (semua koneksi ddt*) supaya tidak menyentuh live:
//   DB_SYS=ddtsys DB_MST=ddtmst DB_PRD=ddtprd DB_MNT=ddtmnt (dan DB_* lain) php artisan test --filter=MaintenancePreventiveTest
// Dilewati bila tabel mnt_* belum ada. Semua baris buatan test (kode TEST-*) dihapus di tearDown; status mesin dikembalikan.
class MaintenancePreventiveTest extends TestCase
{
    private array $downtimes = [];

    private ?ProdMachine $machine = null;

    private ?string $statusBefore = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::connection('tm2mnt')->hasTable('mnt_order')) {
            $this->markTestSkipped('Tabel Maintenance Fase 2 belum ada di database ini; jalankan dengan DB_MNT=ddtmnt.');
        }
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        if (Schema::connection('tm2mnt')->hasTable('mnt_order')) {
            $this->cleanup();
        }
        if ($this->machine) {
            ProdMachine::whereKey($this->machine->machine_id)->update(['machine_status' => $this->statusBefore]);
        }
        parent::tearDown();
    }

    private function cleanup(): void
    {
        $assets = MntAsset::where('asset_code', 'like', 'TEST-%')->pluck('id_asset');
        $orders = MntOrder::whereIn('asset_id', $assets)->pluck('id_order');
        ProdMachineDowntime::whereIn('downtime_id', MntOrder::whereIn('id_order', $orders)->whereNotNull('downtime_id')->pluck('downtime_id')->merge($this->downtimes))->delete();
        MntLog::whereIn('doc_type', ['ORDER', 'PLAN', 'CHECK', 'REQUEST', 'ASSET'])->where(fn ($q) => $q->whereIn('doc_id', $orders)->orWhereIn('doc_id', $assets))->delete();
        MntOrder::whereIn('id_order', $orders)->delete();
        MntRequest::whereIn('asset_id', $assets)->delete();
        MntDailyCheck::whereIn('asset_id', $assets)->delete();
        MntMeterReading::whereIn('asset_id', $assets)->delete();
        MntPlan::whereIn('asset_id', $assets)->delete();
        MntAsset::whereIn('id_asset', $assets)->delete();
    }

    private function asset(string $code, array $extra = []): MntAsset
    {
        return MntAsset::create(array_merge(['asset_code' => $code, 'asset_name' => 'Test '.$code, 'asset_class' => 'FACILITY', 'meter_type' => 'NONE', 'activate' => true], $extra));
    }

    private function plan(MntAsset $a, array $extra = []): MntPlan
    {
        $p = MntPlan::create(array_merge(['plan_code' => 'TEST-PM-'.uniqid(), 'asset_id' => $a->id_asset, 'plan_name' => 'TEST monthly', 'order_type' => 'PREVENTIVE', 'trigger_mode' => 'TIME',
            'interval_value' => 1, 'interval_unit' => 'MONTH', 'schedule_mode' => 'FLOATING', 'lead_days' => 3, 'needs_stop' => false, 'activate' => true], $extra));
        $p->tasks()->create(['seq_no' => 1, 'task_text' => 'TEST check', 'task_kind' => 'INSPECT', 'is_required' => true]);

        return $p;
    }

    public function test_next_due_floating_follows_the_last_done_date_and_fixed_keeps_the_calendar(): void
    {
        $a = $this->asset('TEST-A');
        $svc = new PlanService;
        $floating = $this->plan($a);
        $this->assertSame('2026-11-15', $svc->nextDue($floating, CarbonImmutable::parse('2026-10-15'))['next_due_date']);

        $fixed = $this->plan($a, ['schedule_mode' => 'FIXED', 'interval_value' => 1, 'interval_unit' => 'YEAR', 'next_due_date' => today()->addMonths(2)]);
        $next = $svc->nextDue($fixed, CarbonImmutable::now()->addDays(20));
        $this->assertSame(today()->addMonths(2)->addYear()->toDateString(), $next['next_due_date'], 'FIXED moves from the schedule, not from the completion date');

        $meter = $this->plan($a, ['trigger_mode' => 'METER', 'interval_value' => null, 'interval_unit' => null, 'meter_type' => 'ODOMETER_KM', 'meter_interval' => 5000]);
        $this->assertSame(15000.0, $svc->nextDue($meter, null, 10000.0)['next_due_meter']);
    }

    public function test_due_plan_creates_one_draft_order_with_its_checklist_and_not_a_second(): void
    {
        $a = $this->asset('TEST-B');
        $svc = new PlanService;
        $p = $this->plan($a, ['next_due_date' => today()->addDays(2)]);
        $this->assertTrue($svc->isDue($p));
        $o = $svc->generateFromPlan($p->refresh());
        $this->assertSame('DRAFT', $o->docStatus());
        $this->assertSame(1, $o->tasks()->count());
        $this->assertNull($svc->generateFromPlan($p->refresh()), 'an open order from the plan blocks a second one');

        $far = $this->plan($a, ['next_due_date' => today()->addMonths(2)]);
        $this->assertFalse($svc->isDue($far));
        $this->assertNull($svc->generateFromPlan($far));
    }

    public function test_pmo_cycle_numbers_the_order_and_moves_the_plan_forward_on_close(): void
    {
        $a = $this->asset('TEST-C');
        $p = $this->plan($a, ['next_due_date' => today()->addDay()]);
        $o = (new PlanService)->generateFromPlan($p);
        $s = new OrderService;

        $s->confirm($o);
        $this->assertMatchesRegularExpression('/^PMO-\d{4}-\d{4}$/', $o->refresh()->doc_no);
        $this->assertSame('SCHEDULED', $o->work_status);

        $s->start($o->refresh());
        try {
            $s->done($o->refresh());
            $this->fail('Done must wait for the required checklist results.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('maintenance', $e->errors());
        }
        $o->tasks()->update(['result' => 'OK']);
        $s->done($o->refresh());
        $this->assertSame('DONE', $o->refresh()->work_status);
        $s->close($o->refresh());
        $this->assertTrue($o->refresh()->stat_close);
        $p->refresh();
        $this->assertSame(now()->toDateString(), $p->last_done_date->toDateString());
        $this->assertSame(now()->addMonthNoOverflow()->toDateString(), $p->next_due_date->toDateString(), 'FLOATING: next due from the completion date');
    }

    public function test_machine_asset_order_opens_and_closes_downtime_and_restores_machine_status(): void
    {
        $this->machine = ProdMachine::where('machine_code', 'CUT-02')->firstOrFail();
        $this->statusBefore = $this->machine->machine_status;
        $asset = MntAsset::firstOrCreate(['machine_id' => $this->machine->machine_id], ['asset_code' => $this->machine->machine_code, 'asset_name' => $this->machine->machine_name,
            'asset_class' => 'MACHINE', 'meter_type' => 'NONE', 'activate' => true]);
        $order = (new OrderService)->create(['asset_id' => $asset->id_asset, 'order_type' => 'PREVENTIVE', 'description' => 'TEST machine service', 'needs_stop' => true]);
        $s = new OrderService;
        $s->confirm($order);
        $s->start($order->refresh());
        $order->refresh();
        $this->downtimes[] = $order->downtime_id;
        $this->assertNotNull($order->downtime_id);
        $this->assertSame('MAINTENANCE', $this->machine->refresh()->machine_status);
        $this->assertTrue((bool) ProdMachineDowntime::find($order->downtime_id)->is_planned, 'preventive work is planned downtime');

        $s->done($order->refresh());
        $this->assertNotNull(ProdMachineDowntime::find($order->downtime_id)->end_at);
        $this->assertSame('READY', $this->machine->refresh()->machine_status);
        // bersihkan: order milik aset mesin nyata tidak ikut pembersihan TEST-*
        MntLog::where('doc_type', 'ORDER')->where('doc_id', $order->id_order)->delete();
        $order->delete();
    }

    public function test_meter_rejects_a_lower_reading_without_a_note_and_never_overwrites(): void
    {
        $a = $this->asset('TEST-D', ['meter_type' => 'ODOMETER_KM']);
        $svc = new MeterService;
        $svc->record($a, 'ODOMETER_KM', 1000);
        $svc->record($a, 'ODOMETER_KM', 1500);
        $this->assertSame(1500.0, MntMeterReading::latestValue($a->id_asset, 'ODOMETER_KM'));
        try {
            $svc->record($a, 'ODOMETER_KM', 900);
            $this->fail('A lower reading without a note must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('meter', $e->errors());
        }
        $svc->record($a, 'ODOMETER_KM', 100, null, 'MANUAL', 'Counter replaced');
        $this->assertSame(3, MntMeterReading::where('asset_id', $a->id_asset)->count());
        $this->assertSame([], $svc->accumulateFromProduction(), 'only machine assets with a run-hours or impressions meter are accumulated');
    }

    public function test_daily_check_uses_operator_points_and_not_ok_opens_a_request(): void
    {
        $a = $this->asset('TEST-E', ['criticality' => 'A']);
        MntWatchPoint::create(['asset_id' => $a->id_asset, 'check_text' => 'Oil level', 'frequency' => 'SHIFT', 'checked_by_role' => 'OPERATOR', 'activate' => true]);
        MntWatchPoint::create(['asset_id' => $a->id_asset, 'check_text' => 'Temperature', 'frequency' => 'DAILY', 'checked_by_role' => 'OPERATOR', 'normal_min' => 10, 'normal_max' => 20, 'measure_uom' => 'C', 'activate' => true]);
        MntWatchPoint::create(['asset_id' => $a->id_asset, 'check_text' => 'Grease', 'frequency' => 'WEEKLY', 'checked_by_role' => 'TECHNICIAN', 'activate' => true]);
        $svc = new DailyCheckService;

        $sheet = $svc->open($a, today()->toDateString(), '1');
        $this->assertSame(2, $sheet->lines()->count(), 'weekly and technician points are not on the operator sheet');
        $this->assertSame($sheet->id_daily_check, $svc->open($a, today()->toDateString(), '1')->id_daily_check, 'the same sheet opens again');

        $rows = [];
        foreach ($sheet->lines as $l) {
            $rows[$l->id_daily_check_line] = $l->check_text === 'Temperature' ? ['measured_value' => 25, 'note' => 'too hot'] : ['result' => 'OK'];
        }
        $svc->save($sheet, $rows);
        $this->assertSame('NOT_OK', $sheet->lines()->where('check_text', 'Temperature')->first()->result, 'a value outside the limits is suggested NOT_OK');
        $this->assertSame(1, $svc->submit($sheet->refresh()));
        $req = MntRequest::where('asset_id', $a->id_asset)->firstOrFail();
        $this->assertSame('DAILY_CHECK', $req->source_type);
        $this->assertSame('P2', $req->priority, 'criticality A gives priority P2');
        $this->assertMatchesRegularExpression('/^MR-\d{4}-\d{4}$/', $req->doc_no);
        $this->expectException(ValidationException::class);
        $svc->save($sheet->refresh(), []);
    }

    public function test_daily_check_refuses_an_asset_without_operator_watch_points(): void
    {
        $this->expectException(ValidationException::class);
        (new DailyCheckService)->open($this->asset('TEST-F'), today()->toDateString(), '1');
    }

    public function test_compliance_judges_on_time_late_not_done_and_pending(): void
    {
        $a = $this->asset('TEST-G');
        $p = $this->plan($a, ['interval_value' => 1, 'interval_unit' => 'MONTH']);   // 10% of 30 days = 3 days tolerance
        $mk = fn (string $due, ?string $done, string $work) => MntOrder::create(['order_type' => 'PREVENTIVE', 'plan_id' => $p->id_plan, 'asset_id' => $a->id_asset, 'description' => 'TEST',
            'planned_start' => $due.' 07:00', 'actual_end' => $done ? $done.' 10:00' : null, 'work_status' => $work, 'stat_confirm' => true, 'stat_close' => $work === 'DONE']);
        $svc = new ComplianceService;
        $today = CarbonImmutable::parse('2026-10-20');
        $this->assertSame('on_time', $svc->judge($mk('2026-10-01', '2026-10-03', 'DONE'), $today), 'two days late is within the 3 day tolerance');
        $this->assertSame('late', $svc->judge($mk('2026-10-01', '2026-10-09', 'DONE'), $today));
        $this->assertSame('not_done', $svc->judge($mk('2026-10-05', null, 'SCHEDULED'), $today));
        $this->assertSame('pending', $svc->judge($mk('2026-10-19', null, 'SCHEDULED'), $today), 'not yet past due plus tolerance');
        $sum = $svc->summarize(MntOrder::with('plan')->where('plan_id', $p->id_plan)->get(), $today);
        $this->assertSame(1, $sum['on_time']);
        $this->assertSame(3, $sum['due']);
        $this->assertSame(33.3, $sum['pct']);
    }

    public function test_screens_render_and_the_head_can_run_an_order_through_the_routes(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $a = $this->asset('TEST-H');
        foreach (['maintenance-plan', 'maintenance-pmo', 'maintenance-bmo', 'maintenance-pm-calendar', 'maintenance-daily-check', 'maintenance-meter', 'maintenance-pm-compliance'] as $slug) {
            $this->get(route('v2.screen', $slug))->assertOk();
        }
        $this->post(route('v2.mnt.order.create'), ['asset_id' => $a->id_asset, 'order_type' => 'INSPECTION', 'description' => 'TEST route order'])->assertRedirect();
        $o = MntOrder::where('asset_id', $a->id_asset)->firstOrFail();
        $this->get(route('v2.screen', 'maintenance-pmo').'?id='.$o->id_order)->assertOk();
        $this->post(route('v2.mnt.order.save', $o->id_order), ['action' => 'confirm', 'asset_id' => $a->id_asset, 'order_type' => 'INSPECTION', 'priority' => 'P3', 'description' => 'TEST route order'])->assertRedirect();
        $this->assertStringStartsWith('PMO-', $o->refresh()->doc_no);
        $this->post(route('v2.mnt.order.save', $o->id_order), ['action' => 'start'])->assertRedirect();
        $this->assertSame('IN_PROGRESS', $o->refresh()->work_status);
        $this->post(route('v2.mnt.plan.save'), ['action' => 'save', 'plan_code' => 'TEST-PM-ROUTE', 'plan_name' => 'TEST route plan', 'asset_id' => $a->id_asset, 'order_type' => 'PREVENTIVE',
            'trigger_mode' => 'TIME', 'interval_value' => 2, 'interval_unit' => 'WEEK', 'schedule_mode' => 'FLOATING', 'lead_days' => 3])->assertRedirect();
        $plan = MntPlan::where('plan_code', 'TEST-PM-ROUTE')->firstOrFail();
        $this->assertNotNull($plan->next_due_date, 'the next due date is calculated by the system on save');
        $this->post(route('v2.mnt.plan.save'), ['action' => 'save', 'plan_code' => 'TEST-PM-BAD', 'plan_name' => 'TEST bad', 'asset_id' => $a->id_asset, 'order_type' => 'PREVENTIVE',
            'trigger_mode' => 'METER', 'schedule_mode' => 'FLOATING'])->assertSessionHasErrors(['meter_type', 'meter_interval']);
        $this->get(route('v2.screen', 'maintenance-plan').'?id='.$plan->id_plan)->assertOk()->assertSee('TEST-PM-ROUTE');
    }
}
