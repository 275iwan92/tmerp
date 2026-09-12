<?php

namespace App\Services\Migration;

use App\Services\Migration\Concerns\BuildsLegacyArchive;
use Illuminate\Support\Facades\DB;

// tm2old.pur_ppym(_detail) -> tm2pur.po_payment_legacy(_detail). Arsip
// read-only -- basis "sudah dibayar berapa" utk AP. pinv_no (Purchase Invoice)
// disimpan sbg teks (legacy_pinv_no), BUKAN FK -- pur_pinv belum dimigrasi.
class PurchasePaymentMigrationService
{
    use BuildsLegacyArchive;

    public function run(string $actor): array
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $report = ['headers' => ['inserted' => 0], 'details' => ['inserted' => 0], 'supplier_unmatched' => [], 'errors' => []];

        $supplierMap = $this->preloadMap('tm2mst', 'sup_supplier', 'legacy_vendor_code', 'id_supplier');

        DB::connection('tm2pur')->table('po_payment_legacy_detail')->delete();
        DB::connection('tm2pur')->table('po_payment_legacy')->delete();

        $statusMap = ['PAID' => 'PAID', 'DRAFT' => 'DRAFT'];
        $now = now();
        $headers = [];

        DB::connection('tm2old')->table('pur_ppym')->orderBy('ppym_no')->chunk(1000, function ($chunk) use (&$headers, $supplierMap, $statusMap, $now, &$report) {
            foreach ($chunk as $p) {
                $ppymNo = trim((string) $p->ppym_no);
                if ($ppymNo === '') {
                    continue;
                }
                $supCode = trim((string) $p->supplier_code);
                $supplierId = $supplierMap[$supCode] ?? null;
                if ($supplierId === null && $supCode !== '') {
                    $report['supplier_unmatched'][$supCode] = ($report['supplier_unmatched'][$supCode] ?? 0) + 1;
                }
                $rawStatus = trim((string) $p->doc_status);

                $headers[$ppymNo] = [
                    'legacy_ppym_no' => $ppymNo,
                    'ppym_date' => $p->ppym_date,
                    'supplier_id' => $supplierId,
                    'grand_total' => $p->grand_total,
                    'legacy_status' => $rawStatus !== '' ? $rawStatus : null,
                    'doc_status_normalized' => $statusMap[$rawStatus] ?? 'UNKNOWN',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'created_by' => 'migration:tm2old.pur_ppym',
                ];
            }
        });
        $report['headers']['inserted'] = $this->insertChunked('tm2pur', 'po_payment_legacy', array_values($headers));

        $paymentIdMap = DB::connection('tm2pur')->table('po_payment_legacy')->pluck('id_payment_legacy', 'legacy_ppym_no')->all();

        $details = [];
        DB::connection('tm2old')->table('pur_ppym_detail')->orderBy('ppym_detail_id')->chunk(2000, function ($chunk) use (&$details, $paymentIdMap, $now) {
            foreach ($chunk as $d) {
                $paymentLegacyId = $paymentIdMap[trim((string) $d->ppym_no)] ?? null;
                if ($paymentLegacyId === null) {
                    continue;
                }
                $details[] = [
                    'payment_legacy_id' => $paymentLegacyId,
                    'legacy_pinv_no' => $this->blank($d->pinv_no),
                    'paid_amount' => $d->paid_amount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        });
        $report['details']['inserted'] = $this->insertChunked('tm2pur', 'po_payment_legacy_detail', $details, 2000);

        arsort($report['supplier_unmatched']);

        return $report;
    }
}
