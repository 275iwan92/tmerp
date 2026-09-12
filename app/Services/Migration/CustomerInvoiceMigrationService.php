<?php

namespace App\Services\Migration;

use App\Services\Migration\Concerns\BuildsLegacyArchive;
use Illuminate\Support\Facades\DB;

// tm2old.sdi_cinv(_detail) -> tm2sdi.co_invoice_legacy(_detail). Arsip
// read-only -- basis AR (remain_of_bill = sisa tagihan blm dibayar per
// snapshot sumber). sdi_cinv_prebill/print/print_detail/rcp/rcp_detail
// SENGAJA dilewati (rcp* 0 baris, prebill/print salinan cetak turunan).
class CustomerInvoiceMigrationService
{
    use BuildsLegacyArchive;

    public function run(string $actor): array
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $report = ['headers' => ['inserted' => 0], 'details' => ['inserted' => 0, 'no_product_match' => 0], 'customer_unmatched' => [], 'errors' => []];

        $customerMap = $this->preloadMap('tm2mst', 'cust_customer', 'legacy_vendor_code', 'id_customer');
        $productMap = $this->preloadMap('tm2mst', 'prod_product', 'old_item_code', 'id_product');
        $coLegacyMap = $this->preloadMap('tm2sdi', 'co_order_legacy', 'legacy_co_no', 'id_co_legacy');

        DB::connection('tm2sdi')->table('co_invoice_legacy_detail')->delete();
        DB::connection('tm2sdi')->table('co_invoice_legacy')->delete();

        $statusMap = ['INVOICED' => 'CONFIRMED', 'DRAFT' => 'DRAFT'];
        $now = now();
        $headers = [];

        DB::connection('tm2old')->table('sdi_cinv')->orderBy('cinv_no')->chunk(1000, function ($chunk) use (&$headers, $customerMap, $coLegacyMap, $statusMap, $now, &$report) {
            foreach ($chunk as $c) {
                $cinvNo = trim((string) $c->cinv_no);
                if ($cinvNo === '') {
                    continue;
                }
                $custCode = trim((string) $c->customer_code);
                $customerId = $customerMap[$custCode] ?? null;
                if ($customerId === null && $custCode !== '') {
                    $report['customer_unmatched'][$custCode] = ($report['customer_unmatched'][$custCode] ?? 0) + 1;
                }
                $coNo = trim((string) $c->co_no);
                $rawStatus = trim((string) $c->doc_status);

                $headers[$cinvNo] = [
                    'legacy_cinv_no' => $cinvNo,
                    'cinv_date' => $c->cinv_date,
                    'customer_id' => $customerId,
                    'co_legacy_id' => $coLegacyMap[$coNo] ?? null,
                    'legacy_co_no' => $coNo !== '' ? $coNo : null,
                    'subtotal' => $c->subtotal,
                    'disc_amount' => $c->disc_amount,
                    'ppn_amount' => $c->ppn_amount,
                    'grand_total' => $c->grand_total,
                    'dp_amount' => $c->dp_amount,
                    'remain_of_bill' => $c->remain_of_bill,
                    'legacy_status' => $rawStatus !== '' ? $rawStatus : null,
                    'doc_status_normalized' => $statusMap[$rawStatus] ?? 'UNKNOWN',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'created_by' => 'migration:tm2old.sdi_cinv',
                ];
            }
        });
        $report['headers']['inserted'] = $this->insertChunked('tm2sdi', 'co_invoice_legacy', array_values($headers));

        $invoiceIdMap = DB::connection('tm2sdi')->table('co_invoice_legacy')->pluck('id_invoice_legacy', 'legacy_cinv_no')->all();

        $details = [];
        DB::connection('tm2old')->table('sdi_cinv_detail')->orderBy('cinv_detail_id')->chunk(2000, function ($chunk) use (&$details, $invoiceIdMap, $productMap, $now, &$report) {
            foreach ($chunk as $d) {
                $invoiceLegacyId = $invoiceIdMap[trim((string) $d->cinv_no)] ?? null;
                if ($invoiceLegacyId === null) {
                    continue;
                }
                $itemCode = trim((string) $d->item_code);
                $productId = $productMap[$itemCode] ?? null;
                if ($productId === null) {
                    $report['details']['no_product_match']++;
                }
                $details[] = [
                    'invoice_legacy_id' => $invoiceLegacyId,
                    'legacy_cinv_detail_id' => $this->nullableId($d->cinv_detail_id),
                    'legacy_co_detail_id' => $this->nullableId($d->co_detail_id),
                    'product_id' => $productId,
                    'legacy_item_code' => $itemCode !== '' ? $itemCode : null,
                    'qty_inv' => $d->qty_inv,
                    'uom_code' => $this->blank($d->uom_code),
                    'unit_price' => $d->unit_price,
                    'qty_unitprice' => $d->qty_unitprice,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        });
        $report['details']['inserted'] = $this->insertChunked('tm2sdi', 'co_invoice_legacy_detail', $details, 2000);

        arsort($report['customer_unmatched']);

        return $report;
    }
}
