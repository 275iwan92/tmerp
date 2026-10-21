<?php

namespace Tests\Feature;

use App\Models\Master\FlowConfig;
use App\Models\Master\FlowConfigStep;
use App\Models\Master\FlowLog;
use App\Models\Master\OrgDepartment;
use App\Models\Pur\PoLine;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PrLine;
use App\Models\Pur\PrLineAlloc;
use App\Models\Pur\PrRequisition;
use App\Models\Sys\SecUser;
use App\Services\Pur\PrAllocationService;
use App\Support\Finance\TaxRate;
use App\Support\V2\Data\PurchaseOrder;
use App\Support\V2\Data\PurchaseRequisition;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/*
 * REMARK
 * Tujuan       : Mengunci (karakterisasi) aturan entri Purchase Order lewat App\Support\V2\Data\PurchaseOrder: ubah header, tarik alokasi PR, ubah harga / diskon baris dan total pajak, submit ke flow, keputusan approver bertingkat, Kirim ke Supplier, hapus draft (alokasi dilepas). Dijalankan melawan komponen v1 lalu melawan service hasil pemindahan: hasilnya harus sama.
 * Sumber hulu  : PoOrder, PoLine, PrLineAlloc, PrRequisition, FlowLog, konfigurasi flow modul PO yang aktif, TaxRate.
 * Input        : PO dan PR uji buatan sendiri (admin johiwan); approver = pemegang posisi step flow PO.
 * Output       : Baris po_order / po_line / pr_requisition / pr_line / pr_line_alloc / flow_log; semuanya dihapus di tearDown.
 * Dipakai oleh : php artisan test --filter=PurchaseOrderEntryRulesTest.
 * Status       : Aktif (T10 tahap 7a, 2026-10-22).
 */
class PurchaseOrderEntryRulesTest extends TestCase
{
    /** @var list<int> */
    private array $poIds = [];

    /** @var list<int> */
    private array $prIds = [];

    protected function tearDown(): void
    {
        FlowLog::whereIn('module_code', ['PO', 'POC'])->whereIn('doc_id', $this->poIds)->delete();
        PoOrder::whereIn('po_id', $this->poIds)->delete();
        $lineIds = PrLine::whereIn('pr_id', $this->prIds)->pluck('pr_line_id');
        PrLineAlloc::whereIn('pr_line_id', $lineIds)->delete();
        FlowLog::where('module_code', 'PR')->whereIn('doc_id', $this->prIds)->delete();
        PrRequisition::whereIn('pr_id', $this->prIds)->delete();
        $this->assertSame(0, PoOrder::whereIn('po_id', $this->poIds)->count());
        $this->assertSame(0, PrRequisition::whereIn('pr_id', $this->prIds)->count());
        $this->assertSame(0, PoLine::whereIn('po_id', $this->poIds)->count());

        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('is_admin', true)->firstOrFail();
    }

    private function supplierId(): int
    {
        return (int) DB::connection('tm2mst')->table('sup_supplier')->where('activate', 1)->where('supplier_name', '<>', PurchaseOrder::CASH_SUPPLIER)->orderBy('id_supplier')->value('id_supplier');
    }

    private function draftPo(): PoOrder
    {
        $this->actingAs($this->admin());
        $po = PoOrder::create(['i_doc_date' => now()->toDateString(), 'i_supplier_id' => $this->supplierId(), 'i_po_type' => 'LOCAL', 'i_procure_type' => 'REPEAT',
            'i_fx_rate' => 1, 'status' => 'DRAFT', 'created_by' => $this->admin()->login, 'updated_by' => $this->admin()->login]);
        $this->poIds[] = $po->po_id;

        return $po;
    }

    /** Alokasi PR APPROVED siap ditarik ke PO (baris teks bebas, tanpa harga paper engine). */
    private function allocation(float $qty = 4, float $price = 2500): PrLineAlloc
    {
        $this->actingAs($this->admin());
        $pr = PurchaseRequisition::create((int) OrgDepartment::where('activate', true)->value('department_id'));
        $this->prIds[] = $pr->pr_id;
        $account = (int) DB::connection('tm2acc')->table('fin_account')->where('is_postable', 1)->orderBy('account_code')->value('account_id');
        PurchaseRequisition::addFree($pr, 'Uji aturan PO', 'JOB', $account);
        $line = PrLine::where('pr_id', $pr->pr_id)->sole();
        PurchaseRequisition::save($pr, ['lines' => [$line->pr_line_id => ['i_qty' => $qty, 'i_est_price' => $price]]]);
        PrRequisition::whereKey($pr->pr_id)->update(['status' => 'APPROVED']);
        PrAllocationService::createDefaultAllocationsForPr($pr->refresh()->load('lines'));

        return PrLineAlloc::where('pr_line_id', $line->pr_line_id)->sole();
    }

    private function poWithLine(float $qty = 4, float $price = 2500): array
    {
        $po = $this->draftPo();
        $alloc = $this->allocation($qty, $price);
        PurchaseOrder::addAllocation($po, $alloc->alloc_id);

        return [$po->refresh(), PoLine::where('po_id', $po->po_id)->sole(), $alloc->refresh()];
    }

    /** @return list<SecUser> approver tiap step APPROVAL flow PO (urutan step) */
    private function approvers(): array
    {
        $config = FlowConfig::where('module_code', 'PO')->where('activate', true)->firstOrFail();
        $out = [];
        foreach ($config->steps()->where('step_type', 'APPROVAL')->where(fn ($q) => $q->whereNull('min_amount')->orWhere('min_amount', '<=', 100000))->orderBy('step_no')->get() as $step) {
            $holder = SecUser::all()->first(fn (SecUser $u) => $u->currentPositionId() == $step->position_id);
            if (! $holder) {
                $this->markTestSkipped('Posisi approver PO tidak punya pemegang di database ini.');
            }
            $out[] = $holder;
        }

        return $out;
    }

    public function test_header_fields_are_saved_and_empty_values_become_null_only_in_draft(): void
    {
        $po = $this->draftPo();

        PurchaseOrder::save($po, ['i_notes' => 'catatan PO', 'i_payment_term' => '30 hari', 'i_delivery_date' => '2026-12-10', 'i_ship_to' => 'Gudang A']);
        $po->refresh();
        $this->assertSame('catatan PO', $po->i_notes);
        $this->assertSame('30 hari', $po->i_payment_term);
        $this->assertSame('2026-12-10', $po->i_delivery_date->toDateString());
        $this->assertSame('Gudang A', $po->i_ship_to);
        $this->assertSame($this->admin()->login, $po->updated_by);

        PurchaseOrder::save($po, ['i_notes' => '', 'i_delivery_date' => '']);
        $po->refresh();
        $this->assertNull($po->i_notes);
        $this->assertNull($po->i_delivery_date);

        PoOrder::whereKey($po->po_id)->update(['status' => 'SUBMITTED']);
        try {
            PurchaseOrder::save($po->refresh(), ['i_notes' => 'tidak boleh']);
            $this->fail('PO non-draft tidak boleh diubah.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_pulling_an_allocation_creates_the_line_and_marks_the_pr_line_ordered(): void
    {
        [$po, $line, $alloc] = $this->poWithLine(4, 2500);

        $this->assertSame((int) $alloc->alloc_id, (int) $line->alloc_id);
        $this->assertSame(1, (int) $line->line_no);
        $this->assertEquals(4, $line->i_qty);
        $this->assertEquals(2500, $line->i_price);
        $this->assertEquals(0, $line->i_disc_pct);
        $this->assertSame('INDIRECT', $line->i_line_class);
        $this->assertTrue((bool) $line->is_stock, 'kelas INDIRECT tetap ditandai stok oleh PoBuilderService');
        $this->assertEquals(10000, $line->c_line_net);
        $this->assertTrue((bool) $alloc->c_is_converted);
        $this->assertSame((int) $po->i_supplier_id, (int) $alloc->i_supplier_id);
        $prLine = PrLine::findOrFail($alloc->pr_line_id);
        $this->assertEquals(4, $prLine->c_qty_ordered);
        $this->assertSame('FULLY_ORDERED', $prLine->c_line_status);

        $ppn = round(10000 * TaxRate::ppn($po->i_doc_date) / 100, 4);
        $this->assertEquals(10000, $po->c_dpp);
        $this->assertEquals($ppn, $po->c_ppn);
        $this->assertEquals(10000 + $ppn, $po->c_grand_total);

        PurchaseOrder::addAllocation($po, $alloc->alloc_id);
        $this->assertSame(1, PoLine::where('po_id', $po->po_id)->count(), 'alokasi yang sama tidak ditarik dua kali');

        PoOrder::whereKey($po->po_id)->update(['status' => 'SUBMITTED']);
        PurchaseOrder::addAllocation($po->refresh(), $this->allocation()->alloc_id);
        $this->assertSame(1, PoLine::where('po_id', $po->po_id)->count(), 'PO non-draft tidak menerima baris');
    }

    public function test_line_price_and_discount_recalculate_the_net_and_the_tax_totals(): void
    {
        [$po, $line] = $this->poWithLine(4, 2500);

        PurchaseOrder::save($po, ['lines' => [$line->po_line_id => ['i_price' => '3000', 'i_disc_pct' => '10']]]);

        $line->refresh();
        $po->refresh();
        $this->assertEquals(3000, $line->i_price);
        $this->assertEquals(10, $line->i_disc_pct);
        $this->assertEquals(10800, $line->c_line_net);
        $this->assertSame($this->admin()->login, $line->updated_by);
        $ppn = round(10800 * TaxRate::ppn($po->i_doc_date) / 100, 4);
        $this->assertEquals(10800, $po->c_dpp);
        $this->assertEquals($ppn, $po->c_ppn);
        $this->assertEquals(10800 + $ppn, $po->c_grand_total);
        $this->assertEquals(10800 + $ppn, $po->c_net_payable);

        PurchaseOrder::save($po, ['lines' => [$line->po_line_id => ['i_price' => '', 'i_disc_pct' => '']]]);
        $line->refresh();
        $this->assertEquals(0, $line->i_price);
        $this->assertEquals(0, $line->i_disc_pct);
        $this->assertEquals(0, $line->c_line_net);
        $this->assertEquals(0, $po->refresh()->c_dpp);
    }

    public function test_submit_needs_a_line_then_numbers_the_document_and_enters_the_flow(): void
    {
        $po = $this->draftPo();
        $this->assertSame('', PurchaseOrder::submit($po));
        $po->refresh();
        $this->assertSame('DRAFT', $po->status);
        $this->assertNull($po->doc_no);

        [$po] = $this->poWithLine(4, 2500);
        $this->assertSame('', PurchaseOrder::submit($po));
        $po->refresh();
        $this->assertSame('SUBMITTED', $po->status);
        $this->assertMatchesRegularExpression('/^PO'.now()->format('y').'\d{5}$/', (string) $po->doc_no);
        $log = FlowLog::where('module_code', 'PO')->where('doc_id', $po->po_id)->sole();
        $this->assertSame('SUBMITTED', $log->action);
        $this->assertEquals((float) $po->c_grand_total, (float) $log->amount_snapshot);

        $this->assertSame('', PurchaseOrder::submit($po));
        $this->assertSame(1, FlowLog::where('module_code', 'PO')->where('doc_id', $po->po_id)->count(), 'PO yang sudah SUBMITTED tidak diproses ulang');
    }

    public function test_approval_goes_through_every_level_then_the_po_can_be_sent(): void
    {
        $approvers = $this->approvers();
        [$po] = $this->poWithLine(4, 2500);
        PurchaseOrder::submit($po);
        $this->assertSame('', PurchaseOrder::send($po) ?? '');
        $this->assertSame('SUBMITTED', $po->refresh()->status, 'PO belum APPROVED tidak bisa dikirim');

        // Bukan approver: tidak terjadi apa-apa.
        $this->assertSame('', PurchaseOrder::decide($po, 'approve', ''));
        $this->assertSame(1, FlowLog::where('module_code', 'PO')->where('doc_id', $po->po_id)->count());

        $this->actingAs($approvers[0]);
        $this->assertNotSame('', PurchaseOrder::decide($po, 'reject', ' '));
        $this->assertNotSame('', PurchaseOrder::decide($po, 'hold', ''));
        $this->assertSame('', PurchaseOrder::decide($po, 'hold', 'cek budget'));
        $this->assertSame('cek budget', FlowLog::where('module_code', 'PO')->where('doc_id', $po->po_id)->where('action', 'HOLD')->sole()->notes);
        $this->assertSame('SUBMITTED', $po->refresh()->status);

        foreach ($approvers as $i => $approver) {
            $this->actingAs($approver);
            $this->assertSame('', PurchaseOrder::decide($po, 'approve', ''));
            $po->refresh();
            $this->assertSame($i === count($approvers) - 1 ? 'APPROVED' : 'SUBMITTED', $po->status, 'level '.($i + 1));
        }
        $this->assertSame(count($approvers), FlowLog::where('module_code', 'PO')->where('doc_id', $po->po_id)->where('action', 'APPROVED')->count());

        PurchaseOrder::send($po);
        $po->refresh();
        $this->assertSame('SENT', $po->status);
        $this->assertNotNull($po->sent_at);
        $this->assertSame($approvers[count($approvers) - 1]->login, $po->updated_by);
    }

    public function test_rejection_by_the_first_approver_sets_the_reject_status(): void
    {
        $approvers = $this->approvers();
        [$po] = $this->poWithLine(4, 2500);
        PurchaseOrder::submit($po);

        $this->actingAs($approvers[0]);
        $this->assertSame('', PurchaseOrder::decide($po, 'reject', 'harga terlalu tinggi'));

        $log = FlowLog::where('module_code', 'PO')->where('doc_id', $po->po_id)->where('action', 'REJECTED')->sole();
        $step = FlowConfigStep::findOrFail($log->flow_step_id);
        $expected = FlowConfig::whereKey($step->flow_config_id)->value('reject_status') ?: $step->status_to_reject;
        $this->assertSame($expected, $po->refresh()->status);
        $this->assertSame('harga terlalu tinggi', $log->notes);
    }

    public function test_deleting_a_draft_releases_the_allocation_and_refuses_other_statuses(): void
    {
        [$po, , $alloc] = $this->poWithLine(4, 2500);
        $this->assertTrue((bool) $alloc->c_is_converted);

        PurchaseOrder::delete($po);

        $this->assertSame(0, PoOrder::where('po_id', $po->po_id)->count());
        $this->assertSame(0, PoLine::where('po_id', $po->po_id)->count());
        $alloc->refresh();
        $this->assertFalse((bool) $alloc->c_is_converted);
        $this->assertSame('FULLY_ORDERED', PrLine::findOrFail($alloc->pr_line_id)->c_line_status, 'status baris PR tidak dikembalikan oleh hapus');

        $sent = $this->draftPo();
        PoOrder::whereKey($sent->po_id)->update(['status' => 'SUBMITTED']);
        try {
            PurchaseOrder::delete($sent->refresh());
            $this->fail('Hanya DRAFT yang boleh dihapus.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertSame(1, PoOrder::where('po_id', $sent->po_id)->count());
    }

    public function test_v2_routes_pull_confirm_and_delete_a_po(): void
    {
        $this->actingAs($this->admin());
        $this->withoutMiddleware(PreventRequestForgery::class);
        $alloc = $this->allocation(2, 1000);

        $this->post(route('v2.po.create'), ['supplier_id' => $this->supplierId()])->assertRedirect();
        $po = PoOrder::where('created_by', $this->admin()->login)->orderByDesc('po_id')->firstOrFail();
        $this->poIds[] = $po->po_id;
        $this->assertSame('DRAFT', $po->status);

        $this->post(route('v2.po.save', $po->po_id), ['action' => 'confirm'])->assertSessionHasErrors('confirm');
        $this->post(route('v2.po.save', $po->po_id), ['action' => 'add_alloc', 'alloc_id' => $alloc->alloc_id])->assertSessionHasNoErrors();
        $this->assertSame(1, PoLine::where('po_id', $po->po_id)->count());
        $this->post(route('v2.po.save', $po->po_id), ['action' => 'confirm'])->assertSessionHasNoErrors();
        $this->assertSame('SUBMITTED', $po->refresh()->status);

        $draft = $this->draftPo();
        $this->post(route('v2.po.save', $draft->po_id), ['action' => 'delete'])->assertRedirect();
        $this->assertSame(0, PoOrder::where('po_id', $draft->po_id)->count());
    }
}
