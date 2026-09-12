<?php

namespace App\Services\Migration;

use App\Services\Migration\Concerns\BuildsLegacyArchive;
use Illuminate\Support\Facades\DB;

// tm2old.sdi_dnote(_detail) -> tm2sdi.co_delivery_legacy(_detail). Arsip
// read-only, dihubungkan ke co_order_legacy via legacy_co_no (bukan
// co_order_delivery hidup yg terikat alur CPDEL baru).
class DeliveryNoteMigrationService
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

        DB::connection('tm2sdi')->table('co_delivery_legacy_detail')->delete();
        DB::connection('tm2sdi')->table('co_delivery_legacy')->delete();

        $statusMap = ['SHIPPING' => 'SHIPPED', 'DRAFT' => 'DRAFT'];
        $now = now();
        $headers = [];

        DB::connection('tm2old')->table('sdi_dnote')->orderBy('dnote_no')->chunk(1000, function ($chunk) use (&$headers, $customerMap, $coLegacyMap, $statusMap, $now, &$report) {
            foreach ($chunk as $d) {
                $dnoteNo = trim((string) $d->dnote_no);
                if ($dnoteNo === '') {
                    continue;
                }
                $custCode = trim((string) $d->customer_code);
                $customerId = $customerMap[$custCode] ?? null;
                if ($customerId === null && $custCode !== '') {
                    $report['customer_unmatched'][$custCode] = ($report['customer_unmatched'][$custCode] ?? 0) + 1;
                }
                $coNo = trim((string) $d->co_no);
                $rawStatus = trim((string) $d->dnote_status);

                $headers[$dnoteNo] = [
                    'legacy_dnote_no' => $dnoteNo,
                    'dnote_date' => $d->dnote_date,
                    'customer_id' => $customerId,
                    'co_legacy_id' => $coLegacyMap[$coNo] ?? null,
                    'legacy_co_no' => $coNo !== '' ? $coNo : null,
                    'grand_total' => $d->grand_total,
                    'legacy_status' => $rawStatus !== '' ? $rawStatus : null,
                    'doc_status_normalized' => $statusMap[$rawStatus] ?? 'UNKNOWN',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'created_by' => 'migration:tm2old.sdi_dnote',
                ];
            }
        });
        $report['headers']['inserted'] = $this->insertChunked('tm2sdi', 'co_delivery_legacy', array_values($headers));

        $deliveryIdMap = DB::connection('tm2sdi')->table('co_delivery_legacy')->pluck('id_delivery_legacy', 'legacy_dnote_no')->all();

        $details = [];
        DB::connection('tm2old')->table('sdi_dnote_detail')->orderBy('dnote_detail_id')->chunk(2000, function ($chunk) use (&$details, $deliveryIdMap, $productMap, $now, &$report) {
            foreach ($chunk as $d) {
                $deliveryLegacyId = $deliveryIdMap[trim((string) $d->dnote_no)] ?? null;
                if ($deliveryLegacyId === null) {
                    continue;
                }
                $itemCode = trim((string) $d->item_code);
                $productId = $productMap[$itemCode] ?? null;
                if ($productId === null) {
                    $report['details']['no_product_match']++;
                }
                $details[] = [
                    'delivery_legacy_id' => $deliveryLegacyId,
                    'legacy_dnote_detail_id' => $this->nullableId($d->dnote_detail_id),
                    'legacy_co_detail_id' => $this->nullableId($d->co_detail_id),
                    'product_id' => $productId,
                    'legacy_item_code' => $itemCode !== '' ? $itemCode : null,
                    'qty_ship' => $d->qty_ship,
                    'uom_code' => $this->blank($d->uom_code),
                    'unit_price' => $d->unit_price,
                    'qty_unitprice' => $d->qty_unitprice,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        });
        $report['details']['inserted'] = $this->insertChunked('tm2sdi', 'co_delivery_legacy_detail', $details, 2000);

        arsort($report['customer_unmatched']);

        return $report;
    }
}
