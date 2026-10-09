<?php

namespace Tests\Feature;

use App\Models\Inventory\InvShortageRequest;
use App\Models\Pur\PoLine;
use App\Models\Sys\SecUser;
use App\Services\Pur\PvBuilderService;
use App\Services\Pur\PvPostingService;
use App\Services\Pur\ShortageDetector;
use App\Services\Pur\ShortageRequestService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

// Kurang kirim (Q-13..Q-16, D14, D19): deteksi (output + waste standar), bukti hitung ulang, konfirmasi PPIC yang mengoreksi penerimaan, PO, jurnal dan stok,
// kirim ke pemasok, tolak / lewat jendela menutup baris PO di qty terima. Jalankan lewat tools/test-ddt.ps1 (ke ddt*). Penerimaan 10 satuan diposting
// sungguhan lewat PvPostingService; semuanya (dokumen stok, kartu, jurnal, PO, permintaan, saldo bahan) dibersihkan di tearDown().
class ShortageRequestTest extends ShortageTestCase
{
    public function test_detector_applies_the_standard_waste_and_the_tolerance(): void
    {
        $this->assertSame(['candidate' => 3.0, 'tol_qty' => 0.5, 'over_tolerance' => true], ShortageDetector::evaluate(10, 6, 1, 5));
        $this->assertFalse(ShortageDetector::evaluate(10, 6, 3.6, 5)['over_tolerance'], '0.4 short is inside 5% of 10.');
        $this->assertSame(0.0, ShortageDetector::evaluate(10, 9, 2, 5)['candidate'], 'Output + waste above the issue is never a shortage.');
        $this->assertSame(5.0, ShortageDetector::tolerancePct(null));
    }

    public function test_request_is_refused_when_output_plus_standard_waste_cover_the_issue_or_the_gap_is_within_tolerance(): void
    {
        $line = $this->postedReceiptIssuedToWo();

        foreach ([[10, 1, 'usage variance'], [6, 3.7, 'tolerance']] as [$output, $waste, $needle]) {
            try {
                $this->request($line, $output, $waste);
                $this->fail('No shortage request should be made.');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsStringIgnoringCase($needle, $e->getMessage());
            }
        }
        $this->assertSame(0, InvShortageRequest::query()->where('created_by', self::BY)->count());
    }

    public function test_confirm_needs_the_recount_then_corrects_the_receipt_po_and_stock_and_a_refusal_closes_the_po_line(): void
    {
        $line = $this->postedReceiptIssuedToWo();
        $req = $this->request($line);
        $this->assertSame(['SUPPLIER', 'DRAFT', 3.0, 10.0], [$req->source_type, $req->status, (float) $req->qty_candidate, (float) $req->qty_issued_net]);
        $this->assertMatchesRegularExpression('/^SR\d{2}\d{5}$/', $req->request_no);

        try {
            ShortageRequestService::confirm($req, 'johiwan');
            $this->fail('A confirmation without the recount must be refused.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('recount', $e->getMessage());
        }
        $nobody = SecUser::where('is_admin', 0)->get()->first(fn (SecUser $u) => ! $u->isOpsManagerOrAbove() && ! $u->hasGroup('MGR_INV', 'WH_HEAD'));
        try {
            ShortageRequestService::recount($req, 7, 7.2, 3, 'Counted again on the pallet', $nobody->login);
            $this->fail('Only the head of the warehouse may recount.');
        } catch (InvalidArgumentException) {
            $this->assertNull($req->fresh()->recount_at);
        }
        try {
            ShortageRequestService::recount($req, 7, null, 4, 'Counted again on the pallet', 'johiwan');
            $this->fail('The proven shortage cannot be more than the suspected one.');
        } catch (InvalidArgumentException) {
            $this->assertNull($req->fresh()->recount_at);
        }

        ShortageRequestService::recount($req, 7, 7.2, 3, 'Counted again on the pallet, 3 short', 'johiwan');
        $confirmed = ShortageRequestService::confirm($req->fresh(), 'johiwan');
        $this->assertSame('CONFIRMED', $confirmed->status);
        $this->assertNull($confirmed->stock_warning, 'Journal and stock corrections went through without a warning.');
        $this->assertSame(1, DB::connection('tm2acc')->table('fin_voucher')->where('source_type', 'PV_SHORT')->where('source_id', $line->pv_id)->count(), 'The GR/IR correction journal is posted.');

        $line = $line->fresh();
        $this->assertEqualsWithDelta(7.0, (float) $line->i_qty_purchase, 0.0001);
        $this->assertEqualsWithDelta(7.0, (float) $line->c_qty_accepted, 0.0001);
        $this->assertEqualsWithDelta(3.0, (float) $line->c_qty_short, 0.0001);
        $poLine = PoLine::findOrFail($line->po_line_id);
        $this->assertEqualsWithDelta(7.0, (float) $poLine->c_qty_received, 0.0001);
        $this->assertSame('PARTIALLY_RECEIVED', $poLine->c_line_status, 'The PO line is open again for the backorder.');
        $this->assertSame('PARTIALLY_RECEIVED', $this->po->fresh()->status);
        $this->assertTrue(ShortageRequestService::hasCorrections($line->receipt()->first()));
        $this->assertFalse(PvPostingService::cancel($line->receipt()->first()->fresh(), 'Cancel after the correction')['ok'], 'A corrected receipt cannot be cancelled as a whole.');
        $this->assertEqualsWithDelta(7.0, $this->stockBalance(), 0.0001, 'The stock follows the receipt correction.');

        $sent = ShortageRequestService::send($confirmed->fresh(), 'johiwan');
        $this->assertSame('SENT', $sent->status);
        $this->assertSame(now()->addDays(7)->toDateString(), $sent->window_until->toDateString());

        $refused = ShortageRequestService::refuse($sent->fresh(), 'Supplier says the pallet left complete', 'johiwan');
        $this->assertSame(['REFUSED', 'REFUSED', true], [$refused->status, $refused->outcome, (bool) $refused->debit_note_required]);
        $poLine = PoLine::findOrFail($line->po_line_id);
        $this->assertSame(ShortageRequestService::LINE_CLOSED, $poLine->c_line_status);
        $this->assertSame('RECEIVED', $this->po->fresh()->status, 'The PO is complete at the received quantity.');
        $this->assertEmpty(PvBuilderService::openLinesForSupplier($this->po->i_supplier_id)->where('po_line_id', $poLine->po_line_id));

        $this->assertSame('CLOSED', ShortageRequestService::close($refused->fresh(), 'johiwan')->status);
    }

    public function test_delivery_of_the_shortfall_and_the_lapsed_window(): void
    {
        $line = $this->postedReceiptIssuedToWo();
        $req = $this->request($line);
        ShortageRequestService::recount($req, 7, null, 3, 'Counted again, 3 short', 'johiwan');
        ShortageRequestService::confirm($req->fresh(), 'johiwan');
        ShortageRequestService::send($req->fresh(), 'johiwan');

        $received = ShortageRequestService::receive($req->fresh(), 'Supplier sent 3 on a new delivery note', 'johiwan');
        $this->assertSame(['RECEIVED', 'DELIVERED'], [$received->status, $received->outcome]);
        $this->assertSame('CLOSED', ShortageRequestService::close($received->fresh(), 'johiwan')->status);

        // Permintaan lain yang jendela kirimnya sudah lewat ditutup seperti ditolak.
        $lapsed = $this->request($line, 5, 1);
        $lapsed->forceFill(['status' => 'SENT', 'window_until' => now()->subDay()->toDateString(), 'qty_short' => 4])->save();
        $this->assertSame(1, ShortageRequestService::closeLapsed('johiwan'));
        $this->assertSame(['REFUSED', 'WINDOW_LAPSED'], [$lapsed->fresh()->status, $lapsed->fresh()->outcome]);
    }

    public function test_without_proof_the_difference_stays_a_usage_variance(): void
    {
        $line = $this->postedReceiptIssuedToWo();
        $req = $this->request($line);

        $closed = ShortageRequestService::dismiss($req, 'Extra waste on the press, no proof of a short delivery', 'johiwan');
        $this->assertSame(['CLOSED', 'USAGE_VARIANCE'], [$closed->status, $closed->outcome]);
        $this->assertEqualsWithDelta(10.0, (float) $line->fresh()->i_qty_purchase, 0.0001, 'Nothing is corrected.');
        $this->assertFalse(ShortageRequestService::hasCorrections($line->receipt()->first()));
        $this->assertEqualsWithDelta(10.0, $this->stockBalance(), 0.0001);
    }
}
