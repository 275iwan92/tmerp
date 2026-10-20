<?php

namespace Tests\Feature;

use App\Models\Master\MatRaw;
use App\Models\Master\ProdMachine;
use App\Models\Master\SupSupplier;
use App\Models\Prd\PrdBreakdownEvent;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoMaterialSubstitution;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PrRequisition;
use App\Models\Sys\SecUser;
use App\Services\Production\WoBreakdownService;
use App\Services\Production\WoMaterialSubstitutionService;
use App\Services\Production\WoQcGateService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\WoFixture;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Fitur eksekusi Work Order di layar v2 production-wo: gerbang QC (pass / reject / rework / terima dari subkontrak), breakdown (mesin alternatif, PO subkontrak) dan substitusi bahan (pratinjau, ajukan, approve, reject, undo), beserta larangan peran dan rute POST.
 * Sumber hulu  : WoQcGateService, WoBreakdownService, WoMaterialSubstitutionService, rute v2.production.wo.*; fixture WO sendiri (WoFixture).
 * Input        : Panggilan service dan POST sebagai admin (QA / Ops Manager lewat is_admin) dan rina (SALES_STAFF, tanpa hak).
 * Output       : Status task, event breakdown, PO subkontrak, baris substitusi; semua fixture dan PO / PR subkon dihapus di tearDown.
 * Dipakai oleh : php artisan test. Menggantikan tes v1 QC / breakdown / subkontrak di ProductionWorklistWoTest.
 * Status       : Aktif (T9 pindahan v1, 2026-10-20).
 */
class WoExecutionServiceTest extends TestCase
{
    use WoFixture;

    private array $layoutParts = [];

    private array $cleanup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWoFixture();
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $fn) {
            $fn();
        }
        if ($this->layoutParts) {
            DB::connection('tm2sdi')->table('iqp_layout')->whereIn('part_id', $this->layoutParts)->delete();
        }
        $this->tearDownWoFixture();
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

    public function test_qc_pass_needs_qa_and_reject_needs_a_reason_then_rework(): void
    {
        $svc = app(WoQcGateService::class);
        $op = $this->seedWo('XQC', [['Cetak', 'QC_HOLD', 800]])->operations()->firstOrFail();

        try {
            $svc->pass($op->operation_id, $this->rina());
            $this->fail('rina bukan QA');
        } catch (AuthorizationException) {
            $this->assertSame('QC_HOLD', $op->fresh()->task_status);
        }

        try {
            $svc->reject($op->operation_id, 'abc', $this->admin());
            $this->fail('alasan terlalu pendek');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }

        $svc->reject($op->operation_id, 'Warna tidak sesuai Delta E toleransi', $this->admin());
        $this->assertSame('QC_REJECTED', $op->fresh()->task_status);
        $this->assertNotNull($op->fresh()->rejection_reason);

        $svc->rework($op->operation_id);
        $this->assertSame('PENDING', $op->fresh()->task_status);

        // transisi tidak sah ditolak sebagai galat layar, bukan 500
        try {
            $svc->pass($op->operation_id, $this->admin());
            $this->fail('PENDING tidak bisa langsung QC_PASSED');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }

        DB::connection('tm2prd')->table('prd_wo_operation')->where('operation_id', $op->operation_id)->update(['task_status' => 'QC_HOLD']);
        $svc->pass($op->operation_id, $this->admin());
        $this->assertSame('QC_PASSED', $op->fresh()->task_status);
    }

    public function test_breakdown_report_then_reroute_to_an_alternative_machine(): void
    {
        $svc = app(WoBreakdownService::class);
        $op = $this->seedWo('XBD', [['Cetak', 'RUNNING', 800]])->operations()->firstOrFail();
        $original = $op->machine_id;

        try {
            $svc->report($op->operation_id, 0, $this->admin());
            $this->fail('downtime 0');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('downtime', $e->errors());
        }

        $event = $svc->report($op->operation_id, 90, $this->admin());
        $this->assertSame('BREAKDOWN', $op->fresh()->task_status);
        $this->assertSame($event->event_id, $svc->openEvent($op->operation_id)->event_id);

        $alt = ProdMachine::where('division_id', ProdMachine::find($original)->division_id)->where('activate', true)->where('machine_id', '!=', $original)->firstOrFail();
        $svc->rerouteToMachine($op->operation_id, $alt->machine_id, $this->admin());
        $this->assertSame('PENDING', $op->fresh()->task_status);
        $this->assertSame($alt->machine_id, (int) $op->fresh()->machine_id);
        $this->assertSame('MACHINE_ALT', $event->fresh()->reroute_decision);
        $this->assertNull($svc->openEvent($op->operation_id));

        try {
            $svc->rerouteToMachine($op->operation_id, $alt->machine_id, $this->admin());
            $this->fail('tidak ada breakdown terbuka');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }
    }

    public function test_subcontract_po_needs_an_ops_manager_and_receiving_goes_to_qc(): void
    {
        $svc = app(WoBreakdownService::class);
        $op = $this->seedWo('XSC', [['Cetak', 'RUNNING', 800]])->operations()->firstOrFail();
        $vendor = SupSupplier::where('is_subcon', true)->where('activate', true)->firstOrFail();
        $event = $svc->report($op->operation_id, 60, $this->admin());
        $this->cleanup[] = fn () => $event->delete();

        $before = PoOrder::count();
        try {
            $svc->createSubcontractPo($op->operation_id, $vendor->id_supplier, 1500000, now()->addDay()->toDateString(), now()->addDays(3)->toDateString(), $this->rina());
            $this->fail('rina bukan Ops Manager');
        } catch (AuthorizationException) {
            $this->assertSame($before, PoOrder::count());
            $this->assertSame('BREAKDOWN', $op->fresh()->task_status);
        }
        try {
            $svc->createSubcontractPo($op->operation_id, $vendor->id_supplier, 0, '', '', $this->admin());
            $this->fail('data belum lengkap');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }

        $po = $svc->createSubcontractPo($op->operation_id, $vendor->id_supplier, 1500000, now()->addDay()->toDateString(), now()->addDays(3)->toDateString(), $this->admin());
        $this->cleanup[] = function () use ($po, $op) {
            $pr = PrRequisition::whereHas('lines', fn ($q) => $q->where('i_description', 'like', '%'.$op->process_name.'%'))->latest('pr_id')->first();
            $po->lines->each->delete();
            $po->delete();
            if ($pr) {
                $pr->lines()->delete();
                $pr->delete();
            }
        };
        $this->assertSame('SUBCONTRACT', $po->i_po_type);
        $this->assertSame('SUBCONTRACT', $op->fresh()->task_status);

        app(WoQcGateService::class)->receiveFromSubcontract($op->operation_id);
        $this->assertSame('QC_HOLD', $op->fresh()->task_status);
    }

    private function confirmedLayout(PrdWo $wo): void
    {
        $this->layoutParts[] = (int) $wo->source_iqp_part_id;
        DB::connection('tm2sdi')->table('iqp_layout')->insert(['part_id' => $wo->source_iqp_part_id, 'flat_w_mm' => 200, 'flat_h_mm' => 300, 'gap_mm' => 3, 'plano_w_mm' => 720, 'plano_h_mm' => 1020,
            'gripper_mm' => 12, 'margin_mm' => 8, 'method' => 'TEST', 'up_count' => 2, 'efficiency_pct' => 70, 'waste_pct' => 30, 'stat_confirm' => 1, 'created_by' => 'test']);
    }

    public function test_material_substitution_flow_with_preview_approval_and_undo(): void
    {
        $svc = app(WoMaterialSubstitutionService::class);
        $wo = $this->seedWo('XSUB');
        $line = $svc->lines($wo)->firstOrFail()->id_part_material;
        $raw = MatRaw::where('activate', true)->firstOrFail();

        $this->assertArrayHasKey('error', $svc->preview($wo, $line, $raw->material_id, 720, 1020), 'tanpa layout confirmed tidak bisa menghitung UP');
        try {
            $svc->submit($wo, $line, $raw->material_id, 720, 1020, null, $this->admin());
            $this->fail('layout belum confirmed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('size', $e->errors());
        }

        $this->confirmedLayout($wo);
        $p = $svc->preview($wo, $line, $raw->material_id, 720, 1020);
        $this->assertGreaterThan(0, $p['up_count']);
        $this->assertArrayNotHasKey('error', $p);
        $this->assertArrayHasKey('error', $svc->preview($wo, $line, $raw->material_id, 10, 10), 'plano terlalu kecil');

        $sub = $svc->submit($wo, $line, $raw->material_id, 720, 1020, '  stok habis ', $this->admin());
        $this->assertSame('PENDING', $sub->approval_status);
        $this->assertFalse((bool) $sub->is_active);
        $this->assertSame('stok habis', $sub->reason);
        $this->assertSame($sub->id, $svc->rows($wo)->first()->pending->id);

        try {
            $svc->submit($wo, $line, $raw->material_id, 720, 1020, null, $this->admin());
            $this->fail('masih ada pengajuan PENDING');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }
        try {
            $svc->approve($sub->id, $this->rina());
            $this->fail('rina bukan Ops Manager');
        } catch (AuthorizationException) {
            $this->assertSame('PENDING', $sub->fresh()->approval_status);
        }

        $svc->approve($sub->id, $this->admin());
        $this->assertTrue((bool) $sub->fresh()->is_active);
        $row = $svc->rows($wo)->first();
        $this->assertTrue($row->substituted);
        $this->assertSame($raw->material_name, $row->name);
        try {
            $svc->approve($sub->id, $this->admin());
            $this->fail('hanya PENDING yang bisa di-approve');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // pengajuan kedua: approve menonaktifkan yang aktif (satu aktif per baris BOM per WO)
        $second = $svc->submit($wo, $line, $raw->material_id, 720, 1020, null, $this->admin());
        try {
            $svc->reject($second->id, 'no', $this->admin());
            $this->fail('alasan tolak terlalu pendek');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }
        $svc->approve($second->id, $this->admin());
        $this->assertFalse((bool) $sub->fresh()->is_active);
        $this->assertTrue((bool) $second->fresh()->is_active);

        $svc->undo($second->id, $this->admin());
        $this->assertFalse($svc->rows($wo)->first()->substituted);
        try {
            $svc->undo($second->id, $this->admin());
            $this->fail('sudah tidak aktif');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $third = $svc->submit($wo, $line, $raw->material_id, 720, 1020, null, $this->admin());
        $svc->reject($third->id, 'Ukuran tidak cocok untuk mesin', $this->admin());
        $this->assertSame('REJECTED', $third->fresh()->approval_status);
        $this->assertSame(3, PrdWoMaterialSubstitution::where('wo_id', $wo->wo_id)->count());
    }

    public function test_v2_screen_shows_actions_by_status_and_posts_run_the_services(): void
    {
        $this->actingAs($this->admin());
        $wo = $this->seedWo('XSCR', [['Cetak', 'RUNNING', 800], ['Laminasi', 'QC_HOLD', 800]]);
        [$running, $hold] = $wo->operations()->orderBy('seq_no')->get()->all();

        // Kartu Execution dan kartu substitusi dirender sendiri: layar penuh production-wo butuh WO lengkap dari ProDev (dieline, plan), fixture tidak membuatnya.
        $src = \App\Support\V2\Src::of('tm2prd.prd_wo');
        $ops = fn (array $rows) => array_map(fn ($o, $st) => ['op_id' => $o->operation_id, 'task_status' => $st, 'seq' => $o->seq_no, 'process' => $o->process_name, 'machine' => 'OFF-02', 'machine_id' => $o->machine_id], $rows[0], $rows[1]);
        $card = fn (array $rows) => view('v2.partials.wo-execution', ['wo' => (object) ['wo_id' => $wo->wo_id], 'ops' => $ops($rows), 'src' => $src])->render();
        $html = $card([[$running, $hold], ['RUNNING', 'QC_HOLD']]);
        $this->assertStringContainsString('data-dl-open="wo-bd-'.$running->operation_id.'"', $html);
        $this->assertStringContainsString('data-dl-open="wo-qc-'.$hold->operation_id.'"', $html);
        $this->assertStringContainsString(route('v2.production.wo.op.qc-pass', ['wo' => $wo->wo_id, 'op' => $hold->operation_id]), $html);
        $bom = view('v2.partials.wo-substitution', ['wo' => (object) ['wo_id' => $wo->wo_id], 'src' => $src])->render();
        $this->assertStringContainsString('data-dl-open="wo-sub-', $bom);

        $back = route('v2.screen', 'production-wo').'?id='.$wo->wo_id.'&tab=bop';
        $this->post(route('v2.production.wo.op.qc-pass', ['wo' => $wo->wo_id, 'op' => $hold->operation_id]))->assertRedirect($back);
        $this->assertSame('QC_PASSED', $hold->fresh()->task_status);

        $this->post(route('v2.production.wo.op.breakdown', ['wo' => $wo->wo_id, 'op' => $running->operation_id]), ['downtime' => 45])->assertRedirect($back);
        $this->assertSame('BREAKDOWN', $running->fresh()->task_status);
        $html = $card([[$running], ['BREAKDOWN']]);
        $this->assertStringContainsString('data-dl-open="wo-rr-'.$running->operation_id.'"', $html);
        $this->assertStringContainsString('data-dl-open="wo-sc-'.$running->operation_id.'"', $html);
        $this->cleanup[] = fn () => PrdBreakdownEvent::where('operation_id', $running->operation_id)->delete();

        // peran tanpa hak: kembali ke layar dengan galat, status tak berubah
        $this->actingAs($this->rina());
        DB::connection('tm2prd')->table('prd_wo_operation')->where('operation_id', $hold->operation_id)->update(['task_status' => 'QC_HOLD']);
        $this->post(route('v2.production.wo.op.qc-pass', ['wo' => $wo->wo_id, 'op' => $hold->operation_id]))->assertRedirect($back);
        $this->assertSame('QC_HOLD', $hold->fresh()->task_status);

        // operasi milik WO lain tidak bisa disentuh lewat rute ini
        $other = $this->seedWo('XSCR2', [['Cetak', 'QC_HOLD', 100]])->operations()->firstOrFail();
        $this->actingAs($this->admin());
        $this->post(route('v2.production.wo.op.qc-pass', ['wo' => $wo->wo_id, 'op' => $other->operation_id]))->assertNotFound();
    }
}
