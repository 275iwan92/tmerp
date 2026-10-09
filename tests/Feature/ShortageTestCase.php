<?php

namespace Tests\Feature;

use App\Models\Inventory\InvShortageRequest;
use App\Models\Master\MatRaw;
use App\Models\Pur\PoLine;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PvLine;
use App\Models\Sys\SecUser;
use App\Services\Pur\PvBuilderService;
use App\Services\Pur\PvPostingService;
use App\Services\Pur\ShortageRequestService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Dasar uji kurang kirim: PO + penerimaan 10 satuan yang diposting sungguhan + dokumen keluar ke WO; tearDown membersihkan semua jejaknya (ke ddt*).
abstract class ShortageTestCase extends TestCase
{
    protected const BY = 'test_shortage';

    protected PoOrder $po;

    protected MatRaw $material;

    protected array $materialBefore;

    protected int $woId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(SecUser::where('login', 'johiwan')->orWhere('is_admin', true)->firstOrFail());
        $this->woId = (int) DB::connection('tm2prd')->table('prd_wo')->orderBy('wo_id')->value('wo_id');
        $this->material = MatRaw::query()->where('activate', 1)->whereNotNull('uom_stock')->orderBy('material_id')->firstOrFail();
        $this->materialBefore = $this->material->only(['cur_stock', 'avg_unitprice', 'last_unitprice']);
        $this->po = PoOrder::create(['doc_no' => 'PO26TESTSR'.now()->timestamp.random_int(10, 99), 'i_doc_date' => now()->toDateString(),
            'i_supplier_id' => DB::connection('tm2mst')->table('sup_supplier')->value('id_supplier'), 'i_po_type' => 'LOCAL', 'i_procure_type' => 'REPEAT', 'i_currency_id' => 1,
            'status' => 'SENT', 'created_by' => self::BY, 'updated_by' => self::BY]);
    }

    protected function tearDown(): void
    {
        $inv = DB::connection('tm2inv');
        $pur = DB::connection('tm2pur');
        $pvIds = $pur->table('pv_line')->where('c_po_id', $this->po->po_id)->pluck('pv_id')->unique();
        $docIds = $inv->table('rm_stock')->whereIn('ref_doc_type', ['PV', 'PV_SHORT'])->whereIn('ref_doc_id', $pvIds)->pluck('doc_id')
            ->merge($inv->table('rm_stock')->where('create_by', self::BY)->pluck('doc_id'));
        $detailIds = $inv->table('rm_stock_d')->whereIn('doc_id', $docIds)->pluck('doc_detail_id');
        $inv->table('rm_stockcard')->whereIn('reference_detail_id', $detailIds)->delete();
        $inv->table('rm_stock_d')->whereIn('doc_id', $docIds)->delete();
        $inv->table('rm_stock')->whereIn('doc_id', $docIds)->delete();
        $batchIds = $pur->table('pv_line')->where('c_po_id', $this->po->po_id)->pluck('i_batch_id')->filter();
        $inv->table('inv_qc_decision')->whereIn('batch_id', $batchIds)->delete();
        $inv->table('inv_batch')->whereIn('id_batch', $batchIds)->delete();
        $requests = $inv->table('inv_shortage_request')->where('created_by', self::BY)->get(['id_shortage', 'request_no', 'pr_id']);
        foreach ($requests->pluck('request_no') as $no) {
            DB::connection('tm2sys')->table('sys_notifications')->where('message', 'like', $no.' %')->delete();
        }
        $pur->table('dn_debit_note')->whereIn('shortage_id', $requests->pluck('id_shortage'))->delete();
        $pur->table('pr_line')->whereIn('pr_id', $requests->pluck('pr_id')->filter())->delete();
        $pur->table('pr_requisition')->whereIn('pr_id', $requests->pluck('pr_id')->filter())->delete();
        $inv->table('inv_shortage_request')->where('created_by', self::BY)->delete();
        $voucherIds = DB::connection('tm2acc')->table('fin_voucher')->whereIn('source_type', ['PV', 'PV_SHORT'])->whereIn('source_id', $pvIds)->pluck('voucher_id');
        DB::connection('tm2acc')->table('fin_voucher_line')->whereIn('voucher_id', $voucherIds)->delete();
        DB::connection('tm2acc')->table('fin_voucher')->whereIn('voucher_id', $voucherIds)->delete();
        $pur->table('pv_line')->where('c_po_id', $this->po->po_id)->delete();
        $pur->table('pv_receipt')->whereIn('pv_id', $pvIds)->delete();
        $pur->table('po_line')->where('po_id', $this->po->po_id)->delete();
        $pur->table('po_order')->where('po_id', $this->po->po_id)->delete();
        DB::connection('tm2mst')->table('mat_raw')->where('material_id', $this->material->material_id)->update($this->materialBefore);
        // Database dummy dipakai jalur lain bersamaan, jadi yang diperiksa hanya jejak uji ini (bukan jumlah baris seluruh tabel).
        $this->assertSame(0, $inv->table('rm_stock')->whereIn('doc_id', $docIds)->count() + $inv->table('rm_stock_d')->whereIn('doc_id', $docIds)->count(), 'Dokumen stok uji masih ada.');
        $this->assertSame(0, $inv->table('rm_stock')->whereIn('ref_doc_type', ['PV', 'PV_SHORT'])->whereIn('ref_doc_id', $pvIds)->count(), 'Dokumen stok penerimaan uji masih ada.');
        $this->assertSame(0, $inv->table('rm_stockcard')->whereIn('reference_detail_id', $detailIds)->count() + $inv->table('inv_batch')->whereIn('id_batch', $batchIds)->count(), 'Kartu stok / batch uji masih ada.');
        $this->assertSame(0, $inv->table('inv_shortage_request')->where('created_by', self::BY)->count() + $pur->table('pv_receipt')->whereIn('pv_id', $pvIds)->count()
            + $pur->table('po_order')->where('po_id', $this->po->po_id)->count() + DB::connection('tm2acc')->table('fin_voucher')->whereIn('voucher_id', $voucherIds)->count(), 'Jejak uji masih ada.');
        parent::tearDown();
    }

    // Penerimaan 10 satuan yang sudah diposting + dokumen keluar 10 satuan ke WO (hanya baris dokumen, supaya laporan rekonsiliasi melihatnya).
    protected function postedReceiptIssuedToWo(): PvLine
    {
        $m = $this->material;
        $poLine = PoLine::create(['po_id' => $this->po->po_id, 'line_no' => 1, 'item_id' => $m->material_id, 'c_item_code' => $m->material_code, 'i_description' => $m->material_name, 'i_line_class' => 'RAW',
            'i_qty' => 10, 'i_uom' => $m->uom_purchase ?: 'PCS', 'c_uom_stock' => $m->uom_stock ?: 'PCS', 'i_price' => 1000, 'is_stock' => true, 'c_qty_received' => 0, 'c_qty_outstanding' => 10,
            'created_by' => self::BY, 'updated_by' => self::BY]);
        $poLine->recalcNet();
        $poLine->save();
        $pv = PvBuilderService::createFromLines($this->po->i_supplier_id, 'WH-RAW-01', [$poLine->po_line_id]);
        $pv->lines()->firstOrFail()->update(['qc_qty_status' => 'DONE', 'qc_quality_status' => 'PASS', 'c_qty_accepted' => 10, 'i_lot_no' => 'TSR-LOT', 'qc_by' => self::BY]);
        $pv->update(['status' => 'QC_PENDING']);
        $result = PvPostingService::post($pv->fresh());
        $this->assertTrue($result['ok'], (string) ($result['message'] ?? ''));

        $docId = DB::connection('tm2inv')->table('rm_stock')->insertGetId(['doc_no' => 'TSR'.random_int(100000, 999999), 'doc_date' => now(), 'doc_type' => -1, 'doc_cat' => 'STOCK', 'flow_status' => 'POSTED',
            'stat_confirm' => 1, 'stat_cancel' => 0, 'wo_id' => $this->woId, 'wo_no' => 'WO-TSR', 'ref_doc_no' => 'WO-TSR', 'create_by' => self::BY]);
        DB::connection('tm2inv')->table('rm_stock_d')->insert(['doc_id' => $docId, 'material_id' => $m->material_id, 'location_id' => 1, 'qty' => 10, 'uom_code' => $m->uom_stock,
            'batch_id' => $pv->lines()->firstOrFail()->i_batch_id]);

        return $pv->lines()->firstOrFail()->fresh();
    }

    protected function request(PvLine $line, float $output = 6, float $waste = 1): InvShortageRequest
    {
        return ShortageRequestService::create(['wo_material' => $this->woId.':'.$this->material->material_id, 'pv_line_id' => $line->pv_line_id, 'qty_output_good' => $output,
            'qty_waste_std' => $waste], self::BY);
    }

    protected function stockBalance(): float
    {
        $in = (float) DB::connection('tm2inv')->table('rm_stock_d as d')->join('rm_stock as h', 'h.doc_id', '=', 'd.doc_id')->where('h.create_by', '<>', self::BY)
            ->where('h.stat_confirm', 1)->where('h.doc_type', 1)->where('d.lot_no', 'TSR-LOT')->sum('d.qty');
        $out = (float) DB::connection('tm2inv')->table('rm_stock_d as d')->join('rm_stock as h', 'h.doc_id', '=', 'd.doc_id')->where('h.create_by', '<>', self::BY)
            ->where('h.stat_confirm', 1)->where('h.doc_type', -1)->where('d.lot_no', 'TSR-LOT')->sum('d.qty');

        return $in - $out;
    }
}
