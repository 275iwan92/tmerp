<?php

namespace Tests\Feature;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\InvQcDecision;
use App\Models\Inventory\InvQcFirstArticle;
use App\Models\Inventory\InvQcHold;
use App\Models\Inventory\InvSupplierClaim;
use App\Services\Inventory\BatchService;
use App\Services\Inventory\QcDecisionService;
use App\Services\Inventory\QcTraceService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

// Keputusan QC atas batch (Q-05..Q-09): status batch, first article, telusur maju saat Rejected (WO ditahan, klaim supplier), umur keputusan.
// Jalankan lewat tools/test-ddt.ps1. Memakai item nyata mat_raw (hanya dibaca) dan WO nyata (hanya dirujuk); semua baris uji dibersihkan di tearDown().
class QcDecisionTest extends TestCase
{
    private const BY = 'test_qc_decision';

    private int $woId;

    private int $materialId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
        $this->woId = (int) DB::connection('tm2prd')->table('prd_wo')->orderBy('wo_id')->value('wo_id');
        $this->materialId = (int) DB::connection('tm2mst')->table('mat_raw')->where('activate', 1)->orderBy('material_id')->value('material_id');
        $this->assertGreaterThan(0, $this->woId, 'Butuh minimal satu WO di database uji.');
    }

    protected function tearDown(): void
    {
        $this->clean();
        foreach (['inv_batch' => 'created_by', 'inv_supplier_claim' => 'created_by'] as $table => $col) {
            $this->assertSame(0, DB::connection('tm2inv')->table($table)->where($col, self::BY)->count(), "{$table} masih berisi baris uji.");
        }
        $this->assertSame(0, DB::connection('tm2inv')->table('rm_stock')->where('create_by', self::BY)->count(), 'rm_stock masih berisi baris uji.');
        $this->assertSame(0, InvQcDecision::query()->where('decided_by', self::BY)->count());
        parent::tearDown();
    }

    private function clean(): void
    {
        $inv = DB::connection('tm2inv');
        $batchIds = InvBatch::query()->where('created_by', self::BY)->pluck('id_batch');
        InvQcHold::query()->whereIn('batch_id', $batchIds)->delete();
        InvQcFirstArticle::query()->whereIn('batch_id', $batchIds)->delete();
        InvQcDecision::query()->where('decided_by', self::BY)->delete();
        InvSupplierClaim::query()->where('created_by', self::BY)->delete();
        $docIds = $inv->table('rm_stock')->where('create_by', self::BY)->pluck('doc_id');
        $inv->table('rm_stock_d')->whereIn('doc_id', $docIds)->delete();
        $inv->table('rm_stock')->where('create_by', self::BY)->delete();
        InvBatch::query()->where('created_by', self::BY)->delete();
    }

    private function batch(string $lot, string $status = 'CONDITIONAL'): InvBatch
    {
        return BatchService::create('RM', $this->materialId, $lot, ['qc_status' => $status, 'supplier_id' => 999999821], self::BY);
    }

    // Dokumen keluar bahan ke WO uji: satu baris bertanda batch, satu baris lama hanya dengan lot_no yang sama.
    private function issue(InvBatch $batch, bool $tagged): void
    {
        $inv = DB::connection('tm2inv');
        $docId = $inv->table('rm_stock')->insertGetId(['doc_no' => 'TQC'.random_int(100000, 999999), 'doc_date' => now(), 'doc_type' => -1, 'doc_cat' => 'STOCK', 'flow_status' => 'POSTED',
            'stat_confirm' => 1, 'stat_cancel' => 0, 'wo_id' => $this->woId, 'wo_no' => 'WO-TEST', 'ref_doc_no' => 'WO-TEST', 'create_by' => self::BY]);
        $inv->table('rm_stock_d')->insert(['doc_id' => $docId, 'material_id' => $this->materialId, 'location_id' => 1, 'qty' => 12.5, 'uom_code' => 'KG', 'lot_no' => $batch->supplier_lot,
            'batch_id' => $tagged ? $batch->id_batch : null]);
    }

    public function test_rejection_needs_a_reason_and_each_decision_sets_the_batch_status(): void
    {
        $batch = $this->batch('QC-A');

        try {
            QcDecisionService::record($batch, 'SAMPLING_DOCK', 'REJECTED', self::BY, ['reason' => 'bad']);
            $this->fail('A rejection without a real reason must be refused.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('CONDITIONAL', $batch->fresh()->qc_status);
        }

        $released = QcDecisionService::record($batch, 'USAGE_DECISION', 'RELEASED', self::BY, ['test_result' => 'gsm 250.4 / moisture 7.1%']);
        $this->assertSame(['CONDITIONAL', 'RELEASED'], [$released->status_before, $released->status_after]);
        $this->assertSame('RELEASED', $batch->fresh()->qc_status);

        $blocked = QcDecisionService::record($batch, 'DEVIATION', 'BLOCKED', self::BY, ['reason' => 'Waiting for the supplier certificate']);
        $this->assertSame('BLOCKED', $batch->fresh()->qc_status);
        $this->assertFalse($batch->fresh()->isAllocatable());
        $this->assertSame(0, InvSupplierClaim::query()->where('batch_id', $batch->id_batch)->count(), 'Only a rejection opens a supplier claim, a block does not.');

        $conditional = QcDecisionService::record($batch, 'USAGE_DECISION', 'CONDITIONAL_USE', self::BY, ['reason' => 'Use for the non critical job']);
        $this->assertSame('CONDITIONAL', $batch->fresh()->qc_status);
        $this->assertSame(3, InvQcDecision::query()->where('batch_id', $batch->id_batch)->count());
        $this->assertNotNull($blocked->decided_at);
        $this->assertNotNull($conditional->decided_at);
    }

    public function test_reject_traces_forward_holds_the_work_orders_and_opens_one_claim_then_release_clears_the_holds(): void
    {
        $batch = $this->batch('QC-B');
        $this->issue($batch, true);
        $this->issue($batch, false);

        $uses = QcTraceService::forward($batch);
        $this->assertCount(2, $uses, 'The tagged line and the old line matched by item + lot are both found.');
        $this->assertSame([$this->woId], array_values(array_unique(array_column($uses, 'wo_id'))));

        QcDecisionService::record($batch, 'USAGE_DECISION', 'REJECTED', self::BY, ['reason' => 'Colour far off the standard']);
        $this->assertSame('REJECTED', $batch->fresh()->qc_status);
        $hold = InvQcHold::query()->where('batch_id', $batch->id_batch)->get();
        $this->assertCount(1, $hold, 'One hold per work order, not per issue line.');
        $this->assertSame('HELD', $hold->first()->status);
        $this->assertEqualsWithDelta(25.0, (float) $hold->first()->qty_issued, 0.0001);

        // Ditolak lagi tidak menggandakan klaim terbuka.
        QcDecisionService::record($batch, 'DEVIATION', 'BLOCKED', self::BY, ['reason' => 'Still blocked, waiting for the supplier']);
        $this->assertSame(1, InvSupplierClaim::query()->where('batch_id', $batch->id_batch)->whereIn('status', ['OPEN', 'SENT'])->count());

        QcDecisionService::record($batch, 'USAGE_DECISION', 'RELEASED', self::BY, ['reason' => 'Supplier replacement tested']);
        $this->assertSame('CLEARED', InvQcHold::query()->where('batch_id', $batch->id_batch)->value('status'));
    }

    public function test_first_article_pass_releases_and_fail_rejects_the_batch(): void
    {
        $pass = $this->batch('QC-C');
        $row = QcDecisionService::firstArticle($this->woId, $pass, 'PASS', self::BY, 'Colour and register ok');
        $this->assertSame('RELEASED', $pass->fresh()->qc_status);
        $this->assertSame('FIRST_ARTICLE', InvQcDecision::query()->find($row->decision_id)->decision_type);

        $fail = $this->batch('QC-D');
        try {
            QcDecisionService::firstArticle($this->woId, $fail, 'FAIL', self::BY, '');
            $this->fail('A failed first article needs a note.');
        } catch (InvalidArgumentException) {
            $this->assertSame('CONDITIONAL', $fail->fresh()->qc_status);
        }
        QcDecisionService::firstArticle($this->woId, $fail, 'FAIL', self::BY, 'Sheet curls on the press');
        $this->assertSame('REJECTED', $fail->fresh()->qc_status);
        $this->assertSame(2, InvQcFirstArticle::query()->whereIn('batch_id', [$pass->id_batch, $fail->id_batch])->count());
    }

    public function test_decision_age_escalates_and_goes_overdue_by_the_settings(): void
    {
        $batch = $this->batch('QC-E');
        $this->assertSame('OK', QcDecisionService::age($batch->fresh())['state']);

        $batch->forceFill(['qc_status_at' => now()->subHours(5)])->save();
        $this->assertSame('ESCALATE', QcDecisionService::age($batch->fresh())['state']);

        $batch->forceFill(['qc_status_at' => now()->subHours(9)])->save();
        $age = QcDecisionService::age($batch->fresh());
        $this->assertSame('OVERDUE', $age['state']);
        $this->assertEqualsWithDelta(9.0, $age['hours'], 0.2);
    }
}
