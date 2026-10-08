<?php

namespace Tests\Feature;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\InvQcDecision;
use App\Models\Master\MatRaw;
use App\Models\Pur\PoLine;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PvLine;
use App\Models\Sys\SecUser;
use App\Services\Inventory\QcDecisionService;
use App\Services\Pur\PvBuilderService;
use App\Services\Pur\PvPostingService;
use App\Services\Pur\UrgentReceiptService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

// Penerimaan URGENT (Q-03, Q-04, Q-11, D16): baris URGENT tidak ditahan QC mutu PENDING, wajib alasan + WO + penanda berhak,
// FSC / food grade butuh persetujuan, batch lahir CONDITIONAL; jalur biasa tetap menunggu sampling dan batchnya RELEASED.
// Jalankan lewat tools/test-ddt.ps1 (ke ddt*): membuat PO + penerimaan uji, memposting stok sungguhan, lalu membersihkan semuanya
// (termasuk dokumen stok, kartu stok, lapisan, jurnal, batch, keputusan, dan saldo bahan yang berubah).
class QcUrgentReceiptTest extends TestCase
{
    private ?PoOrder $po = null;

    private ?MatRaw $material = null;

    private array $materialBefore = [];

    private int $woId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(SecUser::where('login', 'johiwan')->orWhere('is_admin', true)->firstOrFail());
        $this->woId = (int) DB::connection('tm2prd')->table('prd_wo')->orderBy('wo_id')->value('wo_id');
        $this->material = MatRaw::query()->where('activate', 1)->whereNotNull('uom_stock')->orderBy('material_id')->firstOrFail();
        $this->materialBefore = $this->material->only(['cur_stock', 'avg_unitprice', 'last_unitprice']);
        $this->po = PoOrder::create(['doc_no' => 'PO26TESTQU'.now()->timestamp.random_int(10, 99), 'i_doc_date' => now()->toDateString(),
            'i_supplier_id' => DB::connection('tm2mst')->table('sup_supplier')->value('id_supplier'), 'i_po_type' => 'LOCAL', 'i_procure_type' => 'REPEAT', 'i_currency_id' => 1,
            'status' => 'SENT', 'created_by' => 'test_qc_urgent', 'updated_by' => 'test_qc_urgent']);
    }

    protected function tearDown(): void
    {
        $inv = DB::connection('tm2inv');
        $pur = DB::connection('tm2pur');
        if ($this->po) {
            $pvIds = $pur->table('pv_line')->where('c_po_id', $this->po->po_id)->pluck('pv_id')->unique();
            $docIds = $pur->table('pv_receipt')->whereIn('pv_id', $pvIds)->pluck('rm_stock_doc_id')->filter();
            $batchIds = $pur->table('pv_line')->where('c_po_id', $this->po->po_id)->pluck('i_batch_id')->filter();
            $detailIds = $inv->table('rm_stock_d')->whereIn('doc_id', $docIds)->pluck('doc_detail_id');
            $inv->table('rm_stockcard')->whereIn('reference_detail_id', $detailIds)->delete();
            $inv->table('rm_stock_d')->whereIn('doc_id', $docIds)->delete();
            $inv->table('rm_stock')->whereIn('doc_id', $docIds)->delete();
            InvQcDecision::query()->whereIn('batch_id', $batchIds)->delete();
            InvBatch::query()->whereIn('id_batch', $batchIds)->delete();
            $voucherIds = DB::connection('tm2acc')->table('fin_voucher')->whereIn('source_type', ['PV', 'PV_SHORT'])->whereIn('source_id', $pvIds)->pluck('voucher_id');
            DB::connection('tm2acc')->table('fin_voucher_line')->whereIn('voucher_id', $voucherIds)->delete();
            DB::connection('tm2acc')->table('fin_voucher')->whereIn('voucher_id', $voucherIds)->delete();
            $pur->table('pv_line')->where('c_po_id', $this->po->po_id)->delete();
            $pur->table('pv_receipt')->whereIn('pv_id', $pvIds)->delete();
            $pur->table('po_line')->where('po_id', $this->po->po_id)->delete();
            $pur->table('po_order')->where('po_id', $this->po->po_id)->delete();
        }
        if ($this->material) {
            DB::connection('tm2mst')->table('mat_raw')->where('material_id', $this->material->material_id)->update($this->materialBefore);
        }
        // Database dummy dipakai jalur lain bersamaan: yang diperiksa hanya jejak uji ini.
        $this->assertSame(0, $pur->table('po_order')->where('doc_no', 'like', 'PO26TESTQU%')->count() + $inv->table('rm_stock')->whereIn('doc_id', $docIds ?? [])->count(), 'Jejak uji masih ada.');
        parent::tearDown();
    }

    // Satu penerimaan 10 satuan dari PO uji; QC qty selesai, QC mutu PENDING.
    private function receipt(string $lot, array $line = []): PvLine
    {
        $m = $this->material;
        $poLine = PoLine::create(['po_id' => $this->po->po_id, 'line_no' => (int) PoLine::where('po_id', $this->po->po_id)->max('line_no') + 1, 'item_id' => $m->material_id, 'c_item_code' => $m->material_code,
            'i_description' => $m->material_name, 'i_line_class' => 'RAW', 'i_qty' => 10, 'i_uom' => $m->uom_purchase ?: 'PCS', 'c_uom_stock' => $m->uom_stock ?: 'PCS', 'i_price' => 1000,
            'is_stock' => true, 'c_qty_received' => 0, 'c_qty_outstanding' => 10, 'created_by' => 'test_qc_urgent', 'updated_by' => 'test_qc_urgent']);
        $poLine->recalcNet();
        $poLine->save();
        $pv = PvBuilderService::createFromLines($this->po->i_supplier_id, 'WH-RAW-01', [$poLine->po_line_id]);
        $l = $pv->lines()->firstOrFail();
        $l->update(array_merge(['qc_qty_status' => 'DONE', 'qc_quality_status' => 'PENDING', 'c_qty_accepted' => 0, 'c_qty_rejected' => 0, 'i_lot_no' => $lot, 'updated_by' => 'test_qc_urgent'], $line));
        $pv->update(['status' => 'QC_PENDING']);

        return $l->fresh();
    }

    public function test_a_normal_line_is_still_held_by_a_pending_quality_qc(): void
    {
        $line = $this->receipt('TQU-N1');

        $blockers = PvPostingService::blockers($line->receipt()->first()->fresh());
        $this->assertCount(1, $blockers);
        $this->assertStringContainsString('quality QC', $blockers[0]);
    }

    public function test_marking_urgent_needs_an_authorised_person_a_reason_and_a_work_order(): void
    {
        $line = $this->receipt('TQU-M1');
        $nobody = SecUser::where('is_admin', 0)->get()->first(fn (SecUser $u) => ! $u->isOpsManagerOrAbove() && ! $u->hasGroup('PROD_PLANNING', 'PROD_SPV', 'PROD_MGR'));
        $this->assertNotNull($nobody, 'Butuh satu akun tanpa hak URGENT untuk uji ini.');

        foreach ([[fn () => UrgentReceiptService::mark($line, 'Press waits for the board', $this->woId, $nobody), 'Only PPIC'],
            [fn () => UrgentReceiptService::mark($line, 'no', $this->woId, 'johiwan'), 'reason'],
            [fn () => UrgentReceiptService::mark($line, 'Press waits for the board', 999999999, 'johiwan'), 'work order']] as [$call, $needle]) {
            try {
                $call();
                $this->fail("Marking should have been refused ({$needle}).");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsStringIgnoringCase($needle, $e->getMessage());
            }
        }
        $this->assertFalse((bool) $line->fresh()->is_urgent);
    }

    public function test_urgent_line_posts_with_pending_quality_and_its_batch_is_conditional(): void
    {
        $line = $this->receipt('TQU-U1');
        UrgentReceiptService::mark($line, 'Press 2 is waiting for this board', $this->woId, 'johiwan');
        $pv = $line->receipt()->first()->fresh();

        $this->assertSame([], PvPostingService::blockers($pv), 'An URGENT line is not held by the pending quality QC.');
        $result = PvPostingService::post($pv);
        $this->assertTrue($result['ok'], (string) ($result['message'] ?? ''));

        $line = $line->fresh();
        $this->assertEqualsWithDelta(10.0, (float) $line->c_qty_accepted, 0.0001, 'The whole quantity counts as received for an URGENT line.');
        $this->assertSame('PENDING', $line->qc_quality_status, 'The quality QC stays open: sampling runs in parallel.');
        $batch = InvBatch::findOrFail($line->i_batch_id);
        $this->assertSame('CONDITIONAL', $batch->qc_status);
        $this->assertTrue($batch->isAllocatable(), 'Conditional stock can go straight to production.');
        $this->assertSame(0, InvQcDecision::query()->where('batch_id', $batch->id_batch)->count(), 'No dock decision yet: the sampling decision follows.');

        $stockLine = DB::connection('tm2inv')->table('rm_stock_d')->where('doc_id', $pv->fresh()->rm_stock_doc_id)->first();
        $this->assertSame($batch->id_batch, (int) $stockLine->batch_id, 'The stock line carries the batch.');

        QcDecisionService::record($batch, 'SAMPLING_DOCK', 'RELEASED', 'johiwan', ['lot_qty' => 10, 'test_result' => 'gsm ok']);
        $this->assertSame('RELEASED', $batch->fresh()->qc_status);
    }

    public function test_fsc_urgent_material_needs_an_approval_before_posting(): void
    {
        $line = $this->receipt('TQU-F1');
        UrgentReceiptService::setCertification($line, 'FSC', false, 'johiwan');
        UrgentReceiptService::mark($line->fresh(), 'FSC job for an export customer', $this->woId, 'johiwan');
        $pv = $line->receipt()->first()->fresh();

        $blockers = PvPostingService::blockers($pv);
        $this->assertCount(1, $blockers);
        $this->assertStringContainsString('approve', $blockers[0]);

        $nobody = SecUser::where('is_admin', 0)->get()->first(fn (SecUser $u) => ! $u->isOpsManagerOrAbove() && ! $u->hasGroup('QA_HEAD'));
        try {
            UrgentReceiptService::approve($line->fresh(), $nobody);
            $this->fail('Only the head of QC or an operations manager may approve.');
        } catch (InvalidArgumentException) {
            $this->assertNull($line->fresh()->urgent_approved_by);
        }

        UrgentReceiptService::approve($line->fresh(), 'johiwan', 'Checked the FSC certificate');
        $this->assertSame([], PvPostingService::blockers($pv->fresh()));
        $this->assertTrue(PvPostingService::post($pv->fresh())['ok']);
        $batch = InvBatch::findOrFail($line->fresh()->i_batch_id);
        $this->assertSame(['FSC', 'CONDITIONAL'], [$batch->certification, $batch->qc_status]);
    }

    public function test_changing_the_work_order_withdraws_an_earlier_approval(): void
    {
        $line = $this->receipt('TQU-F2');
        UrgentReceiptService::setCertification($line, 'NONE', true, 'johiwan');
        $other = (int) DB::connection('tm2prd')->table('prd_wo')->where('wo_id', '<>', $this->woId)->value('wo_id');
        UrgentReceiptService::mark($line->fresh(), 'Food grade carton for the dairy job', $this->woId, 'johiwan');
        UrgentReceiptService::approve($line->fresh(), 'johiwan');
        $this->assertNotNull($line->fresh()->urgent_approved_by);

        UrgentReceiptService::mark($line->fresh(), 'Moved to another job', $other, 'johiwan');
        $this->assertNull($line->fresh()->urgent_approved_by, 'A new work order needs a new approval.');
        $this->assertTrue(UrgentReceiptService::needsApproval($line->fresh()), 'Food grade needs approval.');
    }

    public function test_a_normal_posted_line_gets_a_released_batch_and_a_dock_decision(): void
    {
        $line = $this->receipt('TQU-P1', ['qc_quality_status' => 'PASS', 'c_qty_accepted' => 10, 'qc_by' => 'johiwan']);
        $pv = $line->receipt()->first()->fresh();
        $result = PvPostingService::post($pv);
        $this->assertTrue($result['ok'], (string) ($result['message'] ?? ''));

        $batch = InvBatch::findOrFail($line->fresh()->i_batch_id);
        $this->assertSame('RELEASED', $batch->qc_status);
        $decision = InvQcDecision::query()->where('batch_id', $batch->id_batch)->firstOrFail();
        $this->assertSame(['SAMPLING_DOCK', 'RELEASED'], [$decision->decision_type, $decision->decision]);
        $this->assertEqualsWithDelta(10.0, (float) $decision->lot_qty, 0.0001);
    }
}
