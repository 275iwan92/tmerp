<?php

/*
 * REMARK
 * Tujuan       : Menjaga Purchase Return v2 (NRB): kedua sumber (penerimaan QC reject dan stok), qty retur dibatasi sisa reject, harga mengikuti penerimaan atau harga terakhir item, nilai kredit, Submit (nomor NRB + flow PRT), Hold / Reject / Approve lewat service yang dipakai Approval Center, Cancel dengan alasan, Mark Complete, Duplicate, layar, route, grid, redirect v1.
 * Sumber hulu  : App\Services\Pur\PurchaseReturnService, layar pur-return-form, routes/v2_purchase_return.php, ApprovalCenter, PurchaseReturnGrid.
 * Input        : User johiwan (admin); penerimaan uji dibuat di dalam tes; flow config PRT yang aktif. Seluruh uji berjalan di transaksi tm2pur / tm2mst / tm2inv / tm2acc yang di-rollback (hak approver lewat PLT sementara), jadi tidak ada data tersisa.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=PurchaseReturnServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Master\FlowConfig;
use App\Models\Master\FlowPositionActing;
use App\Models\Master\MatRaw;
use App\Models\Pur\PrtLine;
use App\Models\Pur\PrtReturn;
use App\Models\Pur\PvLine;
use App\Models\Pur\PvReceipt;
use App\Models\Sys\SecUser;
use App\Services\Pur\PurchaseReturnService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Data\ApprovalCenter;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseReturnServiceTest extends TestCase
{
    private const CONNECTIONS = ['tm2pur', 'tm2mst', 'tm2inv', 'tm2acc'];

    private int $supplier;

    private PvReceipt $pv;

    private array $pvLines = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::CONNECTIONS as $c) {
            DB::connection($c)->beginTransaction();
        }
        $this->supplier = (int) DB::connection('tm2mst')->table('sup_supplier')->where('activate', true)->orderBy('id_supplier')->value('id_supplier');
        $this->makeReceipt();
    }

    protected function tearDown(): void
    {
        foreach (self::CONNECTIONS as $c) {
            while (DB::connection($c)->transactionLevel() > 0) {
                DB::connection($c)->rollBack();
            }
        }
        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function material(): MatRaw
    {
        return MatRaw::where('activate', true)->where('stat_confirm', true)->orderBy('material_id')->firstOrFail();
    }

    // Penerimaan POSTED dengan dua baris yang ditolak QC: 12 x 25.000 dan 5 x 40.000.
    private function makeReceipt(): void
    {
        $m = $this->material();
        $this->pv = PvReceipt::create(['doc_no' => 'ZZPV/'.uniqid(), 'i_receipt_type' => 'PO', 'i_doc_date' => now()->toDateString(), 'i_supplier_id' => $this->supplier, 'status' => 'POSTED', 'created_by' => 'test', 'updated_by' => 'test']);
        foreach ([[12, 25000], [5, 40000]] as $i => [$rej, $price]) {
            $l = PvLine::create(['pv_id' => $this->pv->pv_id, 'line_no' => $i + 1, 'item_id' => $m->material_id, 'c_item_code' => $m->material_code, 'i_description' => 'ZZ '.$m->material_name.' '.($i + 1), 'i_qty_purchase' => 20, 'i_uom_purchase' => 'KG', 'i_qty_stock' => 20, 'i_uom_stock' => 'KG',
                'c_qty_accepted' => 20 - $rej, 'c_qty_rejected' => $rej, 'c_unit_price' => $price, 'created_by' => 'test', 'updated_by' => 'test']);
            $this->pvLines[] = $l->pv_line_id;
        }
    }

    private function makeApprover(): void
    {
        $pos = FlowConfig::where('module_code', 'PRT')->where('activate', true)->firstOrFail()->steps()->orderBy('step_no')->firstOrFail()->position_id;
        FlowPositionActing::create(['position_id' => $pos, 'acting_user_id' => $this->admin()->getKey(), 'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(), 'reason' => 'ZZTEST', 'activate' => true, 'created_by' => 'test', 'updated_by' => 'test']);
    }

    private function qcHead(array $over = []): array
    {
        return array_merge(['source' => 'QC_REJECT', 'pv_id' => $this->pv->pv_id, 'i_doc_date' => now()->toDateString(), 'i_disposition' => 'CREDIT_NOTE', 'i_reason' => 'ZZ wrong grade', 'i_notes' => 'ZZ chronology'], $over);
    }

    private function stockHead(array $over = []): array
    {
        return array_merge(['source' => 'FROM_STOCK', 'supplier_id' => $this->supplier, 'i_doc_date' => now()->toDateString(), 'i_disposition' => 'CREDIT_NOTE', 'i_reason' => 'ZZ found later'], $over);
    }

    public function test_qc_return_is_limited_to_the_remaining_reject_and_priced_from_the_receipt(): void
    {
        $this->actingAs($this->admin());
        $svc = app(PurchaseReturnService::class);
        $this->assertContains((int) $this->pv->pv_id, array_column($svc->returnableReceipts(), 'id'));

        $id = $svc->save(null, $this->qcHead(), [$this->pvLines[0] => ['qty' => '10', 'i_lot_no' => 'LOT-1'], $this->pvLines[1] => ['qty' => '']], $this->admin());
        $h = PrtReturn::with('lines')->findOrFail($id);
        $this->assertSame('DRAFT', $h->status);
        $this->assertNull($h->doc_no, 'nomor terbit saat Submit');
        $this->assertSame('QC_REJECT', $h->i_source);
        $this->assertSame((int) $this->supplier, (int) $h->i_supplier_id);
        $this->assertSame('ZZ chronology', $h->i_notes, 'Notes tersimpan (kolom baru)');
        $this->assertCount(1, $h->lines, 'baris tanpa qty dilewati');
        $l = $h->lines[0];
        $this->assertSame(10.0, (float) $l->i_qty);
        $this->assertSame(25000.0, (float) $l->c_unit_price, 'harga mengikuti penerimaan');
        $this->assertSame(250000.0, (float) $l->c_line_value);
        $this->assertSame(250000.0, (float) $h->c_credit_value);
        $this->assertSame('LOT-1', $l->i_lot_no);
        $this->assertFalse((bool) $l->is_stock_moved);

        $rows = $svc->rows('QC_REJECT', $h);
        $this->assertSame(10.0, $rows[0]['qty']);
        $this->assertSame('', $rows[1]['qty']);

        // Lebih dari sisa ditolak; dokumen lain hanya boleh mengambil sisanya (12 - 10 = 2).
        foreach (['13', '-1'] as $bad) {
            try {
                $svc->save($id, $this->qcHead(), [$this->pvLines[0] => ['qty' => $bad]], $this->admin());
                $this->fail('harus ditolak: '.$bad);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('lines', $e->errors());
            }
        }
        $second = $svc->save(null, $this->qcHead(), [$this->pvLines[0] => ['qty' => '2'], $this->pvLines[1] => ['qty' => '5']], $this->admin());
        $this->assertSame(2.0, (float) PrtReturn::with('lines')->findOrFail($second)->lines[0]->i_qty);
        try {
            $svc->save(null, $this->qcHead(), [$this->pvLines[0] => ['qty' => '3']], $this->admin());
            $this->fail('sisa 0 tidak boleh diretur lagi');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lines', $e->errors());
        }
        // Dokumen yang dibatalkan melepaskan sisanya lagi.
        $svc->cancel($second, 'ZZ cancelled to free the balance', $this->admin());
        $this->assertSame('CANCELLED', PrtReturn::findOrFail($second)->status);
        $this->assertGreaterThan(0.0, (float) collect($svc->returnableReceipts())->firstWhere('id', (int) $this->pv->pv_id)['balance']);

        // Disposisi Replace goods: nilai kredit 0, tanggal pengganti tersimpan, kolom nota kredit dibersihkan.
        $r = $svc->save($id, $this->qcHead(['i_disposition' => 'REPLACEMENT', 'replacement_due_date' => '2026-11-01', 'cn_supplier_no' => 'CN-1']), [$this->pvLines[0] => ['qty' => '4']], $this->admin());
        $rep = PrtReturn::findOrFail($r);
        $this->assertSame(0.0, (float) $rep->c_credit_value);
        $this->assertSame('2026-11-01', $rep->replacement_due_date->toDateString());
        $this->assertNull($rep->cn_supplier_no);
    }

    public function test_stock_return_defaults_the_price_and_rules_are_refused(): void
    {
        $this->actingAs($this->admin());
        $svc = app(PurchaseReturnService::class);
        $m = $this->material();
        // Harga kosong memakai harga terakhir item; item tanpa harga terakhir menolak harga kosong.
        $last = (float) ($m->last_unitprice ?: 0);
        if ($last <= 0) {
            $m->update(['last_unitprice' => 4000]);
            $last = 4000.0;
        }
        $this->assertSame(0, (int) ($m->fresh()->last_unitprice <= 0));
        $id = $svc->save(null, $this->stockHead(), [[], ['item_id' => $m->material_id, 'qty' => '3', 'price' => '', 'i_lot_no' => ''], ['item_id' => $m->material_id, 'qty' => '2', 'price' => '1.500,5']], $this->admin());
        $h = PrtReturn::with('lines')->findOrFail($id);
        $this->assertSame('FROM_STOCK', $h->i_source);
        $this->assertCount(2, $h->lines);
        $this->assertSame($last, (float) $h->lines[0]->c_unit_price, 'harga kosong = harga terakhir item');
        $this->assertSame(1500.5, (float) $h->lines[1]->c_unit_price);
        $this->assertTrue((bool) $h->lines[0]->is_stock_moved);
        $this->assertEqualsWithDelta(3 * $last + 2 * 1500.5, (float) $h->c_credit_value, 0.01);
        $m->update(['last_unitprice' => 0]);
        try {
            $svc->save(null, $this->stockHead(), [['item_id' => $m->material_id, 'qty' => '1', 'price' => '']], $this->admin());
            $this->fail('harga akhir 0 harus ditolak');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lines', $e->errors());
        }
        $m->update(['last_unitprice' => $last]);

        $cases = [
            'no source' => [['source' => ''], []],
            'unknown receipt' => [$this->qcHead(['pv_id' => 999999999]), []],
            'inactive supplier' => [$this->stockHead(['supplier_id' => 999999999]), [['item_id' => $m->material_id, 'qty' => '1']]],
            'no date' => [$this->stockHead(['i_doc_date' => '']), [['item_id' => $m->material_id, 'qty' => '1']]],
            'bad disposition' => [$this->stockHead(['i_disposition' => 'XX']), [['item_id' => $m->material_id, 'qty' => '1']]],
            'no lines' => [$this->stockHead(), []],
            'no item' => [$this->stockHead(), [['item_id' => '', 'qty' => '1']]],
            'zero qty' => [$this->stockHead(), [['item_id' => $m->material_id, 'qty' => '0']]],
            'negative price' => [$this->stockHead(), [['item_id' => $m->material_id, 'qty' => '1', 'price' => '-5']]],
        ];
        $before = PrtReturn::count();
        foreach ($cases as $name => [$head, $lines]) {
            try {
                $svc->save(null, $head, $lines, $this->admin());
                $this->fail('harus ditolak: '.$name);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors(), $name);
            }
        }
        $this->assertSame($before, PrtReturn::count(), 'penolakan tidak meninggalkan dokumen');
    }

    public function test_submit_decide_complete_cancel_and_duplicate(): void
    {
        $this->actingAs($this->admin());
        $svc = app(PurchaseReturnService::class);
        $id = $svc->save(null, $this->qcHead(['i_reason' => '']), [$this->pvLines[0] => ['qty' => '4']], $this->admin());
        try {
            $svc->submit($id, $this->admin());
            $this->fail('alasan wajib untuk Submit');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }
        $svc->save($id, $this->qcHead(), [$this->pvLines[0] => ['qty' => '4']], $this->admin());

        // Cancel Draft butuh alasan >= 10 karakter.
        try {
            $svc->cancel($id, 'short', $this->admin());
            $this->fail('alasan terlalu pendek');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cancel_reason', $e->errors());
        }

        $this->assertSame('', $svc->submit($id, $this->admin()));
        $h = PrtReturn::findOrFail($id);
        $this->assertMatchesRegularExpression('#^NRB\d{2}\d{5}$#', $h->doc_no);
        $this->assertSame('SUBMITTED', $h->status);
        $this->assertSame(['approval', 'Waiting approval'], $svc->formState($h));
        try {
            $svc->save($id, $this->qcHead(), [$this->pvLines[0] => ['qty' => '1']], $this->admin());
            $this->fail('dokumen yang diajukan tidak boleh diubah');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }

        $this->assertStringContainsString('not waiting for your approval', $svc->decide($id, 'approve', '', $this->admin()));
        $this->makeApprover();
        $this->assertStringContainsString('required to Request Info', $svc->decide($id, 'hold', '', $this->admin()));
        $this->assertSame('', $svc->decide($id, 'hold', 'ZZ please confirm the lot', $this->admin()));
        $this->assertSame(['hold', 'Info requested'], $svc->formState(PrtReturn::findOrFail($id)));
        $this->assertStringContainsString('minimum 10', $svc->decide($id, 'reject', 'short', $this->admin()));
        $this->assertSame('', $svc->decide($id, 'reject', 'Wrong supplier on this return', $this->admin()));
        $rejected = PrtReturn::findOrFail($id);
        $this->assertSame('REJECTED', $rejected->status);
        $this->assertSame(['rejected', 'Rejected'], $svc->formState($rejected));

        // Rejected: salinan Draft baru (sisa reject dilepas karena dokumen ditolak), lalu Submit dan Approve.
        $copy = $svc->duplicate($id, $this->admin());
        $c = PrtReturn::with('lines')->findOrFail($copy);
        $this->assertSame('DRAFT', $c->status);
        $this->assertNull($c->doc_no);
        $this->assertSame(4.0, (float) $c->lines[0]->i_qty);
        $this->assertSame('', $svc->submit($copy, $this->admin()));
        $this->assertSame('', $svc->decide($copy, 'approve', '', $this->admin()));
        $done = PrtReturn::findOrFail($copy);
        $this->assertSame('CONFIRMED', $done->status);
        $this->assertSame(['confirmed', 'Confirmed'], $svc->formState($done));
        $svc->complete($copy, $this->admin());
        $this->assertSame(['closed', 'Completed'], $svc->formState(PrtReturn::findOrFail($copy)));
        try {
            $svc->complete($copy, $this->admin());
            $this->fail('hanya dokumen Confirmed yang bisa diselesaikan');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }
        $hist = $svc->history(PrtReturn::findOrFail($copy));
        $this->assertContains('Approved', array_column($hist, 'action'));
    }

    public function test_approval_center_lists_the_return_with_a_v2_link_and_decides_through_the_service(): void
    {
        $this->actingAs($this->admin());
        $svc = app(PurchaseReturnService::class);
        $id = $svc->save(null, $this->qcHead(), [$this->pvLines[1] => ['qty' => '5']], $this->admin());
        $svc->submit($id, $this->admin());
        $this->makeApprover();

        $doc = collect(ApprovalCenter::docs('prt'))->firstWhere('id', 'PRT:'.$id);
        $this->assertNotNull($doc, 'dokumen muncul di antrian approver');
        $this->assertSame(route('v2.screen', 'pur-return-form').'?id='.$id, $doc['url']);
        $this->assertCount(1, $doc['lines']);
        $this->assertSame('', ApprovalCenter::decide('PRT', $id, 'approve', ''));
        $this->assertSame('CONFIRMED', PrtReturn::findOrFail($id)->status);
    }

    public function test_screens_routes_grid_and_v1_state(): void
    {
        $this->actingAs($this->admin());
        $form = route('v2.screen', 'pur-return-form');
        $this->get(route('v2.screen', 'purchase-return'))->assertOk();
        $this->get($form.'?new=1')->assertOk()->assertSee(route('v2.pur.return.start').'?source=QC_REJECT', false)->assertSee(route('v2.pur.return.start').'?source=FROM_STOCK', false);
        $this->get($form.'?new=1&source=FROM_STOCK')->assertNotFound();
        $this->get($form.'?new=1&source=QC_REJECT&pv_id=999999999')->assertNotFound();
        $this->get($form.'?new=1&source=QC_REJECT&pv_id='.$this->pv->pv_id)->assertOk()->assertSee('pv_line_id', false)->assertSee('QC rejected');
        $this->get($form.'?new=1&source=FROM_STOCK&supplier_id='.$this->supplier)->assertOk()->assertSee('item_id_label', false);

        // Start -> Save -> Submit -> form hanya-baca; Cancel dan Mark Complete lewat route.
        $this->post(route('v2.pur.return.start').'?source=QC_REJECT', ['pv_id' => 999999999])->assertSessionHasErrors('pv_id');
        $this->post(route('v2.pur.return.start').'?source=QC_REJECT', ['pv_id' => $this->pv->pv_id])->assertRedirect($form.'?new=1&source=QC_REJECT&pv_id='.$this->pv->pv_id);
        $this->post(route('v2.pur.return.start').'?source=FROM_STOCK', ['supplier_id' => $this->supplier])->assertRedirect($form.'?new=1&source=FROM_STOCK&supplier_id='.$this->supplier);
        $this->post(route('v2.pur.return.start').'?source=ZZ', [])->assertStatus(422);

        $in = $this->qcHead(['lines' => [$this->pvLines[0] => ['qty' => '3']]]);
        $this->post(route('v2.pur.return.save'), $in)->assertRedirect();
        $h = PrtReturn::where('i_reason', 'ZZ wrong grade')->firstOrFail();
        $open = $form.'?id='.$h->prt_id;
        $this->get($open)->assertOk()->assertSee('value="submit"', false);
        $this->post(route('v2.pur.return.save'), array_merge($in, ['id' => $h->prt_id, 'action' => 'submit']))->assertRedirect($open);
        $this->assertSame('SUBMITTED', $h->fresh()->status);
        $this->get($open)->assertOk()->assertSee($h->fresh()->doc_no)->assertDontSee('value="submit"', false);
        $this->post(route('v2.pur.return.save'), array_merge($in, ['action' => 'save', 'i_doc_date' => '']))->assertSessionHasErrors('i_doc_date');

        $d = app(PurchaseReturnService::class)->save(null, $this->stockHead(), [['item_id' => $this->material()->material_id, 'qty' => '1', 'price' => '1000']], $this->admin());
        $this->post(route('v2.pur.return.cancel', $d), ['cancel_reason' => 'short'])->assertSessionHasErrors('cancel_reason');
        $this->post(route('v2.pur.return.cancel', $d), ['cancel_reason' => 'ZZ cancelled from the dialog'])->assertRedirect(route('v2.screen', 'purchase-return'));
        $this->assertSame('CANCELLED', PrtReturn::findOrFail($d)->status);
        $this->post(route('v2.pur.return.save'), ['id' => $d, 'action' => 'duplicate'])->assertRedirect();

        $grid = Grids::find('purchase-return');
        $this->assertStringContainsString('/v2/s/pur-return-form?id={id}', $grid->openUrl());
        $this->assertStringContainsString('/v2/s/pur-return-form?new=1', $grid->newUrl());
        foreach (['entry', 'index'] as $v1) {
            $this->assertFileDoesNotExist(resource_path('views/pages/pur/prt/'.$v1.'.blade.php'));
            $this->assertArchived('pur_prt_'.$v1.'_v1_2026-10-19.blade.php');
        }
        $this->assertArrayNotHasKey('PROC_RET', MenuRoutes::map());
        $this->get('/purchasing/prt')->assertRedirect('/v2/s/purchase-return');
        $this->get('/purchasing/prt?new=1')->assertRedirect('/v2/s/pur-return-form?new=1');
        $this->get('/purchasing/prt/7/edit')->assertRedirect('/v2/s/pur-return-form?id=7');
        $this->assertSame(0, PrtLine::whereIn('prt_id', [])->count());
    }
}
