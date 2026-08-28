<?php

namespace Tests\Feature;

use App\Models\Inventory\RmStock;
use App\Models\Master\MatRaw;
use App\Models\Pur\PoLine;
use App\Models\Pur\PoOrder;
use App\Models\Sys\SecUser;
use App\Services\Pur\PvBuilderService;
use App\Services\Pur\PvPostingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Menutup gap "TODO(tminv)" lama di PvPostingService::post() (PROC_TIER_3 §5.1):
// posting PV sekarang benar-benar menulis rm_stock/rm_stock_d/rm_stockcard lewat
// StockDocumentService+StockPostingService (jalur SAH yang sama dgn Stock In
// manual), bukan cuma ubah status PV. Lihat PvPostingService::postToInventory().
class PvStockIntegrationTest extends TestCase
{
    protected ?PoOrder $po = null;

    protected ?int $rmStockDocId = null;

    protected function tearDown(): void
    {
        if ($this->rmStockDocId) {
            $lineIds = DB::connection('tm2inv')->table('rm_stock_d')->where('doc_id', $this->rmStockDocId)->pluck('doc_detail_id');
            DB::connection('tm2inv')->table('rm_stockcard')->whereIn('reference_detail_id', $lineIds)->delete();
            DB::connection('tm2inv')->table('rm_stock_d')->where('doc_id', $this->rmStockDocId)->delete();
            DB::connection('tm2inv')->table('rm_stock')->where('doc_id', $this->rmStockDocId)->delete();
        }

        if ($this->po) {
            $pvIds = DB::connection('tm2pur')->table('pv_line')->where('c_po_id', $this->po->po_id)->pluck('pv_id')->unique();
            DB::connection('tm2pur')->table('pv_line')->where('c_po_id', $this->po->po_id)->delete();
            DB::connection('tm2pur')->table('pv_receipt')->whereIn('pv_id', $pvIds)->delete();
            DB::connection('tm2pur')->table('po_line')->where('po_id', $this->po->po_id)->delete();
            DB::connection('tm2pur')->table('po_order')->where('po_id', $this->po->po_id)->delete();
        }

        parent::tearDown();
    }

    public function test_posting_pv_writes_rm_stock_and_updates_cur_stock(): void
    {
        $admin = SecUser::where('is_admin', true)->firstOrFail();
        $this->actingAs($admin);
        $login = $admin->login;

        $material = MatRaw::query()->whereNotNull('material_id')->first();
        $this->assertNotNull($material, 'Butuh minimal 1 baris mat_raw untuk test ini.');
        $curStockBefore = (float) ($material->cur_stock ?? 0);

        $this->po = PoOrder::create([
            'doc_no' => 'PO26TESTPV'.now()->timestamp,
            'i_doc_date' => now()->toDateString(),
            'i_supplier_id' => DB::connection('tm2mst')->table('sup_supplier')->value('id_supplier'),
            'i_po_type' => 'LOCAL',
            'i_procure_type' => 'REPEAT',
            'i_currency_id' => 1,
            'status' => 'SENT',
            'created_by' => $login,
            'updated_by' => $login,
        ]);

        $poLine = PoLine::create([
            'po_id' => $this->po->po_id,
            'line_no' => 1,
            'item_id' => $material->material_id,
            'c_item_code' => $material->material_code,
            'i_description' => $material->material_name,
            'i_line_class' => 'RAW',
            'i_qty' => 10,
            'i_uom' => $material->uom_purchase ?: 'PCS',
            'c_uom_stock' => $material->uom_stock ?: 'PCS',
            'i_price' => 1000,
            'is_stock' => true,
            'c_qty_received' => 0,
            'c_qty_outstanding' => 10,
            'created_by' => $login,
            'updated_by' => $login,
        ]);
        $poLine->recalcNet();
        $poLine->save();

        $pv = PvBuilderService::createFromLines($this->po->i_supplier_id, 'WH-RAW-01', [$poLine->po_line_id]);
        $this->assertSame('DRAFT', $pv->status);

        $line = $pv->lines()->firstOrFail();
        $line->update([
            'qc_qty_status' => 'DONE',
            'qc_quality_status' => 'PASS',
            'c_qty_accepted' => $line->i_qty_stock,
            'c_qty_rejected' => 0,
            'qc_by' => $login,
            'qc_at' => now(),
            'updated_by' => $login,
        ]);
        $pv->update(['status' => 'QC_PENDING']);

        $this->assertSame([], PvPostingService::blockers($pv->fresh()));

        $result = PvPostingService::post($pv->fresh());

        $this->assertTrue($result['ok'], $result['message'] ?? '');

        $pv->refresh();
        $this->assertSame('POSTED', $pv->status);
        $this->assertNotNull($pv->rm_stock_doc_id);
        $this->rmStockDocId = $pv->rm_stock_doc_id;

        $rmStock = RmStock::with('lines')->findOrFail($pv->rm_stock_doc_id);
        $this->assertSame('PV', $rmStock->ref_doc_type);
        $this->assertSame($pv->doc_no, $rmStock->ref_doc_no);
        $this->assertTrue((bool) $rmStock->stat_confirm);
        $this->assertCount(1, $rmStock->lines);
        $this->assertEqualsWithDelta(10.0, (float) $rmStock->lines->first()->qty, 0.0001);

        $card = DB::connection('tm2inv')->table('rm_stockcard')
            ->where('reference_detail_id', $rmStock->lines->first()->doc_detail_id)
            ->first();
        $this->assertNotNull($card);
        $this->assertEqualsWithDelta(10.0, (float) $card->qty_eb - (float) $card->qty_bb, 0.0001);

        $material->refresh();
        $this->assertEqualsWithDelta($curStockBefore + 10.0, (float) $material->cur_stock, 0.0001);

        $poLine->refresh();
        $this->assertSame('RECEIVED', $poLine->c_line_status);
    }

    public function test_posting_pv_with_no_stock_lines_leaves_rm_stock_doc_id_null(): void
    {
        $admin = SecUser::where('is_admin', true)->firstOrFail();
        $this->actingAs($admin);
        $login = $admin->login;

        $this->po = PoOrder::create([
            'doc_no' => 'PO26TESTSVC'.now()->timestamp,
            'i_doc_date' => now()->toDateString(),
            'i_supplier_id' => DB::connection('tm2mst')->table('sup_supplier')->value('id_supplier'),
            'i_po_type' => 'LOCAL',
            'i_procure_type' => 'REPEAT',
            'i_currency_id' => 1,
            'status' => 'SENT',
            'created_by' => $login,
            'updated_by' => $login,
        ]);

        $poLine = PoLine::create([
            'po_id' => $this->po->po_id,
            'line_no' => 1,
            'item_id' => null,
            'i_description' => 'Jasa servis mesin (FREE_TEXT)',
            'i_line_class' => 'SERVICE',
            'i_qty' => 1,
            'i_uom' => 'UNIT',
            'c_uom_stock' => 'UNIT',
            'i_price' => 500000,
            'is_stock' => false,
            'is_receivable' => true,
            'c_qty_received' => 0,
            'c_qty_outstanding' => 1,
            'created_by' => $login,
            'updated_by' => $login,
        ]);
        $poLine->recalcNet();
        $poLine->save();

        $pv = PvBuilderService::createFromLines($this->po->i_supplier_id, 'WH-RAW-01', [$poLine->po_line_id]);
        $line = $pv->lines()->firstOrFail();
        $line->update([
            'qc_qty_status' => 'DONE',
            'qc_quality_status' => 'PASS',
            'c_qty_accepted' => $line->i_qty_stock,
            'c_qty_rejected' => 0,
            'qc_by' => $login,
            'qc_at' => now(),
            'updated_by' => $login,
        ]);
        $pv->update(['status' => 'QC_PENDING']);

        $result = PvPostingService::post($pv->fresh());

        $this->assertTrue($result['ok'], $result['message'] ?? '');

        $pv->refresh();
        $this->assertSame('POSTED', $pv->status);
        $this->assertNull($pv->rm_stock_doc_id);
    }
}
