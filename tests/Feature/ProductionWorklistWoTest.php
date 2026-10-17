<?php

/*
 * REMARK
 * Tujuan       : Uji Worklist WO, WO Detail (QC gate, Breakdown dan Subkontrak) dan Production Output.
 * Sumber hulu  : Fixture dummy WO2600231 di tm2*.
 * Input        : Tidak ada.
 * Output       : Lulus atau gagal; state dummy dipulihkan di tearDown.
 * Dipakai oleh : php artisan test (database tm2*, fixture WO2600225-262).
 * Status       : Aktif. Uji Rule Library dan papan v1 dihapus 2026-10-18.
 */

namespace Tests\Feature;

use App\Models\Master\SupSupplier;
use App\Models\Prd\PrdBreakdownEvent;
use App\Models\Prd\PrdScrapType;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoOperation;
use App\Models\Prd\PrdWoOperationOutput;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PrRequisition;
use App\Models\Sys\SecUser;
use Livewire\Livewire;
use Tests\TestCase;

// Worklist WO / WO Detail (QC Gate + Breakdown&Subcontract) / Production Output. Semua mutasi memulihkan
// state dummy di akhir test (dipakai ulang test lain dan demo manual di browser).
class ProductionWorklistWoTest extends TestCase
{
    private ?array $wo231Snapshot = null;

    // Test Breakdown yang gagal di tengah dulu meninggalkan task WO2600231 di BREAKDOWN +
    // event yatim (28 baris, 2026-09-22..30) sehingga test lain ikut gagal. Snapshot di
    // setUp, pulihkan di tearDown apa pun hasil test-nya.
    protected function setUp(): void
    {
        parent::setUp();
        $op = PrdWoOperation::whereHas('wo', fn ($q) => $q->where('wo_no', 'WO2600231'))->first();
        $this->wo231Snapshot = $op ? [
            'id' => $op->operation_id, 'task_status' => $op->task_status, 'machine_id' => $op->machine_id,
            'max_event' => (int) PrdBreakdownEvent::max('event_id'),
        ] : null;
    }

    protected function tearDown(): void
    {
        if ($snap = $this->wo231Snapshot) {
            PrdBreakdownEvent::where('operation_id', $snap['id'])->where('event_id', '>', $snap['max_event'])->delete();
            PrdWoOperation::whereKey($snap['id'])->update(['task_status' => $snap['task_status'], 'machine_id' => $snap['machine_id']]);
        }

        parent::tearDown();
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

    // F15 Production Output Recording: total good / reject / waste menumpuk per operasi.
    public function test_output_entry_accumulates(): void
    {
        $this->actingAs($this->admin());

        $op = PrdWoOperation::whereHas('wo', fn ($q) => $q->where('wo_no', 'WO2600231'))->firstOrFail();
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

        $op = PrdWoOperation::whereHas('wo', fn ($q) => $q->where('wo_no', 'WO2600231'))->firstOrFail();
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
