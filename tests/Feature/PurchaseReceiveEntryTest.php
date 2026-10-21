<?php

/*
 * REMARK
 * Tujuan       : Mengunci perilaku entri Penerimaan Barang (SPB / Goods Receipt) lewat App\Support\V2\Data\PurchaseReceive dan rute v2.pv.save: perubahan header & baris, QC qty / mutu, override toleransi, penolakan saat bukan DRAFT/QC_PENDING, hapus. Tes karakterisasi: asersi tidak berubah saat aturan v1 pages::pur.pv.entry dipindah ke PurchaseReceiveEntryService.
 * Sumber hulu  : PvReceipt, PvLine, PoOrder, PoLine (data uji sendiri, kode "ZZ-TEST").
 * Input        : baris uji pv_receipt + pv_line (+ po_order/po_line untuk variansi toleransi).
 * Output       : asersi terhadap pv_receipt / pv_line; semua baris uji dihapus di tearDown.
 * Dipakai oleh : php artisan test (disarankan lewat tools/test-ddt.ps1 agar ke database dummy).
 * Status       : Aktif (T10 tahap 7b, 2026-10-22).
 */

namespace Tests\Feature;

use App\Models\Master\MatRaw;
use App\Models\Pur\PoLine;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PvLine;
use App\Models\Pur\PvReceipt;
use App\Models\Sys\SecUser;
use App\Services\Pur\PurchaseReceiveEntryService;
use App\Support\V2\Data\PurchaseReceive;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PurchaseReceiveEntryTest extends TestCase
{
    private SecUser $admin;

    /** @var list<int> */
    private array $pvIds = [];

    private ?PoOrder $po = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = SecUser::where('is_admin', true)->firstOrFail();
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        // pv_line ikut terhapus (FK pv_id ON DELETE CASCADE); po_line dulu sebelum po_order.
        PvReceipt::whereIn('pv_id', $this->pvIds)->delete();
        if ($this->po) {
            PoLine::where('po_id', $this->po->po_id)->delete();
            $this->po->delete();
        }
        $pur = DB::connection('tm2pur');
        $this->assertSame(0, $pur->table('pv_receipt')->whereIn('pv_id', $this->pvIds)->count(), 'Jejak pv_receipt uji masih ada.');
        $this->assertSame(0, $pur->table('pv_line')->whereIn('pv_id', $this->pvIds)->count(), 'Jejak pv_line uji masih ada.');
        $this->assertSame(0, $pur->table('po_order')->where('doc_no', 'like', 'ZZ-TEST-PV%')->count(), 'Jejak po_order uji masih ada.');

        parent::tearDown();
    }

    // SPB uji satu baris: 10 PCS beli = 10 PCS stok, harga 1000. $withPo = tautkan ke PO uji (sisa 10) supaya variansi toleransi bisa dihitung.
    private function receipt(string $status = 'DRAFT', bool $withPo = false): PvLine
    {
        $poLineId = null;
        if ($withPo) {
            $m = MatRaw::query()->where('activate', 1)->orderBy('material_id')->firstOrFail();
            $this->po ??= PoOrder::create(['doc_no' => 'ZZ-TEST-PV'.now()->timestamp.random_int(10, 99), 'i_doc_date' => now()->toDateString(),
                'i_supplier_id' => DB::connection('tm2mst')->table('sup_supplier')->value('id_supplier'), 'i_po_type' => 'LOCAL', 'i_procure_type' => 'REPEAT', 'i_currency_id' => 1,
                'status' => 'SENT', 'created_by' => 'zz_test', 'updated_by' => 'zz_test']);
            $poLine = PoLine::create(['po_id' => $this->po->po_id, 'line_no' => 1, 'item_id' => $m->material_id, 'c_item_code' => $m->material_code, 'i_description' => 'ZZ-TEST',
                'i_line_class' => 'RAW', 'i_qty' => 10, 'i_uom' => 'PCS', 'c_uom_stock' => 'PCS', 'i_price' => 1000, 'is_stock' => true, 'c_qty_received' => 0,
                'c_qty_outstanding' => 10, 'created_by' => 'zz_test', 'updated_by' => 'zz_test']);
            $poLine->recalcNet();
            $poLine->save();
            $poLineId = $poLine->po_line_id;
        }
        $pv = PvReceipt::create(['i_receipt_type' => 'PO', 'i_doc_date' => '2026-01-05', 'i_supplier_id' => 1, 'i_warehouse_code' => 'ZZ-TEST', 'status' => $status,
            'i_notes' => 'catatan awal', 'created_by' => 'zz_test', 'updated_by' => 'zz_test']);
        $this->pvIds[] = $pv->pv_id;

        return PvLine::create(['pv_id' => $pv->pv_id, 'line_no' => 1, 'po_line_id' => $poLineId, 'i_description' => 'ZZ-TEST line', 'i_qty_purchase' => 10, 'i_uom_purchase' => 'PCS',
            'i_qty_stock' => 10, 'i_uom_stock' => 'PCS', 'c_unit_price' => 1000, 'created_by' => 'zz_test', 'updated_by' => 'zz_test']);
    }

    private function pv(PvLine $line): PvReceipt
    {
        return PvReceipt::findOrFail($line->pv_id);
    }

    public function test_save_changes_header_fields_and_empty_text_becomes_null(): void
    {
        $line = $this->receipt();

        PurchaseReceive::save($this->pv($line), ['i_doc_date' => '2026-02-10', 'i_supplier_do_no' => 'SJ-ZZ-1', 'i_vehicle_no' => 'B 1234 ZZ', 'i_notes' => '']);

        $pv = $this->pv($line);
        $this->assertSame('2026-02-10', $pv->i_doc_date->toDateString());
        $this->assertSame('SJ-ZZ-1', $pv->i_supplier_do_no);
        $this->assertSame('B 1234 ZZ', $pv->i_vehicle_no);
        $this->assertNull($pv->i_notes);
        $this->assertSame($this->admin->login, $pv->updated_by);
    }

    public function test_save_changes_line_qty_and_recomputes_conversion(): void
    {
        $line = $this->receipt();

        PurchaseReceive::save($this->pv($line), ['lines' => [$line->pv_line_id => ['i_qty_purchase' => '4', 'i_qty_stock' => '8', 'i_lot_no' => 'LOT-ZZ', 'i_location_code' => 'A-01']]]);

        $line->refresh();
        $this->assertEqualsWithDelta(4.0, (float) $line->i_qty_purchase, 0.0001);
        $this->assertEqualsWithDelta(8.0, (float) $line->i_qty_stock, 0.0001);
        $this->assertEqualsWithDelta(2.0, (float) $line->c_conv_used, 0.0001);
        $this->assertSame('LOT-ZZ', $line->i_lot_no);
        $this->assertSame('A-01', $line->i_location_code);
        $this->assertSame($this->admin->login, $line->updated_by);
        $this->assertFalse((bool) $line->is_tol_exceeded, 'Tanpa po_line, variansi toleransi tidak dihitung.');
    }

    public function test_save_flags_tolerance_when_qty_exceeds_the_open_po_qty(): void
    {
        $line = $this->receipt('DRAFT', true);

        PurchaseReceive::save($this->pv($line), ['lines' => [$line->pv_line_id => ['i_qty_purchase' => '20', 'i_qty_stock' => '20']]]);

        $line->refresh();
        $this->assertTrue((bool) $line->is_tol_exceeded);
        $this->assertEqualsWithDelta(100.0, (float) $line->c_qty_variance_pct, 0.0001);

        PurchaseReceive::save($this->pv($line), ['lines' => [$line->pv_line_id => ['i_qty_purchase' => '10', 'i_qty_stock' => '10']]]);
        $this->assertFalse((bool) $line->refresh()->is_tol_exceeded);
    }

    public function test_a_posted_or_cancelled_receipt_cannot_be_changed(): void
    {
        foreach (['POSTED', 'CANCELLED'] as $status) {
            $line = $this->receipt($status);
            try {
                PurchaseReceive::save($this->pv($line), ['i_vehicle_no' => 'XX', 'lines' => [$line->pv_line_id => ['i_qty_purchase' => '99']]]);
                $this->fail('Perubahan pada SPB '.$status.' seharusnya ditolak.');
            } catch (HttpException $e) {
                $this->assertSame(422, $e->getStatusCode());
            }
            $this->assertNull($this->pv($line)->i_vehicle_no);
            $this->assertEqualsWithDelta(10.0, (float) $line->refresh()->i_qty_purchase, 0.0001);
        }
    }

    public function test_qty_qc_done_moves_the_receipt_from_draft_to_qc_pending(): void
    {
        $line = $this->receipt();
        $this->assertSame('PENDING', $line->refresh()->qc_qty_status);

        PurchaseReceive::save($this->pv($line), ['lines' => [$line->pv_line_id => ['qc_qty' => 'DONE']]]);

        $this->assertSame('DONE', $line->refresh()->qc_qty_status);
        $this->assertSame($this->admin->login, $line->updated_by);
        $this->assertSame('QC_PENDING', $this->pv($line)->status);
    }

    public function test_quality_pass_accepts_the_whole_stock_qty_and_fail_rejects_it(): void
    {
        $line = $this->receipt();

        PurchaseReceive::save($this->pv($line), ['lines' => [$line->pv_line_id => ['qc_quality_status' => 'PASS']]]);
        $line->refresh();
        $this->assertSame('PASS', $line->qc_quality_status);
        $this->assertEqualsWithDelta(10.0, (float) $line->c_qty_accepted, 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $line->c_qty_rejected, 0.0001);
        $this->assertEqualsWithDelta(10000.0, (float) $line->c_line_value, 0.0001);
        $this->assertSame($this->admin->login, $line->qc_by);
        $this->assertNotNull($line->qc_at);

        PurchaseReceive::save($this->pv($line), ['lines' => [$line->pv_line_id => ['qc_quality_status' => 'FAIL']]]);
        $line->refresh();
        $this->assertSame('FAIL', $line->qc_quality_status);
        $this->assertEqualsWithDelta(0.0, (float) $line->c_qty_accepted, 0.0001);
        $this->assertEqualsWithDelta(10.0, (float) $line->c_qty_rejected, 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $line->c_line_value, 0.0001);
    }

    public function test_quality_partial_takes_the_accepted_and_rejected_qty_from_the_officer(): void
    {
        $line = $this->receipt();

        PurchaseReceive::save($this->pv($line), ['lines' => [$line->pv_line_id => ['qc_quality_status' => 'PARTIAL', 'c_qty_accepted' => '6', 'c_qty_rejected' => '4']]]);

        $line->refresh();
        $this->assertSame('PARTIAL', $line->qc_quality_status);
        $this->assertEqualsWithDelta(6.0, (float) $line->c_qty_accepted, 0.0001);
        $this->assertEqualsWithDelta(4.0, (float) $line->c_qty_rejected, 0.0001);
        $this->assertEqualsWithDelta(6000.0, (float) $line->c_line_value, 0.0001);
    }

    public function test_tolerance_override_needs_a_reason_and_an_exceeded_line(): void
    {
        $line = $this->receipt('DRAFT', true);
        PurchaseReceive::save($this->pv($line), ['lines' => [$line->pv_line_id => ['i_qty_purchase' => '20', 'i_qty_stock' => '20']]]);

        PurchaseReceive::save($this->pv($line), ['lines' => [$line->pv_line_id => ['tol_override_reason' => '   ']]]);
        $this->assertNull($line->refresh()->tol_override_by);

        PurchaseReceive::save($this->pv($line), ['lines' => [$line->pv_line_id => ['tol_override_reason' => 'Supplier kirim lebih, disetujui Kabag']]]);
        $line->refresh();
        $this->assertSame($this->admin->login, $line->tol_override_by);
        $this->assertSame('Supplier kirim lebih, disetujui Kabag', $line->tol_override_reason);
        $this->assertNotNull($line->tol_override_at);

        // Baris yang tidak melewati toleransi tidak di-override.
        $ok = $this->receipt();
        PurchaseReceive::save($this->pv($ok), ['lines' => [$ok->pv_line_id => ['tol_override_reason' => 'tidak perlu']]]);
        $this->assertNull($ok->refresh()->tol_override_by);
    }

    public function test_entry_service_ignores_header_and_line_edits_outside_draft_and_unknown_fields(): void
    {
        $posted = $this->receipt('POSTED');
        PurchaseReceiveEntryService::updateHeader($this->pv($posted), 'i_vehicle_no', 'XX');
        PurchaseReceiveEntryService::updateLine($this->pv($posted), $posted->pv_line_id, 'i_lot_no', 'XX');
        $this->assertNull($this->pv($posted)->i_vehicle_no);
        $this->assertNull($posted->refresh()->i_lot_no);

        $draft = $this->receipt();
        PurchaseReceiveEntryService::updateHeader($this->pv($draft), 'status', 'POSTED');
        PurchaseReceiveEntryService::updateLine($this->pv($draft), $draft->pv_line_id, 'c_unit_price', '1');
        PurchaseReceiveEntryService::updateQcQty($this->pv($draft), $draft->pv_line_id, 'c_unit_price', '1');
        $this->assertSame('DRAFT', $this->pv($draft)->status);
        $this->assertEqualsWithDelta(1000.0, (float) $draft->refresh()->c_unit_price, 0.0001);
    }

    public function test_entry_service_override_returns_the_message_and_only_a_reason_unlocks_it(): void
    {
        $line = $this->receipt();

        $this->assertNotSame('', PurchaseReceiveEntryService::overrideTolerance($this->pv($line), $line->pv_line_id, ' '));
        $this->assertNull($line->refresh()->tol_override_by);
        $this->assertSame('', PurchaseReceiveEntryService::overrideTolerance($this->pv($line), $line->pv_line_id, 'ok'));
        $this->assertSame('ok', $line->refresh()->tol_override_reason);
    }

    public function test_post_is_refused_while_the_qc_is_pending_and_leaves_the_receipt_untouched(): void
    {
        $line = $this->receipt();

        [$ok, $message] = PurchaseReceive::post($this->pv($line));

        $this->assertFalse($ok);
        $this->assertStringContainsString('qty QC', $message);
        $pv = $this->pv($line);
        $this->assertSame('DRAFT', $pv->status);
        $this->assertNull($pv->doc_no);
    }

    public function test_only_a_draft_receipt_can_be_deleted(): void
    {
        $kept = $this->receipt('QC_PENDING');
        try {
            PurchaseReceive::delete($this->pv($kept));
            $this->fail('SPB QC_PENDING tidak boleh dihapus.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertSame(1, PvReceipt::where('pv_id', $kept->pv_id)->count());

        $gone = $this->receipt();
        PurchaseReceive::delete($this->pv($gone));
        $this->assertSame(0, PvReceipt::where('pv_id', $gone->pv_id)->count());
        $this->assertSame(0, PvLine::where('pv_id', $gone->pv_id)->count());
    }

    public function test_the_v2_route_saves_header_and_lines_and_deletes_a_draft(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        $line = $this->receipt();
        $url = route('v2.pv.save', $line->pv_id);

        $this->post($url, ['action' => 'save', 'i_doc_date' => '2026-03-01', 'i_vehicle_no' => 'D 99 ZZ',
            'lines' => [$line->pv_line_id => ['i_qty_purchase' => '7', 'i_qty_stock' => '7', 'qc_qty' => 'DONE', 'qc_quality_status' => 'PASS']]])
            ->assertRedirect()->assertSessionHasNoErrors();

        $pv = $this->pv($line);
        $this->assertSame('2026-03-01', $pv->i_doc_date->toDateString());
        $this->assertSame('D 99 ZZ', $pv->i_vehicle_no);
        $this->assertSame('QC_PENDING', $pv->status);
        $line->refresh();
        $this->assertEqualsWithDelta(7.0, (float) $line->c_qty_accepted, 0.0001);
        $this->assertSame('DONE', $line->qc_qty_status);

        $this->post($url, ['action' => 'delete'])->assertStatus(422);
        $this->assertSame(1, PvReceipt::where('pv_id', $line->pv_id)->count());

        $draft = $this->receipt();
        $this->post(route('v2.pv.save', $draft->pv_id), ['action' => 'delete'])->assertRedirect();
        $this->assertSame(0, PvReceipt::where('pv_id', $draft->pv_id)->count());
    }
}
