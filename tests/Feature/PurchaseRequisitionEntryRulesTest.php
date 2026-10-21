<?php

namespace Tests\Feature;

use App\Models\Master\FlowConfig;
use App\Models\Master\FlowConfigStep;
use App\Models\Master\FlowLog;
use App\Models\Master\MatCategory;
use App\Models\Master\MatRaw;
use App\Models\Master\OrgDepartment;
use App\Models\Pur\PrLine;
use App\Models\Pur\PrLineAlloc;
use App\Models\Pur\PrRequisition;
use App\Models\Sys\SecUser;
use App\Services\Pur\PaperPriceEngine;
use App\Support\V2\Data\PurchaseRequisition;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/*
 * REMARK
 * Tujuan       : Mengunci (karakterisasi) aturan entri Purchase Requisition lewat App\Support\V2\Data\PurchaseRequisition: ubah header / baris, hapus baris, tambah item master, submit ke flow, keputusan approver (approve / reject / hold). Dijalankan melawan komponen v1 lalu melawan service hasil pemindahan: hasilnya harus sama.
 * Sumber hulu  : PrRequisition, PrLine, PrLineAlloc, FlowLog (tm2pur / tm2sys), konfigurasi flow modul PR yang aktif, PaperPriceEngine.
 * Input        : PR uji buatan sendiri (admin johiwan); keputusan approver oleh pemegang posisi step pertama flow PR.
 * Output       : Baris pr_requisition / pr_line / pr_line_alloc / flow_log; semuanya dihapus di tearDown.
 * Dipakai oleh : php artisan test --filter=PurchaseRequisitionEntryRulesTest.
 * Status       : Aktif (T10 tahap 7a, 2026-10-22).
 */
class PurchaseRequisitionEntryRulesTest extends TestCase
{
    /** @var list<int> */
    private array $prIds = [];

    protected function tearDown(): void
    {
        $lineIds = PrLine::whereIn('pr_id', $this->prIds)->pluck('pr_line_id');
        PrLineAlloc::whereIn('pr_line_id', $lineIds)->delete();
        FlowLog::where('module_code', 'PR')->whereIn('doc_id', $this->prIds)->delete();
        PrRequisition::whereIn('pr_id', $this->prIds)->delete();
        $this->assertSame(0, PrRequisition::whereIn('pr_id', $this->prIds)->count());
        $this->assertSame(0, FlowLog::where('module_code', 'PR')->whereIn('doc_id', $this->prIds)->count());

        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('is_admin', true)->firstOrFail();
    }

    private function draft(): PrRequisition
    {
        $this->actingAs($this->admin());
        $pr = PurchaseRequisition::create((int) OrgDepartment::where('activate', true)->value('department_id'));
        $this->prIds[] = $pr->pr_id;

        return $pr;
    }

    private function account(): int
    {
        return (int) DB::connection('tm2acc')->table('fin_account')->where('is_postable', 1)->orderBy('account_code')->value('account_id');
    }

    private function withLine(float $qty = 2, float $price = 1500): array
    {
        $pr = $this->draft();
        $this->assertSame('', PurchaseRequisition::addFree($pr, 'Uji aturan PR', 'JOB', $this->account()));
        $line = PrLine::where('pr_id', $pr->pr_id)->sole();
        PurchaseRequisition::save($pr, ['lines' => [$line->pr_line_id => ['i_qty' => $qty, 'i_est_price' => $price]]]);

        return [$pr->refresh(), $line->refresh()];
    }

    private function approver(): SecUser
    {
        $positionId = FlowConfigStep::where('flow_config_id', FlowConfig::where('module_code', 'PR')->where('activate', true)->value('flow_config_id'))
            ->where('step_type', 'APPROVAL')->orderBy('step_no')->value('position_id');
        $holder = SecUser::all()->first(fn (SecUser $u) => $u->currentPositionId() == $positionId);
        if (! $holder) {
            $this->markTestSkipped('Posisi approver PR tidak punya pemegang di database ini.');
        }

        return $holder;
    }

    public function test_header_fields_are_saved_and_empty_values_become_null_only_in_draft(): void
    {
        $pr = $this->draft();

        PurchaseRequisition::save($pr, ['i_notes' => 'catatan uji', 'i_need_date' => '2026-12-01']);
        $pr->refresh();
        $this->assertSame('catatan uji', $pr->i_notes);
        $this->assertSame('2026-12-01', $pr->i_need_date->toDateString());
        $this->assertSame($this->admin()->login, $pr->updated_by);

        PurchaseRequisition::save($pr, ['i_notes' => '', 'i_need_date' => '']);
        $pr->refresh();
        $this->assertNull($pr->i_notes);
        $this->assertNull($pr->i_need_date);

        PrRequisition::whereKey($pr->pr_id)->update(['status' => 'SUBMITTED']);
        try {
            PurchaseRequisition::save($pr->refresh(), ['i_notes' => 'tidak boleh']);
            $this->fail('PR non-draft tidak boleh diubah.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertNull($pr->refresh()->i_notes);
    }

    public function test_line_edit_recalculates_the_subtotal_and_the_header_total(): void
    {
        [$pr, $line] = $this->withLine(3, 1500);

        $this->assertEquals(3, $line->i_qty);
        $this->assertEquals(4500, $line->c_est_subtotal);
        $this->assertEquals(4500, $pr->c_est_total);
        $this->assertSame($this->admin()->login, $line->updated_by);

        PurchaseRequisition::save($pr, ['lines' => [$line->pr_line_id => ['i_uom' => 'SET', 'i_notes' => 'ket', 'i_need_date' => '2026-12-05', 'i_est_price' => '2000']]]);
        $line->refresh();
        $this->assertSame('SET', $line->i_uom);
        $this->assertSame('ket', $line->i_notes);
        $this->assertSame('2026-12-05', $line->i_need_date->toDateString());
        $this->assertEquals(6000, $line->c_est_subtotal);
        $this->assertEquals(6000, $pr->refresh()->c_est_total);

        // Kolom NOT NULL yang dikosongkan tidak disimpan NULL: uraian dan satuan tetap.
        PurchaseRequisition::save($pr, ['lines' => [$line->pr_line_id => ['i_description' => '', 'i_uom' => '']]]);
        $line->refresh();
        $this->assertSame('Uji aturan PR', $line->i_description);
        $this->assertSame('SET', $line->i_uom);
    }

    public function test_removed_lines_are_deleted_and_the_total_follows(): void
    {
        [$pr, $line] = $this->withLine(2, 1000);
        $this->assertEquals(2000, $pr->c_est_total);

        PurchaseRequisition::save($pr, ['remove' => [$line->pr_line_id => '1']]);

        $this->assertSame(0, PrLine::where('pr_id', $pr->pr_id)->count());
        $this->assertEquals(0, $pr->refresh()->c_est_total);
    }

    public function test_add_master_item_copies_the_item_and_suggests_the_price(): void
    {
        $pr = $this->draft();
        $m = MatRaw::where('activate', 1)->whereNotNull('category1')->orderBy('material_id')->firstOrFail();
        $prefix1 = MatCategory::level(1)->where('category_name', $m->category1)->first();

        PurchaseRequisition::addMaterial($pr, $m->material_id);

        $line = PrLine::where('pr_id', $pr->pr_id)->sole();
        $this->assertSame('MASTER_ITEM', $line->i_item_source);
        $this->assertSame((int) $m->material_id, (int) $line->item_id);
        $this->assertSame($m->material_code, $line->c_item_code);
        $this->assertSame($m->material_name, $line->i_description);
        $this->assertEquals(1, $line->i_qty);
        $this->assertSame($m->uom_purchase ?: 'PCS', $line->i_uom);
        $expected = app(PaperPriceEngine::class)->priceFor($m, $m->suggest_supplier_id) ?? $m->last_unitprice;
        $this->assertEquals($expected, $line->i_est_price);
        $this->assertEquals($prefix1?->default_coa_account_id, $line->i_coa_account_id);
        $this->assertSame($prefix1?->stock_class !== 'SERVICE', (bool) $line->is_stock);
        $this->assertSame((int) $pr->i_department_id, (int) $line->i_department_id);
        $this->assertSame(1, (int) $line->line_no);
        $this->assertEquals($line->c_est_subtotal ?? 0, $pr->refresh()->c_est_total);

        PrRequisition::whereKey($pr->pr_id)->update(['status' => 'SUBMITTED']);
        PurchaseRequisition::addMaterial($pr->refresh(), $m->material_id);
        $this->assertSame(1, PrLine::where('pr_id', $pr->pr_id)->count(), 'PR non-draft tidak menerima baris baru');
    }

    public function test_submit_needs_a_line_then_numbers_the_document_and_enters_the_flow(): void
    {
        $pr = $this->draft();
        $this->assertSame('', PurchaseRequisition::submit($pr));
        $pr->refresh();
        $this->assertSame('DRAFT', $pr->status);
        $this->assertNull($pr->doc_no);

        [$pr] = $this->withLine(2, 1500);
        $this->assertSame('', PurchaseRequisition::submit($pr));
        $pr->refresh();
        $this->assertSame('SUBMITTED', $pr->status);
        $this->assertMatchesRegularExpression('/^PR'.now()->format('y').'\d{5}$/', (string) $pr->doc_no);
        $log = FlowLog::where('module_code', 'PR')->where('doc_id', $pr->pr_id)->sole();
        $this->assertSame('SUBMITTED', $log->action);
        $this->assertEquals(3000, $log->amount_snapshot);

        $docNo = $pr->doc_no;
        $this->assertSame('', PurchaseRequisition::submit($pr));
        $this->assertSame($docNo, $pr->refresh()->doc_no, 'PR yang sudah SUBMITTED tidak diproses ulang');
        $this->assertSame(1, FlowLog::where('module_code', 'PR')->where('doc_id', $pr->pr_id)->count());
    }

    public function test_only_the_pending_approver_decides_and_notes_are_required(): void
    {
        [$pr] = $this->withLine(2, 1500);
        PurchaseRequisition::submit($pr);

        // Bukan approver: tidak terjadi apa-apa.
        $this->assertSame('', PurchaseRequisition::decide($pr, 'approve', ''));
        $this->assertSame('SUBMITTED', $pr->refresh()->status);
        $this->assertSame(1, FlowLog::where('module_code', 'PR')->where('doc_id', $pr->pr_id)->count());

        $this->actingAs($this->approver());
        $this->assertNotSame('', PurchaseRequisition::decide($pr, 'reject', '   '));
        $this->assertNotSame('', PurchaseRequisition::decide($pr, 'hold', ''));
        $this->assertSame(1, FlowLog::where('module_code', 'PR')->where('doc_id', $pr->pr_id)->count());

        $this->assertSame('', PurchaseRequisition::decide($pr, 'hold', 'tunggu anggaran'));
        $hold = FlowLog::where('module_code', 'PR')->where('doc_id', $pr->pr_id)->where('action', 'HOLD')->sole();
        $this->assertSame('tunggu anggaran', $hold->notes);
        $this->assertSame('SUBMITTED', $pr->refresh()->status);

        $this->assertSame('', PurchaseRequisition::decide($pr, 'reject', 'harga tidak wajar'));
        $step = FlowConfigStep::findOrFail($hold->flow_step_id);
        $expected = FlowConfig::whereKey($step->flow_config_id)->value('reject_status') ?: $step->status_to_reject;
        $this->assertSame($expected, $pr->refresh()->status);
        $this->assertSame('harga tidak wajar', FlowLog::where('module_code', 'PR')->where('doc_id', $pr->pr_id)->where('action', 'REJECTED')->sole()->notes);
        $this->assertSame(0, PrLineAlloc::whereIn('pr_line_id', PrLine::where('pr_id', $pr->pr_id)->pluck('pr_line_id'))->count());
    }

    public function test_final_approval_sets_the_final_status_and_creates_default_allocations(): void
    {
        [$pr, $line] = $this->withLine(2, 1500);
        PurchaseRequisition::submit($pr);

        $this->actingAs($this->approver());
        $this->assertSame('', PurchaseRequisition::decide($pr, 'approve', ''));

        $pr->refresh();
        $first = FlowLog::where('module_code', 'PR')->where('doc_id', $pr->pr_id)->where('action', 'APPROVED')->firstOrFail();
        $expected = FlowConfig::whereKey($first->flow_config_id)->value('final_status') ?: FlowConfigStep::findOrFail($first->flow_step_id)->status_to;
        $this->assertSame($expected, $pr->status);
        if ($pr->status === 'APPROVED') {
            $alloc = PrLineAlloc::where('pr_line_id', $line->pr_line_id)->sole();
            $this->assertEquals(2, $alloc->i_qty);
            $this->assertFalse((bool) $alloc->c_is_converted);
        }
    }

    public function test_v2_save_route_creates_confirms_and_refuses_an_empty_pr(): void
    {
        $this->actingAs($this->admin());
        $this->withoutMiddleware(PreventRequestForgery::class);
        $departmentId = (int) OrgDepartment::where('activate', true)->value('department_id');

        $this->post(route('v2.pr.create'), ['department_id' => $departmentId])->assertRedirect();
        $pr = PrRequisition::where('created_by', $this->admin()->login)->orderByDesc('pr_id')->firstOrFail();
        $this->prIds[] = $pr->pr_id;
        $this->assertSame('DRAFT', $pr->status);

        $this->post(route('v2.pr.save', $pr->pr_id), ['action' => 'confirm'])->assertSessionHasErrors('confirm');
        $this->post(route('v2.pr.save', $pr->pr_id), ['action' => 'add_free', 'free_description' => 'Uji rute', 'free_uom' => 'PCS', 'free_account_id' => $this->account()])->assertSessionHasNoErrors();
        $this->post(route('v2.pr.save', $pr->pr_id), ['action' => 'confirm'])->assertSessionHasNoErrors();
        $this->assertSame('SUBMITTED', $pr->refresh()->status);
    }
}
