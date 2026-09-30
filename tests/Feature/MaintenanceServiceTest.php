<?php

namespace Tests\Feature;

use App\Models\Master\ProdMachine;
use App\Models\Master\ProdMachineDowntime;
use App\Models\Prd\MntLog;
use App\Models\Prd\MntPlan;
use App\Models\Prd\MntRequest;
use App\Models\Prd\MntWo;
use App\Models\Sys\SecUser;
use App\Services\Maintenance\MaintenanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Alur Maintenance (docs/maintenance_tier1.md): Request P1 -> WO otomatis + downtime, Start/Hold/Resume/Done/Close,
// status mesin & downtime ikut, generator PM. Menulis ke database situs; semua baris buatan test dihapus di tearDown dan
// status mesin dikembalikan persis ke nilai sebelum test. Dilewati bila tabel mnt_* belum ada (migration tm2* menunggu OK user).
class MaintenanceServiceTest extends TestCase
{
    private array $requests = [];

    private array $wos = [];

    private array $plans = [];

    private ?ProdMachine $machine = null;

    private ?string $statusBefore = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::connection('tm2prd')->hasTable('mnt_wo')) {
            $this->markTestSkipped('Tabel Maintenance (mnt_*) belum ada di database ini.');
        }
        $this->machine = ProdMachine::where('machine_code', 'CUT-02')->firstOrFail();
        $this->statusBefore = $this->machine->machine_status;
    }

    protected function tearDown(): void
    {
        if ($this->machine) {
            $wos = MntWo::whereIn('id_wo', $this->wos)->orWhereIn('request_id', $this->requests)->orWhereIn('plan_id', $this->plans)->get();
            $down = $wos->pluck('downtime_id')->merge(MntRequest::whereIn('id_request', $this->requests)->pluck('downtime_id'))->filter();
            ProdMachineDowntime::whereIn('downtime_id', $down)->delete();
            MntLog::where(fn ($q) => $q->where('doc_type', 'WO')->whereIn('doc_id', $wos->pluck('id_wo')))
                ->orWhere(fn ($q) => $q->where('doc_type', 'REQUEST')->whereIn('doc_id', $this->requests))
                ->orWhere(fn ($q) => $q->where('doc_type', 'PLAN')->whereIn('doc_id', $this->plans))->delete();
            MntWo::whereIn('id_wo', $wos->pluck('id_wo'))->delete();
            MntRequest::whereIn('id_request', $this->requests)->delete();
            MntPlan::whereIn('id_plan', $this->plans)->delete();
            ProdMachine::whereKey($this->machine->machine_id)->update(['machine_status' => $this->statusBefore]);
        }
        parent::tearDown();
    }

    public function test_breakdown_request_becomes_wo_and_drives_downtime_and_machine_status(): void
    {
        $svc = new MaintenanceService('test');
        $req = $svc->createRequest(['machine_id' => $this->machine->machine_id, 'request_kind' => 'BREAKDOWN', 'machine_stopped' => true, 'symptom' => 'TEST blade stuck']);
        $this->requests[] = $req->id_request;

        $this->assertSame('P1', $req->priority);
        $this->assertSame('ACCEPTED', $req->status);
        $wo = $req->wo;
        $this->wos[] = $wo->id_wo;
        $this->assertSame('OPEN', $wo->docStatus());
        $this->assertSame('SCHEDULED', $wo->work_status);
        $this->assertStringStartsWith('MWO-', $wo->doc_no);
        $this->assertNull(ProdMachineDowntime::find($req->downtime_id)->end_at, 'downtime dibuka saat mesin dilaporkan berhenti');

        $svc->start($wo->refresh());
        $this->assertSame('BREAKDOWN', $this->machine->refresh()->machine_status);
        $this->assertSame($req->downtime_id, $wo->refresh()->downtime_id, 'Start memakai downtime yang sudah terbuka, bukan membuat baris kedua');

        $svc->addTask($wo, 'TEST check');
        $wo->tasks()->update(['is_required' => true]);
        try {
            $svc->done($wo->refresh());
            $this->fail('Done harus ditolak selama task wajib belum ada hasil');
        } catch (ValidationException) {
        }
        $wo->tasks()->update(['result' => 'OK']);

        $svc->hold($wo->refresh());
        $this->assertSame('WAITING_PART', $wo->refresh()->work_status);
        $svc->resume($wo);
        $svc->addLabor($wo->refresh(), 114, 90);
        $this->assertGreaterThan(0, $wo->refresh()->cost_labor);

        $svc->done($wo->refresh());
        $this->assertNotNull(ProdMachineDowntime::find($req->downtime_id)->end_at);
        $this->assertSame('READY', $this->machine->refresh()->machine_status);

        $svc->close($wo->refresh());
        $this->assertSame('CLOSED', $wo->refresh()->docStatus());
    }

    public function test_preventive_plan_generates_one_wo_with_checklist_and_moves_due_date_on_close(): void
    {
        $svc = new MaintenanceService('test');
        $plan = MntPlan::create(['plan_code' => 'TEST-PM-'.uniqid(), 'machine_id' => $this->machine->machine_id, 'plan_name' => 'TEST monthly',
            'wo_type' => 'PREVENTIVE', 'trigger_mode' => 'TIME', 'interval_value' => 1, 'interval_unit' => 'MONTH', 'schedule_mode' => 'FLOATING',
            'lead_days' => 3, 'needs_machine_stop' => false, 'last_done_date' => now()->subDays(40)->toDateString(), 'activate' => true]);
        $this->plans[] = $plan->id_plan;
        $plan->tasks()->create(['seq_no' => 1, 'task_text' => 'TEST clean', 'task_kind' => 'CLEAN', 'is_required' => true]);
        $plan->update($svc->nextDue($plan, CarbonImmutable::parse($plan->last_done_date)));
        $this->assertTrue($svc->isDue($plan->refresh()));

        $wo = $svc->generateFromPlan($plan->load(['tasks', 'parts']));
        $this->assertNotNull($wo);
        $this->assertSame('DRAFT', $wo->docStatus());
        $this->assertSame(1, $wo->tasks()->count(), 'checklist plan disalin ke WO');
        $this->assertNull($svc->generateFromPlan($plan), 'plan dengan WO terbuka tidak membuat WO kedua');

        $svc->confirm($wo);
        $svc->start($wo->refresh());
        $wo->tasks()->update(['result' => 'OK']);
        $svc->done($wo->refresh());
        $svc->close($wo->refresh());

        $plan->refresh();
        $this->assertSame(now()->toDateString(), $plan->last_done_date->toDateString());
        $this->assertSame(now()->addMonthNoOverflow()->toDateString(), $plan->next_due_date->toDateString(), 'FLOATING: jatuh tempo dari tanggal selesai');
    }

    public function test_screen_actions_post_through_routes(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $this->post(route('v2.mnt.request.create'), ['machine_id' => $this->machine->machine_id, 'request_kind' => 'CORRECTIVE', 'symptom' => 'TEST noise'])
            ->assertRedirect();
        $req = MntRequest::where('symptom', 'TEST noise')->latest('id_request')->firstOrFail();
        $this->requests[] = $req->id_request;
        $this->assertSame('OPEN', $req->status);

        $this->post(route('v2.mnt.request.save', $req->id_request), ['action' => 'accept', 'technician_id' => 114])->assertRedirect();
        $wo = $req->refresh()->wo;
        $this->assertSame(114, (int) $wo->assigned_to);

        $this->post(route('v2.mnt.wo.save', $wo->id_wo), ['action' => 'start'])->assertRedirect();
        $this->post(route('v2.mnt.wo.save', $wo->id_wo), ['action' => 'save', 'problem_code' => 'NOISE', 'findings' => 'TEST bearing'])->assertRedirect();
        $this->assertSame('NOISE', $wo->refresh()->problem_code);
        $this->assertSame('IN_PROGRESS', $wo->work_status);

        foreach (['maintenance-wo', 'maintenance-request', 'maintenance-plan', 'maintenance-my-work', 'maintenance-codes', 'maintenance-wo-report'] as $slug) {
            $this->get(route('v2.screen', $slug))->assertOk();
        }
        $this->get(route('v2.screen', 'maintenance-wo').'?id='.$wo->id_wo)->assertOk()->assertSee($wo->doc_no);
    }
}
