<?php

/*
 * REMARK
 * Tujuan       : Uji Worklist WO, WO Detail (QC gate, Breakdown dan Subkontrak) dan Production Output.
 * Sumber hulu  : WO fixture yang dibangun tes ini sendiri (WoFixture); master asli (mesin OFF-02, vendor subkon, scrap type) hanya dibaca.
 * Input        : Tidak ada.
 * Output       : Lulus atau gagal; fixture dan PO/PR subkon yang lahir dari tes dihapus di tearDown.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif. Menggantikan fixture lama WO2600225-262 (aturan B.17, 2026-10-18).
 */

namespace Tests\Feature;

use App\Models\Master\SupSupplier;
use App\Models\Prd\PrdBreakdownEvent;
use App\Models\Prd\PrdScrapType;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoOperationOutput;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PrRequisition;
use App\Models\Sys\SecUser;
use Livewire\Livewire;
use Tests\Support\WoFixture;
use Tests\TestCase;

// Worklist WO / WO Detail (QC Gate + Breakdown&Subcontract) / Production Output. Dua WO fixture: satu RUNNING (800 pcs),
// satu QC_HOLD; keduanya dihapus di tearDown apa pun hasil tesnya.
class ProductionWorklistWoTest extends TestCase
{
    use WoFixture;

    private ?PrdWo $running = null;

    private ?PrdWo $qcHold = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWoFixture();
    }

    protected function tearDown(): void
    {
        $this->tearDownWoFixture();
        parent::tearDown();
    }

    private function runningWo(): PrdWo
    {
        return $this->running ??= $this->seedWo('WLRUN', [['Cetak', 'RUNNING', 800]]);
    }

    private function qcHoldWo(): PrdWo
    {
        return $this->qcHold ??= $this->seedWo('WLQC', [['Cetak', 'QC_HOLD', 800]]);
    }

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
        $woNo = $this->runningWo()->wo_no;

        Livewire::test('pages::production.wo-list')
            ->assertSee('Total WO')
            ->assertSee($woNo);
    }

    public function test_wo_detail_renders_conditional_action_buttons(): void
    {
        $this->actingAs($this->admin());

        $running = $this->runningWo();
        Livewire::test('pages::production.wo-entry', ['wo' => $running->wo_id])
            ->assertSee('Lapor Breakdown');

        $qcHold = $this->qcHoldWo();
        Livewire::test('pages::production.wo-entry', ['wo' => $qcHold->wo_id])
            ->assertSee('Lulus')
            ->assertSee('Tolak');
    }

    public function test_qc_pass_requires_qa_group(): void
    {
        $op = $this->qcHoldWo()->operations()->firstOrFail();
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

        $op = $this->qcHoldWo()->operations()->firstOrFail();

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

        $op = $this->runningWo()->operations()->firstOrFail();
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
        $op = $this->runningWo()->operations()->firstOrFail();
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

    // F15 Production Output Recording: total good / reject / waste menumpuk per operasi.
    public function test_output_entry_accumulates(): void
    {
        $this->actingAs($this->admin());

        $op = $this->runningWo()->operations()->firstOrFail();
        $this->assertSame('800.0000', $op->qty);
        $printDefect = PrdScrapType::where('type_code', 'PRINT_DEFECT')->firstOrFail();
        $makeready = PrdScrapType::where('type_code', 'MAKEREADY')->firstOrFail();

        Livewire::test('pages::production.wo-entry', ['wo' => $op->wo_id])
            ->call('openOutputEntry', $op->operation_id)
            ->set('outputQtyGood', 400)
            ->call('addDefectRow', 'REJECT')
            ->set('outputDefectRows.0.scrap_type_id', $printDefect->type_id)
            ->set('outputDefectRows.0.qty', 5)
            ->call('saveOutputEntry')
            ->call('openOutputEntry', $op->operation_id)
            ->set('outputQtyGood', 200)
            ->call('addDefectRow', 'WASTE')
            ->set('outputDefectRows.0.scrap_type_id', $makeready->type_id)
            ->set('outputDefectRows.0.qty', 3)
            ->call('saveOutputEntry');

        $op->refresh();
        $op->load('outputs.defects.scrapType');
        $this->assertSame(600.0, $op->totalQtyGood());
        $this->assertSame(5.0, $op->totalQtyReject());
        $this->assertSame(3.0, $op->totalQtyWaste());
        $this->assertSame(75, $op->outputProgressPercent());
        $this->assertCount(2, $op->outputs);

        PrdWoOperationOutput::where('operation_id', $op->operation_id)->delete();
    }

    // Permintaan user 2026-08-28: 1 entri output boleh punya BEBERAPA tipe
    // reject/waste sekaligus (bukan cuma 1 tipe per entri) -- form nested.
    public function test_output_entry_supports_multiple_scrap_types_in_one_entry(): void
    {
        $this->actingAs($this->admin());

        $op = $this->runningWo()->operations()->firstOrFail();
        $printDefect = PrdScrapType::where('type_code', 'PRINT_DEFECT')->firstOrFail();
        $colorOff = PrdScrapType::where('type_code', 'COLOR_OFF')->firstOrFail();

        Livewire::test('pages::production.wo-entry', ['wo' => $op->wo_id])
            ->call('openOutputEntry', $op->operation_id)
            ->set('outputQtyGood', 500)
            ->call('addDefectRow', 'REJECT')
            ->set('outputDefectRows.0.scrap_type_id', $printDefect->type_id)
            ->set('outputDefectRows.0.qty', 3)
            ->call('addDefectRow', 'REJECT')
            ->set('outputDefectRows.1.scrap_type_id', $colorOff->type_id)
            ->set('outputDefectRows.1.qty', 2)
            ->call('saveOutputEntry');

        $op->refresh();
        $op->load('outputs.defects.scrapType');
        $this->assertSame(5.0, $op->totalQtyReject());
        $breakdown = $op->defectBreakdown('REJECT');
        $this->assertSame(3.0, $breakdown[$printDefect->type_name]);
        $this->assertSame(2.0, $breakdown[$colorOff->type_name]);

        PrdWoOperationOutput::where('operation_id', $op->operation_id)->delete();
    }
}
