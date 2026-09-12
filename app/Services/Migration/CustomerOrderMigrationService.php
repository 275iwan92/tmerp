<?php

namespace App\Services\Migration;

use App\Models\Legacy\SdiCo;
use App\Models\Legacy\SdiCoDetail;
use App\Models\Master\CustCustomer;
use App\Models\Master\ProdProduct;
use App\Services\Migration\Concerns\BuildsLegacyArchive;
use Illuminate\Support\Facades\DB;

// Engine migrasi tm2old.sdi_co(_detail) -> tm2sdi.co_order_legacy(_detail).
// Dipicu manual dari tab "Migrasi Data Legacy" (Setup, admin-only).
//
// SENGAJA bukan co_order/co_order_item: co_order.quotation_id NOT NULL (1 CO
// = 1 Quotation) karena mewakili alur baru InqProd->Quotation->CO. 20.669 CO
// lama tidak pernah punya lineage itu -- co_order_legacy adalah arsip terpisah
// read-only, tujuannya dashboard/laporan (mis. "total belanja Customer X"),
// bukan record yang ikut alur CPDEL/Delivery hidup.
//
// Idempoten via TRUNCATE+reload (bukan diff per baris) -- sumber (tm2old)
// immutable & arsip ini bukan tempat user mengedit apa pun, jadi refresh penuh
// tiap running lebih simpel & tetap aman drpd cek 20 ribu baris satu-satu.
class CustomerOrderMigrationService
{
    use BuildsLegacyArchive;

    public function run(string $actor): array
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(600);

        $report = [
            'headers' => ['inserted' => 0],
            'details' => ['inserted' => 0, 'no_product_match' => 0],
            'customer_unmatched' => [],
            'errors' => [],
        ];

        // Preload crosswalk sekali di memori -- hindari N+1 query per baris
        // (881 customer, ~14rb produk, keduanya muat wajar di RAM).
        $customerMap = CustCustomer::query()
            ->whereNotNull('legacy_vendor_code')
            ->pluck('id_customer', 'legacy_vendor_code')
            ->all();

        $productMap = ProdProduct::query()
            ->whereNotNull('old_item_code')
            ->pluck('id_product', 'old_item_code')
            ->all();

        DB::connection('tm2sdi')->table('co_order_legacy_detail')->delete();
        DB::connection('tm2sdi')->table('co_order_legacy')->delete();

        $statusMap = [
            'APPROVED' => 'CONFIRMED',
            'DRAFT' => 'DRAFT',
            'CANCELLED' => 'CANCELLED',
        ];

        $headerBuffer = [];
        $now = now();

        SdiCo::query()->orderBy('co_no')->chunk(1000, function ($chunk) use (&$headerBuffer, $customerMap, $statusMap, $actor, $now, &$report) {
            foreach ($chunk as $co) {
                $coNo = trim((string) $co->co_no);
                if ($coNo === '') {
                    continue;
                }

                $custCode = trim((string) $co->customer_code);
                $customerId = $customerMap[$custCode] ?? null;
                if ($customerId === null && $custCode !== '') {
                    $report['customer_unmatched'][$custCode] = ($report['customer_unmatched'][$custCode] ?? 0) + 1;
                }

                $rawStatus = trim((string) $co->co_status);

                $headerBuffer[] = [
                    'legacy_co_no' => $coNo,
                    'co_date' => $co->co_date,
                    'customer_id' => $customerId,
                    'customer_po_no' => $this->blank($co->po_no_customer),
                    'sales_person' => $this->blank($co->sales_by),
                    'subtotal' => $co->subtotal,
                    'disc_amount' => $co->disc_amount,
                    'ppn_amount' => $co->ppn_amount,
                    'grand_total' => $co->grand_total,
                    'legacy_status' => $rawStatus !== '' ? $rawStatus : null,
                    'doc_status_normalized' => $statusMap[$rawStatus] ?? 'UNKNOWN',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'created_by' => 'migration:tm2old.sdi_co',
                ];
            }

            DB::connection('tm2sdi')->table('co_order_legacy')->insert($headerBuffer);
            $report['headers']['inserted'] += count($headerBuffer);
            $headerBuffer = [];
        });

        // Peta legacy_co_no -> id_co_legacy baru, utk isi FK di detail.
        $coIdMap = DB::connection('tm2sdi')->table('co_order_legacy')->pluck('id_co_legacy', 'legacy_co_no')->all();

        $detailBuffer = [];
        SdiCoDetail::query()->orderBy('co_detail_id')->chunk(2000, function ($chunk) use (&$detailBuffer, $coIdMap, $productMap, $now, &$report) {
            foreach ($chunk as $d) {
                $coNo = trim((string) $d->co_no);
                $coLegacyId = $coIdMap[$coNo] ?? null;
                if ($coLegacyId === null) {
                    continue; // co_no di detail tidak ketemu headernya -- data yatim di sumber, dilewati.
                }

                $itemCode = trim((string) $d->item_code);
                $productId = $productMap[$itemCode] ?? null;
                if ($productId === null) {
                    $report['details']['no_product_match']++;
                }

                $detailBuffer[] = [
                    'co_legacy_id' => $coLegacyId,
                    'legacy_co_detail_id' => $this->nullableId($d->co_detail_id),
                    'product_id' => $productId,
                    'legacy_item_code' => $itemCode !== '' ? $itemCode : null,
                    'qty' => $d->qty,
                    'uom_code' => $this->blank($d->uom_code),
                    'unit_price' => $d->unit_price,
                    'qty_unitprice' => $d->qty_unitprice,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::connection('tm2sdi')->table('co_order_legacy_detail')->insert($detailBuffer);
            $report['details']['inserted'] += count($detailBuffer);
            $detailBuffer = [];
        });

        arsort($report['customer_unmatched']);

        return $report;
    }
}
