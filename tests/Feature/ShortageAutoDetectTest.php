<?php

namespace Tests\Feature;

use App\Models\Inventory\InvShortageRequest;
use App\Models\Pur\DnDebitNote;
use App\Models\Pur\PrRequisition;
use App\Services\Inventory\QcAuthority;
use App\Services\Inventory\QcNotifier;
use App\Services\Pur\ShortageAutoDetector;
use App\Services\Pur\ShortageRequestService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Kurang kirim otomatis (Q-13), pemberitahuan, PR tambahan dan draft debit note (Q-14, Q-15). Memakai WO uji sendiri (selesai, output 60 dari 100)
// dengan kebutuhan bahan 10 yang seluruhnya dikeluarkan: dugaan kurang = 10 - 6 = 4. Semua jejak (WO, PR, debit note, pemberitahuan) dibersihkan di tearDown().
class ShortageAutoDetectTest extends ShortageTestCase
{
    private int $autoWoId;

    protected function setUp(): void
    {
        parent::setUp();
        $prd = DB::connection('tm2prd');
        $this->autoWoId = $this->woId = (int) $prd->table('prd_wo')->insertGetId(['wo_no' => 'WO-TSA'.random_int(1000, 9999), 'wo_type' => 'MTO', 'source_type' => 'CO_CONFIRMED', 'product_id' => (int) $prd->table('prd_wo')->value('product_id'), 'qty' => 100, 'due_date' => now()->addWeek(),
            'wo_status' => 'COMPLETED', 'created_by' => self::BY]);
        $prd->table('prd_wo_material')->insert(['wo_id' => $this->autoWoId, 'seq_no' => 10, 'material_id' => $this->material->material_id, 'qty_required' => 10, 'qty_standar' => 10,
            'uom' => $this->material->uom_stock ?: 'PCS', 'material_role' => 'TEST', 'created_by' => self::BY]);
        foreach ([10 => 'CUT', 20 => 'PACK'] as $seq => $name) {
            $id = $prd->table('prd_wo_operation')->insertGetId(['wo_id' => $this->autoWoId, 'seq_no' => $seq, 'process_name' => $name, 'resource_type' => 'MANUAL', 'qty' => 100, 'task_status' => 'DONE', 'created_by' => self::BY]);
            if ($seq === 20) {
                $prd->table('prd_wo_operation_output')->insert(['operation_id' => $id, 'qty_good' => 60, 'recorded_by' => self::BY, 'recorded_at' => now()]);
            }
        }
    }

    protected function tearDown(): void
    {
        $prd = DB::connection('tm2prd');
        $opIds = $prd->table('prd_wo_operation')->where('wo_id', $this->autoWoId)->pluck('operation_id');
        $prd->table('prd_wo_operation_output')->whereIn('operation_id', $opIds)->delete();
        $prd->table('prd_wo_operation')->where('wo_id', $this->autoWoId)->delete();
        $prd->table('prd_wo_material')->where('wo_id', $this->autoWoId)->delete();
        $prdLeft = $prd->table('prd_wo_operation')->where('wo_id', $this->autoWoId)->count() + $prd->table('prd_wo_material')->where('wo_id', $this->autoWoId)->count();
        // PR, debit note dan pemberitahuan dari permintaan uji dibersihkan parent (lewat id / nomor permintaan).
        $before = DB::connection('tm2pur')->table('pr_requisition')->count();
        parent::tearDown();
        $prd->table('prd_wo')->where('wo_id', $this->autoWoId)->delete();
        $this->assertSame(0, $prdLeft + $prd->table('prd_wo')->where('wo_id', $this->autoWoId)->count(), 'Jejak WO uji masih ada.');
        $this->assertLessThanOrEqual($before, DB::connection('tm2pur')->table('pr_requisition')->count(), 'PR uji masih ada.');
        $this->assertSame(0, DB::connection('tm2pur')->table('dn_debit_note')->where('created_by', 'johiwan')->where('i_reason', 'like', 'Shortage SR%WO WO-TSR')->count(), 'Debit note uji masih ada.');
    }

    public function test_production_output_is_converted_and_a_draft_request_is_made_with_a_notice_and_no_duplicate(): void
    {
        $line = $this->postedReceiptIssuedToWo();

        $dry = Artisan::call('inv:detect-shortage', ['--wo' => $this->autoWoId, '--dry-run' => true]);
        $this->assertSame(0, $dry);
        $this->assertSame(0, InvShortageRequest::query()->where('wo_id', $this->autoWoId)->count(), 'A dry run writes nothing.');
        $this->assertSame('WOULD_CREATE', ShortageAutoDetector::scan($this->autoWoId, true)[0]['state']);

        $rows = ShortageAutoDetector::scan($this->autoWoId, false, self::BY);
        $this->assertSame(['CREATED', 4.0], [$rows[0]['state'], $rows[0]['candidate']]);
        $req = InvShortageRequest::query()->where('wo_id', $this->autoWoId)->firstOrFail();
        $this->assertSame(['DRAFT', 'AUTO', 'SUPPLIER', $line->pv_line_id], [$req->status, $req->detected_source, $req->source_type, (int) $req->pv_line_id]);
        $this->assertEqualsWithDelta(6.0, (float) $req->qty_output_good, 0.0001, 'qty_required 10 x (60 good / 100 planned).');
        $this->assertEqualsWithDelta(4.0, (float) $req->qty_candidate, 0.0001);

        $recipients = QcNotifier::recipients(QcAuthority::RECOUNT);
        $this->assertNotEmpty($recipients, 'The warehouse head group has a user in the test database.');
        $this->assertSame(count($recipients), DB::connection('tm2sys')->table('sys_notifications')->where('message', 'like', $req->request_no.' %')->count(), 'Each head of warehouse gets one recount notice.');

        $again = ShortageAutoDetector::scan($this->autoWoId, false, self::BY);
        $this->assertSame('SKIPPED', $again[0]['state']);
        $this->assertSame(1, InvShortageRequest::query()->where('wo_id', $this->autoWoId)->count(), 'No second request for the same work order and material.');
    }

    public function test_pairs_that_cannot_be_judged_are_skipped_with_the_reason_and_small_gaps_are_not_requests(): void
    {
        $this->postedReceiptIssuedToWo();
        $prd = DB::connection('tm2prd');
        $opId = $prd->table('prd_wo_operation')->where('wo_id', $this->autoWoId)->where('seq_no', 20)->value('operation_id');
        $state = fn () => ShortageAutoDetector::scan($this->autoWoId, true)[0];

        $prd->table('prd_wo_operation_output')->where('operation_id', $opId)->update(['qty_good' => 95]);
        $this->assertSame(['WITHIN_TOLERANCE', 0.5], [$state()['state'], $state()['candidate']], '10 - 9.5 = 0.5 is inside 5% of 10.');
        $prd->table('prd_wo_operation_output')->where('operation_id', $opId)->update(['qty_good' => 100]);
        $this->assertSame('COVERED', $state()['state']);

        $prd->table('prd_wo_operation_output')->where('operation_id', $opId)->delete();
        $this->assertStringContainsString('no output', $state()['detail']);
        $prd->table('prd_wo_operation_output')->insert(['operation_id' => $opId, 'qty_good' => 60, 'recorded_by' => self::BY, 'recorded_at' => now()]);

        $prd->table('prd_wo')->where('wo_id', $this->autoWoId)->update(['wo_status' => 'IN_PROGRESS']);
        $prd->table('prd_wo_operation')->where('operation_id', $opId)->update(['task_status' => 'RUNNING']);
        $this->assertStringContainsString('not finished', $state()['detail']);
        $prd->table('prd_wo_operation')->where('operation_id', $opId)->update(['task_status' => 'DONE']);

        $prd->table('prd_wo_material')->where('wo_id', $this->autoWoId)->update(['uom' => 'XXLONG']);
        $this->assertStringContainsString('unit differs', $state()['detail']);
        $prd->table('prd_wo_material')->where('wo_id', $this->autoWoId)->update(['uom' => $this->material->uom_stock ?: 'PCS', 'qty_required' => 0]);
        $this->assertStringContainsString('is 0', $state()['detail']);
    }

    public function test_confirm_makes_an_additional_draft_purchase_request_and_a_refusal_makes_a_draft_debit_note(): void
    {
        $line = $this->postedReceiptIssuedToWo();
        DB::connection('tm2pur')->table('po_line')->where('po_line_id', $line->po_line_id)->update(['i_department_id' => DB::connection('tm2mst')->table('org_department')->value('department_id'),
            'i_coa_account_id' => DB::connection('tm2acc')->table('fin_account')->value('account_id')]);
        ShortageAutoDetector::scan($this->autoWoId, false, self::BY);
        $req = InvShortageRequest::query()->where('wo_id', $this->autoWoId)->firstOrFail();

        ShortageRequestService::recount($req, 6, null, 4, 'Counted again on the pallet, 4 short', 'johiwan');
        $this->assertGreaterThan(0, DB::connection('tm2sys')->table('sys_notifications')->where('title', 'Shortage to confirm')->where('message', 'like', $req->request_no.' %')->count(), 'PPIC is told to confirm.');
        $confirmed = ShortageRequestService::confirm($req->fresh(), 'johiwan');
        $this->assertNotNull($confirmed->pr_id);
        $pr = PrRequisition::query()->with('lines')->findOrFail($confirmed->pr_id);
        $this->assertSame(['DRAFT', 1], [$pr->status, $pr->lines->count()]);
        $this->assertEqualsWithDelta(4.0, (float) $pr->lines->first()->i_qty, 0.0001);
        $this->assertSame((int) $this->material->material_id, (int) $pr->lines->first()->item_id);
        $this->assertGreaterThan(0, DB::connection('tm2sys')->table('sys_notifications')->where('title', 'Shortage to follow up')->where('message', 'like', $req->request_no.' %')->count(), 'Purchasing is told to follow up.');

        $sent = ShortageRequestService::send($confirmed->fresh(), 'johiwan');
        $refused = ShortageRequestService::refuse($sent->fresh(), 'Supplier refuses', 'johiwan');
        $dn = DnDebitNote::query()->findOrFail($refused->debit_note_id);
        $this->assertSame(['DRAFT', $refused->supplier_id], [$dn->status, (int) $dn->i_supplier_id]);
        $this->assertEqualsWithDelta(4.0, (float) $dn->i_qty, 0.0001);
        $this->assertEqualsWithDelta(4000.0, (float) $dn->c_amount, 0.01, '4 units x the receipt line price of 1000.');
        $this->assertMatchesRegularExpression('/^DN\d{7}$/', $dn->doc_no);
    }
}
