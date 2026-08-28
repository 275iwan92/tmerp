<?php

namespace Tests\Feature;

use App\Models\Master\ProdProduct;
use App\Models\Master\SupSupplier;
use App\Models\Prd\PrdBreakdownEvent;
use App\Models\Prd\PrdMtsForecast;
use App\Models\Prd\PrdSchedulingRule;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoOperation;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PrRequisition;
use App\Models\Sys\SecUser;
use Livewire\Livewire;
use Tests\TestCase;

// Worklist WO / WO Detail (QC Gate + Breakdown&Subcontract) / Rule Library /
// MTS Forecast Entry -- production_scheduling_tier4.md §1, 4 layar dibangun
// 2026-08-28 (sesi lanjutan Scheduling Board + PDR engine + Interruption
// Dialog). Semua mutasi memulihkan state dummy di akhir test (dipakai ulang
// test lain & demo manual di browser -- pola sama ProductionSchedulingBoardSmokeTest).
class ProductionWorklistWoTest extends TestCase
{
    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function rina(): SecUser
    {
        return SecUser::where('login', 'rina')->firstOrFail();
    }

    public function test_worklist_wo_renders_seeded_data(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::production.wo-list')
            ->assertSee('Total WO')
            ->assertSee('WO2600231');
    }

    public function test_wo_detail_renders_conditional_action_buttons(): void
    {
        $this->actingAs($this->admin());

        $running = PrdWo::where('wo_no', 'WO2600231')->firstOrFail();
        Livewire::test('pages::production.wo-entry', ['wo' => $running->wo_id])
            ->assertSee('Lapor Breakdown');

        $qcHold = PrdWo::where('wo_no', 'WO2600227')->firstOrFail();
        Livewire::test('pages::production.wo-entry', ['wo' => $qcHold->wo_id])
            ->assertSee('Lulus')
            ->assertSee('Tolak');
    }

    public function test_qc_pass_requires_qa_group(): void
    {
        $op = PrdWoOperation::whereHas('wo', fn ($q) => $q->where('wo_no', 'WO2600227'))->firstOrFail();
        $this->assertSame('QC_HOLD', $op->task_status);

        // rina (SALES_STAFF, bukan QA) -- ditolak, status tak berubah.
        $this->actingAs($this->rina());
        Livewire::test('pages::production.wo-entry', ['wo' => $op->wo_id])
            ->call('qcPass', $op->operation_id);
        $op->refresh();
        $this->assertSame('QC_HOLD', $op->task_status);

        // admin (is_admin bypass = QA) -- lolos.
        $this->actingAs($this->admin());
        Livewire::test('pages::production.wo-entry', ['wo' => $op->wo_id])
            ->call('qcPass', $op->operation_id);
        $op->refresh();
        $this->assertSame('QC_PASSED', $op->task_status);

        $op->update(['task_status' => 'QC_HOLD']);
    }

    public function test_qc_reject_then_rework(): void
    {
        $this->actingAs($this->admin());

        $op = PrdWoOperation::whereHas('wo', fn ($q) => $q->where('wo_no', 'WO2600227'))->firstOrFail();

        $component = Livewire::test('pages::production.wo-entry', ['wo' => $op->wo_id])
            ->call('openQcReject', $op->operation_id)
            ->set('qcRejectReason', 'Warna tidak sesuai Delta E toleransi')
            ->call('submitQcReject');

        $op->refresh();
        $this->assertSame('QC_REJECTED', $op->task_status);
        $this->assertNotNull($op->rejection_reason);

        $component->call('reworkFromReject', $op->operation_id);
        $op->refresh();
        $this->assertSame('PENDING', $op->task_status);

        $op->update(['task_status' => 'QC_HOLD', 'rejection_reason' => null]);
    }

    public function test_breakdown_reroute_to_alternate_machine(): void
    {
        $this->actingAs($this->admin());

        $op = PrdWoOperation::whereHas('wo', fn ($q) => $q->where('wo_no', 'WO2600231'))->firstOrFail();
        $originalMachineId = $op->machine_id;
        $this->assertSame('RUNNING', $op->task_status);

        $component = Livewire::test('pages::production.wo-entry', ['wo' => $op->wo_id])
            ->call('openBreakdown', $op->operation_id)
            ->set('breakdownDowntimeMinutes', 90)
            ->call('submitBreakdownReport');

        $op->refresh();
        $this->assertSame('BREAKDOWN', $op->task_status);

        $altMachineId = $component->get('altMachines')->first()->machine_id;
        $component->set('altMachineId', $altMachineId)->call('rerouteToMachine');

        $op->refresh();
        $this->assertSame('PENDING', $op->task_status);
        $this->assertSame($altMachineId, $op->machine_id);

        $event = PrdBreakdownEvent::where('operation_id', $op->operation_id)->latest('event_id')->first();
        $this->assertSame('MACHINE_ALT', $event->reroute_decision);

        $event->delete();
        $op->update(['task_status' => 'RUNNING', 'machine_id' => $originalMachineId]);
    }

    public function test_breakdown_subcontract_requires_ops_manager_and_creates_po(): void
    {
        $op = PrdWoOperation::whereHas('wo', fn ($q) => $q->where('wo_no', 'WO2600231'))->firstOrFail();
        $vendor = SupSupplier::where('is_subcon', true)->where('activate', true)->firstOrFail();

        $this->actingAs($this->admin());
        $component = Livewire::test('pages::production.wo-entry', ['wo' => $op->wo_id])
            ->call('openBreakdown', $op->operation_id)
            ->call('submitBreakdownReport')
            ->set('rerouteChoice', 'SUBCONTRACT')
            ->call('selectVendor', $vendor->id_supplier)
            ->set('subDueOut', now()->addDay()->toDateString())
            ->set('subDueReturn', now()->addDays(3)->toDateString())
            ->set('subEstimatedCost', 1500000);

        $eventId = $component->get('breakdownEventId');

        // rina (bukan Ops Manager) -- ditolak, tak ada PO tercipta.
        $this->actingAs($this->rina());
        $poCountBefore = PoOrder::count();
        $component->call('createSubcontractPo');
        $this->assertSame($poCountBefore, PoOrder::count());
        $op->refresh();
        $this->assertSame('BREAKDOWN', $op->task_status);

        // admin (Ops Manager bypass) -- PO+PR tercipta, task -> SUBCONTRACT.
        $this->actingAs($this->admin());
        $component->call('createSubcontractPo');

        $event = PrdBreakdownEvent::findOrFail($eventId);
        $po = PoOrder::findOrFail($event->subcontract_po_id);
        $this->assertSame('SUBCONTRACT', $po->i_po_type);
        $this->assertSame('SERVICE', $po->lines->first()->i_line_class);

        $op->refresh();
        $this->assertSame('SUBCONTRACT', $op->task_status);

        // Terima dari subkontrak -> wajib QC_HOLD (Tier2 §7 langkah 3).
        Livewire::test('pages::production.wo-entry', ['wo' => $op->wo_id])
            ->call('receiveFromSubcontract', $op->operation_id);
        $op->refresh();
        $this->assertSame('QC_HOLD', $op->task_status);

        // cleanup
        $pr = PrRequisition::whereHas('lines', fn ($q) => $q->where('i_description', 'like', '%'.$op->process_name.'%'))
            ->latest('pr_id')->first();
        $po->lines->each->delete();
        $po->delete();
        if ($pr) {
            $pr->lines()->delete();
            $pr->delete();
        }
        $event->delete();
        $op->update(['task_status' => 'RUNNING']);
    }

    public function test_rule_library_activation_requires_prod_manager(): void
    {
        $this->actingAs($this->rina());

        $component = Livewire::test('pages::production.rule-library')
            ->call('openNew')
            ->set('rule_type', 'BATCHING')
            ->set('rule_name', 'TEST Gabung Dieline Sama')
            ->set('condition_field', 'dieline_ref')
            ->call('save');

        $rule = PrdSchedulingRule::where('rule_name', 'TEST Gabung Dieline Sama')->firstOrFail();
        $this->assertSame('DRAFT', $rule->rule_status);

        $component->call('activate', $rule->rule_id);
        $rule->refresh();
        $this->assertSame('DRAFT', $rule->rule_status);

        $this->actingAs($this->admin());
        Livewire::test('pages::production.rule-library')->call('activate', $rule->rule_id);
        $rule->refresh();
        $this->assertSame('ACTIVE', $rule->rule_status);

        $rule->delete();
    }

    public function test_mts_forecast_entry_computes_accuracy(): void
    {
        $this->actingAs($this->admin());

        $product = ProdProduct::firstOrFail();

        Livewire::test('pages::production.mts-forecast')
            ->call('openNew')
            ->call('selectProduct', $product->id_product)
            ->set('periodStart', '2026-09-01')
            ->set('periodEnd', '2026-09-07')
            ->set('forecastQty', 1000)
            ->call('save');

        $forecast = PrdMtsForecast::where('product_id', $product->id_product)
            ->where('period_start', '2026-09-01')->latest('forecast_id')->firstOrFail();
        $this->assertNull($forecast->accuracy_pct);

        Livewire::test('pages::production.mts-forecast')
            ->call('openActualEntry', $forecast->forecast_id)
            ->set('actualQty', 900)
            ->call('saveActual');

        $forecast->refresh();
        $this->assertEqualsWithDelta(0.9, (float) $forecast->accuracy_pct, 0.0001);

        $forecast->delete();
    }
}
