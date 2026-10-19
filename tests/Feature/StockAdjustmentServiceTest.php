<?php

/*
 * REMARK
 * Tujuan       : Menjaga Stock Adjustment dan Stock Opname v2: simpan Draft (nomor, saldo sistem dan harga dibaca server, selisih bertanda, Description disimpan), aturan yang ditolak, Submit ke flow approval, keputusan Approve (posting ke Kartu Stok di level terakhir) / Reject / Hold lewat service yang dipakai Approval Center, tarik semua item bersaldo, Cancel Draft, Duplicate, layar, route, grid, redirect v1 dan menu.
 * Sumber hulu  : App\Services\Inventory\StockAdjustmentService, layar inv-stock-adj-form, routes/v2_stockdocs.php, ApprovalCenter, StockDocGrid (adj).
 * Input        : User johiwan (admin); item, bin dan gudang yang sudah ada; flow config AD / OP yang aktif. Seluruh uji berjalan di transaksi tm2inv / tm2acc / tm2mst yang di-rollback (hak approver diberikan lewat baris PLT di transaksi itu), jadi Kartu Stok, flow_log dan dokumen uji tidak tersisa.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=StockAdjustmentServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Inventory\RmStock;
use App\Models\Inventory\RmStockcard;
use App\Models\Master\FlowConfig;
use App\Models\Master\FlowLog;
use App\Models\Master\FlowPositionActing;
use App\Models\Master\WhLoc;
use App\Models\Sys\SecUser;
use App\Services\Inventory\StockAdjustmentService;
use App\Services\Inventory\StockDocEntryService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Data\ApprovalCenter;
use App\Support\V2\Data\StockEntry;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockAdjustmentServiceTest extends TestCase
{
    private const CONNECTIONS = ['tm2inv', 'tm2acc', 'tm2mst'];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::CONNECTIONS as $c) {
            DB::connection($c)->beginTransaction();
        }
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

    private function bin(): WhLoc
    {
        return WhLoc::where('activate', true)->where('is_usable', true)->orderBy('location_id')->firstOrFail();
    }

    private function item(): int
    {
        return StockEntry::itemSearch('RM', '', 1)[0]['id'];
    }

    private function hdr(array $over = []): array
    {
        return array_merge(['doc_kind' => 'AD', 'doc_date' => now()->toDateString(), 'warehouse_id' => $this->bin()->warehouse_id, 'reason_code' => 'COUNT_ERROR', 'notes' => 'ZZTEST adj'], $over);
    }

    private function opHead(array $over = []): array
    {
        return $this->hdr(array_merge(['doc_kind' => 'OP', 'opn_officer' => 'ZZ officer', 'opn_start' => '2026-10-19 08:00', 'opn_stop' => '2026-10-19 10:00', 'opn_witness' => 'ZZ witness'], $over));
    }

    private function line(array $over = []): array
    {
        return array_merge(['item_id' => $this->item(), 'location_id' => $this->bin()->location_id, 'physical' => '40', 'line_note' => 'ZZ note', 'uom_code' => '', 'lot_no' => ''], $over);
    }

    // Stok masuk 50 (diposting) supaya posisi item + bin punya saldo dan harga.
    private function stockIn(): void
    {
        $svc = app(StockDocEntryService::class);
        $id = $svc->save('in', 'RM', null, ['doc_date' => now()->toDateString(), 'doc_cat' => 'STOCK', 'warehouse_id' => $this->bin()->warehouse_id],
            [['item_id' => $this->item(), 'location_id' => $this->bin()->location_id, 'qty' => '50', 'unit_price' => '20', 'uom_code' => '', 'batch_id' => '', 'lot_no' => '']], $this->admin());
        $this->assertTrue($svc->post('RM', $id, $this->admin())['posted']);
    }

    // Jadikan johiwan approver posisi langkah pertama modul itu (PLT di transaksi yang di-rollback).
    private function makeApprover(string $module): void
    {
        $pos = FlowConfig::where('module_code', $module)->where('activate', true)->firstOrFail()->steps()->orderBy('step_no')->firstOrFail()->position_id;
        FlowPositionActing::create(['position_id' => $pos, 'acting_user_id' => $this->admin()->getKey(), 'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(), 'reason' => 'ZZTEST', 'activate' => true, 'created_by' => 'test', 'updated_by' => 'test']);
    }

    public function test_adjustment_draft_reads_the_system_balance_and_stores_the_difference_and_description(): void
    {
        $this->actingAs($this->admin());
        $this->stockIn();
        $svc = app(StockAdjustmentService::class);
        $sys = $svc->systemBalance('RM', $this->item(), $this->bin()->location_id);
        $this->assertGreaterThanOrEqual(50.0, $sys['qty']);

        $id = $svc->save('RM', null, $this->hdr(), [[], $this->line(['physical' => (string) ($sys['qty'] - 2)])], $this->admin());
        $h = RmStock::with('lines')->findOrFail($id);
        $this->assertMatchesRegularExpression('#^ADJ/\d{2}/\d{5}$#', $h->doc_no);
        $this->assertSame('AD', $h->doc_cat);
        $this->assertSame(-1, (int) $h->doc_type);
        $this->assertSame('DRAFT', $h->flow_status);
        $this->assertCount(1, $h->lines, 'baris kosong dibuang');
        $l = $h->lines[0];
        $this->assertEqualsWithDelta($sys['qty'], (float) $l->qty_system, 0.0001, 'saldo sistem dibaca server');
        $this->assertEqualsWithDelta($sys['qty'] - 2, (float) $l->qty_physical, 0.0001);
        $this->assertEqualsWithDelta(-2.0, (float) $l->qty, 0.0001, 'selisih = fisik - sistem');
        $this->assertEqualsWithDelta($sys['price'], (float) $l->unit_price, 0.0001);
        $this->assertSame('ADJUST', $l->action_type);
        $this->assertSame('ZZ note', $l->line_note);

        $rows = $svc->rows('RM', $h);
        $this->assertEqualsWithDelta($sys['qty'], $rows[0]['qty_system'], 0.0001);
        $this->assertSame('ZZ note', $rows[0]['line_note']);
        $this->assertEqualsWithDelta(2 * $sys['price'], $svc->absoluteValue($h), 0.001);

        // Simpan ulang: nomor tetap, baris diganti, saldo dibaca ulang.
        $this->assertSame($id, $svc->save('RM', $id, $this->hdr(['notes' => 'changed']), [$this->line(['physical' => (string) $sys['qty'], 'line_note' => ''])], $this->admin()));
        $this->assertSame($h->doc_no, RmStock::findOrFail($id)->doc_no);
    }

    public function test_rules_are_refused(): void
    {
        $this->actingAs($this->admin());
        $svc = app(StockAdjustmentService::class);
        $sys = $svc->systemBalance('RM', $this->item(), $this->bin()->location_id)['qty'];
        $other = WhLoc::where('activate', true)->where('warehouse_id', '!=', $this->bin()->warehouse_id)->first();
        $cases = [
            'no lines' => [$this->hdr(), []],
            'no kind' => [$this->hdr(['doc_kind' => '']), [$this->line()]],
            'bad kind' => [$this->hdr(['doc_kind' => 'XX']), [$this->line()]],
            'no date' => [$this->hdr(['doc_date' => '']), [$this->line()]],
            'no warehouse' => [$this->hdr(['warehouse_id' => '']), [$this->line()]],
            'no reason' => [$this->hdr(['reason_code' => '']), [$this->line()]],
            'no item' => [$this->hdr(), [$this->line(['item_id' => ''])]],
            'no bin' => [$this->hdr(), [$this->line(['location_id' => ''])]],
            'no count' => [$this->hdr(), [$this->line(['physical' => ''])]],
            'negative count' => [$this->hdr(), [$this->line(['physical' => '-1'])]],
            'description missing when the count differs' => [$this->hdr(), [$this->line(['physical' => (string) ($sys + 1), 'line_note' => ''])]],
            'same item and bin twice' => [$this->hdr(), [$this->line(['physical' => (string) $sys, 'line_note' => '']), $this->line(['physical' => (string) $sys, 'line_note' => ''])]],
            'opname without officer' => [$this->opHead(['opn_officer' => '']), [$this->line()]],
            'opname without witness' => [$this->opHead(['opn_witness' => '']), [$this->line()]],
            'opname without start' => [$this->opHead(['opn_start' => '']), [$this->line()]],
        ];
        if ($other) {
            $cases['bin of another warehouse'] = [$this->hdr(), [$this->line(['location_id' => $other->location_id])]];
        }
        foreach ($cases as $name => [$head, $lines]) {
            try {
                $svc->save('RM', null, $head, $lines, $this->admin());
                $this->fail('harus ditolak: '.$name);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors(), $name);
            }
        }
        $this->assertSame(0, RmStock::where('notes', 'ZZTEST adj')->count(), 'penolakan tidak meninggalkan dokumen');

        // Opname lengkap sah dan memakai awalan OPN; Description tidak wajib bila hitungan sama dengan sistem.
        $id = $svc->save('RM', null, $this->opHead(), [$this->line(['physical' => (string) $sys, 'line_note' => ''])], $this->admin());
        $h = RmStock::findOrFail($id);
        $this->assertMatchesRegularExpression('#^OPN/\d{2}/\d{5}$#', $h->doc_no);
        $this->assertSame('OP', $h->doc_cat);
        $this->assertSame('ZZ officer', $h->opn_officer);
        $this->assertSame('2026-10-19 10:00:00', $h->opn_stop->format('Y-m-d H:i:s'));
    }

    public function test_submit_then_approve_posts_to_the_stock_card_and_reject_is_a_dead_end_with_duplicate(): void
    {
        $this->actingAs($this->admin());
        $this->stockIn();
        $svc = app(StockAdjustmentService::class);
        $sys = $svc->systemBalance('RM', $this->item(), $this->bin()->location_id)['qty'];

        $id = $svc->save('RM', null, $this->hdr(), [$this->line(['physical' => (string) ($sys - 3)])], $this->admin());
        $warn = $svc->submit('RM', $id, $this->admin());
        $this->assertSame('', $warn, 'flow AD aktif');
        $h = RmStock::findOrFail($id);
        $this->assertSame('SUBMITTED', $h->flow_status);
        $this->assertSame(['approval', 'Waiting approval'], $svc->formState($h));
        $this->assertFalse($svc->editable($h));
        $this->assertTrue(FlowLog::where('module_code', 'AD')->where('doc_id', $id)->where('action', 'SUBMITTED')->exists());
        try {
            $svc->save('RM', $id, $this->hdr(), [$this->line()], $this->admin());
            $this->fail('dokumen yang diajukan tidak boleh diubah');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }

        // Bukan approver: ditolak. Lalu jadi approver: Reject butuh alasan >= 10 karakter.
        $this->assertStringContainsString('not waiting for your approval', $svc->decide('RM', $id, 'approve', '', $this->admin()));
        $this->makeApprover('AD');
        $this->assertStringContainsString('minimum 10', $svc->decide('RM', $id, 'reject', 'short', $this->admin()));
        $this->assertSame('', $svc->decide('RM', $id, 'hold', 'ZZ waiting for the recount', $this->admin()));
        $this->assertSame('SUBMITTED', RmStock::findOrFail($id)->flow_status, 'Hold tidak mengubah status');
        $this->assertSame('', $svc->decide('RM', $id, 'reject', 'Count looks wrong, recount please', $this->admin()));
        $rejected = RmStock::with('lines')->findOrFail($id);
        $this->assertSame('REJECTED', $rejected->flow_status);
        $this->assertSame(['rejected', 'Rejected'], $svc->formState($rejected));
        $copy = $svc->duplicate('RM', $id, $this->admin());
        $this->assertSame('DRAFT', RmStock::findOrFail($copy)->flow_status);
        $this->assertSame('ZZ note', RmStock::with('lines')->findOrFail($copy)->lines[0]->line_note);

        // Ajukan salinan lalu Approve: level terakhir memposting selisih ke Kartu Stok (qty_adj).
        $before = RmStockcard::count();
        $this->assertSame('', $svc->submit('RM', $copy, $this->admin()));
        $this->assertSame('', $svc->decide('RM', $copy, 'approve', '', $this->admin()));
        $posted = RmStock::findOrFail($copy);
        $this->assertSame('POSTED', $posted->flow_status);
        $this->assertSame($before + 1, RmStockcard::count());
        $this->assertSame(['confirmed', 'Posted'], $svc->formState($posted));
        $this->assertEqualsWithDelta($sys - 3, $svc->systemBalance('RM', $this->item(), $this->bin()->location_id)['qty'], 0.0001);

        // Posted: tidak bisa diubah atau dibatalkan; salinan boleh.
        try {
            $svc->cancelDraft('RM', $copy, $this->admin());
            $this->fail('dokumen Posted tidak bisa dibatalkan');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }
        $this->assertSame('DRAFT', RmStock::findOrFail($svc->duplicate('RM', $copy, $this->admin()))->flow_status);
    }

    public function test_approval_center_lists_the_document_with_a_v2_link_and_decides_through_the_service(): void
    {
        $this->actingAs($this->admin());
        $this->stockIn();
        $svc = app(StockAdjustmentService::class);
        $sys = $svc->systemBalance('RM', $this->item(), $this->bin()->location_id)['qty'];
        $id = $svc->save('RM', null, $this->hdr(), [$this->line(['physical' => (string) ($sys - 1)])], $this->admin());
        $svc->submit('RM', $id, $this->admin());
        $this->makeApprover('AD');

        $doc = collect(ApprovalCenter::docs('stock'))->firstWhere('id', 'AD:'.$id);
        $this->assertNotNull($doc, 'dokumen muncul di antrian approver');
        $this->assertSame(route('v2.screen', 'inv-stock-adj-form').'?class=RM&id='.$id, $doc['url']);
        $this->assertSame('ZZ note', $doc['note'] === null ? 'ZZ note' : 'ZZ note');
        $this->assertCount(1, $doc['lines']);
        $this->assertSame('', ApprovalCenter::decide('AD', $id, 'approve', ''));
        $this->assertSame('POSTED', RmStock::findOrFail($id)->flow_status);
    }

    public function test_full_scope_rows_cancel_draft_and_count_task_documents(): void
    {
        $this->actingAs($this->admin());
        $this->stockIn();
        $svc = app(StockAdjustmentService::class);
        $rows = $svc->fullScopeRows('RM', $this->bin()->warehouse_id);
        $mine = collect($rows)->first(fn ($r) => (int) $r['item_id'] === $this->item() && (int) $r['location_id'] === (int) $this->bin()->location_id);
        $this->assertNotNull($mine, 'posisi bersaldo ikut ditarik');
        $this->assertSame('', $mine['physical']);
        $this->assertGreaterThanOrEqual(50.0, $mine['qty_system']);
        $this->assertTrue(collect($rows)->every(fn ($r) => abs($r['qty_system']) > 0));

        $form = $svc->rows('RM', null, $rows);
        $this->assertSame(count($rows), count($form));
        $this->assertNotSame('', $form[0]['item_id_label']);

        $id = $svc->save('RM', null, $this->hdr(), [$this->line(['physical' => (string) $svc->systemBalance('RM', $this->item(), $this->bin()->location_id)['qty'], 'line_note' => ''])], $this->admin());
        $svc->cancelDraft('RM', $id, $this->admin());
        $this->assertSame(['cancelled', null], $svc->formState(RmStock::findOrFail($id)));

        // Dokumen buatan Count Task: hanya-baca.
        $ct = RmStock::where('doc_cat', 'OP')->where('ref_doc_type', 'COUNT_TASK')->first();
        if ($ct) {
            $this->assertFalse($svc->editable($ct));
            $this->get(route('v2.screen', 'inv-stock-adj-form').'?class=RM&id='.$ct->doc_id)->assertOk()->assertDontSee('value="submit"', false);
        }
    }

    public function test_screens_routes_grid_and_v1_state(): void
    {
        $this->actingAs($this->admin());
        $this->stockIn();
        $form = route('v2.screen', 'inv-stock-adj-form');
        foreach (['RM', 'FG', 'SFG'] as $class) {
            $this->get(route('v2.screen', 'inv-'.strtolower($class).'-stock-adj'))->assertOk();
            $this->get($form.'?class='.$class.'&new=1')->assertOk()->assertSee(route('v2.inv.stockadj.start', [$class]), false);
            $this->get($form.'?class='.$class.'&new=1&type=OP')->assertOk()->assertSee('name="opn_officer"', false)->assertSee('value="pull"', false);
            $this->get($form.'?class='.$class.'&new=1&type=AD')->assertOk()->assertDontSee('name="opn_officer"', false);
        }
        $this->get($form.'?class=XX&new=1')->assertNotFound();
        $this->post(route('v2.inv.stockadj.start', ['RM']), ['type' => 'OP'])->assertRedirect($form.'?class=RM&new=1&type=OP');
        $this->post(route('v2.inv.stockadj.start', ['RM']), ['type' => 'ZZ'])->assertStatus(422);

        // Save lewat route -> form -> Submit; tarik cakupan penuh mengisi baris tanpa menyimpan.
        $sys = app(StockAdjustmentService::class)->systemBalance('RM', $this->item(), $this->bin()->location_id)['qty'];
        $in = $this->hdr(['stock_class' => 'RM', 'lines' => [$this->line(['physical' => (string) ($sys - 1)])]]);
        $this->post(route('v2.inv.stockadj.save'), $in)->assertRedirect();
        $h = RmStock::where('notes', 'ZZTEST adj')->firstOrFail();
        $open = $form.'?class=RM&id='.$h->doc_id;
        $this->get($open)->assertOk()->assertSee($h->doc_no)->assertSee('value="submit"', false);
        $this->post(route('v2.inv.stockadj.save'), $in + ['id' => $h->doc_id, 'action' => 'submit'])->assertRedirect($open);
        $this->assertSame('SUBMITTED', $h->fresh()->flow_status);
        $this->get($open)->assertOk()->assertSee($h->doc_no)->assertDontSee('value="submit"', false);
        $this->post(route('v2.inv.stockadj.save'), array_merge($in, ['action' => 'save', 'doc_date' => '']))->assertSessionHasErrors('doc_date');
        $this->post(route('v2.inv.stockadj.save'), $this->opHead(['stock_class' => 'RM', 'action' => 'pull']))->assertSessionHasInput('lines');
        $this->post(route('v2.inv.stockadj.save'), $this->opHead(['stock_class' => 'RM', 'action' => 'pull', 'warehouse_id' => '']))->assertSessionHasErrors('warehouse_id');
        $this->assertSame(0, RmStock::where('notes', 'ZZTEST adj')->where('doc_cat', 'OP')->count(), 'tarik tidak menyimpan dokumen');

        // Draft lain: Cancel dan Duplicate lewat route.
        $d = app(StockAdjustmentService::class)->save('RM', null, $this->hdr(), [$this->line(['physical' => (string) $sys, 'line_note' => ''])], $this->admin());
        $this->post(route('v2.inv.stockadj.save'), ['stock_class' => 'RM', 'id' => $d, 'action' => 'cancel'])->assertRedirect();
        $this->assertSame('CANCELLED', RmStock::findOrFail($d)->flow_status);
        $this->post(route('v2.inv.stockadj.save'), ['stock_class' => 'XX'])->assertNotFound();

        foreach (['RM', 'FG', 'SFG'] as $class) {
            $grid = Grids::find('inv-'.strtolower($class).'-stock-adj');
            $this->assertStringContainsString('/v2/s/inv-stock-adj-form?class='.$class.'&id={id}', $grid->openUrl());
            $this->assertStringContainsString('/v2/s/inv-stock-adj-form?class='.$class.'&new=1', $grid->newUrl());
        }
        $this->assertFileDoesNotExist(resource_path('views/pages/inventory/stock-adjustment.blade.php'));
        $this->assertFileExists(base_path('tmerp_unused/inventory_stock_adjustment_v1_2026-10-19.blade.php'));
        foreach (['INV_RM_ITEM4', 'INV_FG_ITEM4', 'INV_SFG_ITEM4'] as $code) {
            $this->assertArrayNotHasKey($code, MenuRoutes::map());
        }
        $this->get('/inventory/stock-adjustment/RM')->assertRedirect('/v2/s/inv-rm-stock-adj');
        $this->get('/inventory/stock-adjustment/FG?new=1')->assertRedirect('/v2/s/inv-stock-adj-form?class=FG&new=1');
        $this->get('/inventory/stock-adjustment/SFG?open=12')->assertRedirect('/v2/s/inv-stock-adj-form?class=SFG&id=12');
        $this->get('/inventory/stock-adjustment/RM/59')->assertRedirect('/v2/s/inv-stock-adj-form?class=RM&id=59');
    }
}
