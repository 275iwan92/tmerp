<?php

namespace Tests\Feature;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\InvQcDecision;
use App\Models\Inventory\InvQcFirstArticle;
use App\Models\Inventory\InvQcHold;
use App\Models\Inventory\InvQcRiskClass;
use App\Models\Inventory\InvShortageRequest;
use App\Models\Inventory\InvSupplierClaim;
use App\Models\Master\MatRaw;
use App\Models\Pur\PoLine;
use App\Models\Pur\PoOrder;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysAppMenu;
use App\Services\Inventory\BatchService;
use App\Services\Inventory\QcSamplingService;
use App\Services\Pur\PvBuilderService;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Layar Inventory > Batch & Quality jalur QC / URGENT / kurang kirim: semua layar terbuka, menu terpasang, aksi lewat route (keputusan, klaim,
// first article, tanda URGENT, permintaan kirim kekurangan), dan Grid Lookup Risk Class menyimpan kunci barisnya. Jalankan lewat tools/test-ddt.ps1.
class QcScreensTest extends TestCase
{
    private const BY = 'test_qc_screens';

    private MatRaw $material;

    private int $woId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
        $this->actingAs(SecUser::query()->where('is_admin', 1)->firstOrFail());
        $this->material = MatRaw::withoutGlobalScopes()->where('activate', true)->orderBy('material_id')->firstOrFail();
        $this->woId = (int) DB::connection('tm2prd')->table('prd_wo')->orderBy('wo_id')->value('wo_id');
    }

    protected function tearDown(): void
    {
        $this->clean();
        $this->assertSame(0, InvBatch::query()->where('created_by', self::BY)->count() + InvShortageRequest::query()->where('created_by', self::BY)->count()
            + InvQcRiskClass::query()->where('note', self::BY)->count(), 'Jejak uji masih ada.');
        parent::tearDown();
    }

    private function clean(): void
    {
        $batchIds = InvBatch::query()->where('created_by', self::BY)->pluck('id_batch');
        InvQcHold::query()->whereIn('batch_id', $batchIds)->delete();
        InvQcFirstArticle::query()->whereIn('batch_id', $batchIds)->delete();
        InvQcDecision::query()->whereIn('batch_id', $batchIds)->delete();
        InvSupplierClaim::query()->whereIn('batch_id', $batchIds)->delete();
        InvBatch::query()->whereIn('id_batch', $batchIds)->delete();
        InvShortageRequest::query()->where('created_by', self::BY)->delete();
        InvQcRiskClass::query()->where('created_by', self::BY)->orWhere('note', self::BY)->delete();
        $po = PoOrder::query()->where('created_by', self::BY)->pluck('po_id');
        $pvIds = DB::connection('tm2pur')->table('pv_line')->whereIn('c_po_id', $po)->pluck('pv_id');
        DB::connection('tm2pur')->table('pv_line')->whereIn('c_po_id', $po)->delete();
        DB::connection('tm2pur')->table('pv_receipt')->whereIn('pv_id', $pvIds)->delete();
        DB::connection('tm2pur')->table('po_line')->whereIn('po_id', $po)->delete();
        DB::connection('tm2pur')->table('po_order')->whereIn('po_id', $po)->delete();
    }

    private function batch(string $lot): InvBatch
    {
        return BatchService::create('RM', $this->material->material_id, $lot, ['qc_status' => 'CONDITIONAL', 'supplier_id' => DB::connection('tm2mst')->table('sup_supplier')->value('id_supplier'),
            'item_code' => $this->material->material_code, 'item_name' => $this->material->material_name], self::BY);
    }

    public function test_every_screen_opens_and_is_in_the_menu(): void
    {
        $batch = $this->batch('QS-1');
        InvShortageRequest::query()->create(['request_no' => 'SRZ'.random_int(10000, 99999), 'source_type' => 'SUPPLIER', 'wo_id' => $this->woId, 'wo_no' => 'WO-TEST', 'status' => 'DRAFT',
            'qty_candidate' => 3, 'created_by' => self::BY]);
        $req = InvShortageRequest::query()->where('created_by', self::BY)->firstOrFail();

        foreach (['conditional-stock', 'qc-first-article', 'supplier-shortage', 'qc-sampling-plan', 'qc-sampling-rules', 'qc-risk-class', 'qc-urgent-usage', 'shortage-by-supplier', 'supplier-claims'] as $slug) {
            $this->get(route('v2.screen', $slug))->assertOk();
            $this->assertTrue(SysAppMenu::query()->where('target', 'v2:'.$slug)->exists() || ! SysAppMenu::query()->where('code', 'INV_BQ')->exists(), "{$slug} has no menu row.");
        }
        $this->get(route('v2.screen', 'conditional-stock'))->assertSee($batch->batch_no);
        $this->get(route('v2.screen', 'conditional-stock').'?id='.$batch->id_batch)->assertOk()->assertSee('Quality Decision')->assertSee('Release');
        $this->get(route('v2.screen', 'qc-first-article').'?new=1')->assertOk()->assertSee('First Article');
        $this->get(route('v2.screen', 'supplier-shortage').'?new=1')->assertOk()->assertSee('Receipt Line');
        $this->get(route('v2.screen', 'supplier-shortage').'?id='.$req->id_shortage)->assertOk()->assertSee($req->request_no)->assertSee('Recount');
    }

    public function test_decision_route_releases_and_a_rejection_without_a_reason_shows_the_error(): void
    {
        $batch = $this->batch('QS-2');
        $url = route('v2.iq.decide', $batch->id_batch);

        $this->post($url, ['decision' => 'REJECTED', 'decision_type' => 'SAMPLING_DOCK', 'reason' => ''])->assertSessionHasErrors('decide');
        $this->assertSame('CONDITIONAL', $batch->fresh()->qc_status);

        $this->post($url, ['decision' => 'RELEASED', 'decision_type' => 'SAMPLING_DOCK', 'lot_qty' => '100', 'sample_size' => 20, 'sample_defects' => 0, 'test_result' => 'gsm ok'])->assertSessionHasNoErrors();
        $this->assertSame('RELEASED', $batch->fresh()->qc_status);
        $decision = InvQcDecision::query()->where('batch_id', $batch->id_batch)->firstOrFail();
        $this->assertSame([20, 0, 'gsm ok'], [$decision->sample_size, $decision->sample_defects, $decision->test_result]);
        $this->assertEqualsWithDelta(100.0, (float) $decision->lot_qty, 0.0001);

        $this->post($url, ['decision' => 'REJECTED', 'decision_type' => 'USAGE_DECISION', 'reason' => 'Mould found on the pallet'])->assertSessionHasNoErrors();
        $claim = InvSupplierClaim::query()->where('batch_id', $batch->id_batch)->firstOrFail();
        $this->post(route('v2.iq.claim', $claim->id_claim), ['status' => 'SENT', 'notes' => 'Mailed to the supplier'])->assertSessionHasNoErrors();
        $this->assertSame('SENT', $claim->fresh()->status);
        $this->get(route('v2.screen', 'supplier-claims'))->assertOk()->assertSee($claim->claim_no);
    }

    public function test_first_article_route_records_a_result(): void
    {
        $batch = $this->batch('QS-3');
        $this->post(route('v2.iq.first-article'), ['wo_id' => $this->woId, 'batch_id' => $batch->id_batch, 'result' => 'FAIL', 'notes' => ''])->assertSessionHasErrors('first');
        $this->post(route('v2.iq.first-article'), ['wo_id' => $this->woId, 'batch_id' => $batch->id_batch, 'result' => 'PASS', 'notes' => 'Register ok'])->assertSessionHasNoErrors();
        $this->assertSame('RELEASED', $batch->fresh()->qc_status);
        $this->get(route('v2.screen', 'qc-first-article'))->assertOk()->assertSee('Register ok');
    }

    public function test_urgent_card_on_the_goods_receipt_and_the_mark_route(): void
    {
        $m = $this->material;
        $po = PoOrder::create(['doc_no' => 'PO26TESTQS'.now()->timestamp.random_int(10, 99), 'i_doc_date' => now()->toDateString(), 'i_supplier_id' => DB::connection('tm2mst')->table('sup_supplier')->value('id_supplier'),
            'i_po_type' => 'LOCAL', 'i_procure_type' => 'REPEAT', 'i_currency_id' => 1, 'status' => 'SENT', 'created_by' => self::BY, 'updated_by' => self::BY]);
        $poLine = PoLine::create(['po_id' => $po->po_id, 'line_no' => 1, 'item_id' => $m->material_id, 'c_item_code' => $m->material_code, 'i_description' => $m->material_name, 'i_line_class' => 'RAW',
            'i_qty' => 10, 'i_uom' => $m->uom_purchase ?: 'PCS', 'c_uom_stock' => $m->uom_stock ?: 'PCS', 'i_price' => 1000, 'is_stock' => true, 'c_qty_received' => 0, 'c_qty_outstanding' => 10,
            'created_by' => self::BY, 'updated_by' => self::BY]);
        $poLine->recalcNet();
        $poLine->save();
        $pv = PvBuilderService::createFromLines($po->i_supplier_id, 'WH-RAW-01', [$poLine->po_line_id]);
        $line = $pv->lines()->firstOrFail();

        $this->get(route('v2.screen', 'purchase-receive').'?id='.$pv->pv_id)->assertOk()->assertSee('URGENT and Sampling')->assertSee('Sampling');

        $this->post(route('v2.iq.urgent', $pv->pv_id), ['pv_line_id' => $line->pv_line_id, 'step' => 'mark', 'wo_id' => '', 'reason' => 'Press waits'])->assertSessionHasErrors('urgent');
        $this->post(route('v2.iq.urgent', $pv->pv_id), ['pv_line_id' => $line->pv_line_id, 'step' => 'mark', 'wo_id' => $this->woId, 'reason' => 'Press 2 waits for the board'])->assertSessionHasNoErrors();
        $this->assertTrue((bool) $line->fresh()->is_urgent);
        $this->post(route('v2.iq.urgent', $pv->pv_id), ['pv_line_id' => $line->pv_line_id, 'step' => 'certify', 'certification' => 'FSC', 'food_grade' => 1])->assertSessionHasNoErrors();
        $this->assertSame(['FSC', true], [$line->fresh()->i_certification, $line->fresh()->i_food_grade]);
        $this->get(route('v2.screen', 'purchase-receive').'?id='.$pv->pv_id)->assertOk()->assertSee('Needs approval');
        $this->post(route('v2.iq.urgent', $pv->pv_id), ['pv_line_id' => $line->pv_line_id, 'step' => 'approve', 'note' => 'ok'])->assertSessionHasNoErrors();
        $this->assertNotNull($line->fresh()->urgent_approved_by);
        $this->post(route('v2.iq.urgent', $pv->pv_id), ['pv_line_id' => $line->pv_line_id, 'step' => 'unmark'])->assertSessionHasNoErrors();
        $this->assertFalse((bool) $line->fresh()->is_urgent);
    }

    public function test_risk_class_lookup_stores_the_scope_key_and_refuses_both_or_neither(): void
    {
        $grid = Grids::find('qc-risk-class');
        $category = (int) DB::connection('tm2mst')->table('mat_category')->orderByDesc('id_category')->value('id_category');

        foreach ([['category_id' => null, 'material_code' => ''], ['category_id' => $category, 'material_code' => $this->material->material_code], ['category_id' => null, 'material_code' => 'NO-SUCH-ITEM']] as $bad) {
            try {
                $grid->save([$bad + ['id' => null, 'risk_class' => 'A', 'activate' => true]]);
                $this->fail('The row must be refused.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('row', $e->errors());
            }
        }

        $rows = $grid->save([['id' => null, 'category_id' => null, 'material_code' => strtolower($this->material->material_code), 'risk_class' => 'C', 'note' => self::BY, 'activate' => true]]);
        $saved = InvQcRiskClass::query()->where('note', self::BY)->firstOrFail();
        $saved->forceFill(['created_by' => self::BY])->save();
        $this->assertSame(['ITEM', 'I:'.$this->material->material_id, $this->material->material_code], [$saved->scope_type, $saved->scope_key, $saved->material_code]);
        $this->assertNotEmpty($rows);
        $this->assertSame('C', QcSamplingService::riskClass((int) $this->material->material_id));

        try {
            $grid->save([['id' => null, 'category_id' => null, 'material_code' => $this->material->material_code, 'risk_class' => 'A', 'activate' => true]]);
            $this->fail('A second row for the same item must be refused.');
        } catch (ValidationException) {
            $this->assertSame(1, InvQcRiskClass::query()->where('scope_key', $saved->scope_key)->count());
        }
    }
}
