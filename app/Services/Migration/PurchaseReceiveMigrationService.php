<?php

namespace App\Services\Migration;

use App\Services\Migration\Concerns\BuildsLegacyArchive;
use Illuminate\Support\Facades\DB;

// tm2old.pur_prcv(_detail) -> tm2pur.po_receive_legacy(_detail). Arsip
// read-only, dihubungkan ke po_order_legacy via legacy_po_no (bukan po_order
// hidup). Idempoten via truncate+reload.
class PurchaseReceiveMigrationService
{
    use BuildsLegacyArchive;

    public function run(string $actor): array
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $report = ['headers' => ['inserted' => 0], 'details' => ['inserted' => 0, 'no_material_match' => 0], 'supplier_unmatched' => [], 'errors' => []];

        $supplierMap = $this->preloadMap('tm2mst', 'sup_supplier', 'legacy_vendor_code', 'id_supplier');
        $materialMap = $this->preloadMap('tm2mst', 'mat_raw', 'old_item_code', 'material_id');
        $poLegacyMap = $this->preloadMap('tm2pur', 'po_order_legacy', 'legacy_po_no', 'id_po_legacy');

        DB::connection('tm2pur')->table('po_receive_legacy_detail')->delete();
        DB::connection('tm2pur')->table('po_receive_legacy')->delete();

        $statusMap = ['RECEIVE' => 'RECEIVED'];
        $now = now();
        $headers = [];

        DB::connection('tm2old')->table('pur_prcv')->orderBy('prcv_no')->chunk(1000, function ($chunk) use (&$headers, $supplierMap, $poLegacyMap, $statusMap, $now, &$report) {
            foreach ($chunk as $r) {
                $prcvNo = trim((string) $r->prcv_no);
                if ($prcvNo === '') {
                    continue;
                }
                $supCode = trim((string) $r->supplier_code);
                $supplierId = $supplierMap[$supCode] ?? null;
                if ($supplierId === null && $supCode !== '') {
                    $report['supplier_unmatched'][$supCode] = ($report['supplier_unmatched'][$supCode] ?? 0) + 1;
                }
                $poNo = trim((string) $r->po_no);
                $rawStatus = trim((string) $r->doc_status);

                $headers[$prcvNo] = [
                    'legacy_prcv_no' => $prcvNo,
                    'prcv_date' => $r->prcv_date,
                    'supplier_id' => $supplierId,
                    'po_legacy_id' => $poLegacyMap[$poNo] ?? null,
                    'legacy_po_no' => $poNo !== '' ? $poNo : null,
                    'legacy_status' => $rawStatus !== '' ? $rawStatus : null,
                    'doc_status_normalized' => $statusMap[$rawStatus] ?? 'UNKNOWN',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'created_by' => 'migration:tm2old.pur_prcv',
                ];
            }
        });
        $report['headers']['inserted'] = $this->insertChunked('tm2pur', 'po_receive_legacy', array_values($headers));

        $receiveIdMap = DB::connection('tm2pur')->table('po_receive_legacy')->pluck('id_receive_legacy', 'legacy_prcv_no')->all();

        $details = [];
        DB::connection('tm2old')->table('pur_prcv_detail')->orderBy('prcv_detail_id')->chunk(2000, function ($chunk) use (&$details, $receiveIdMap, $materialMap, $now, &$report) {
            foreach ($chunk as $d) {
                $receiveLegacyId = $receiveIdMap[trim((string) $d->prcv_no)] ?? null;
                if ($receiveLegacyId === null) {
                    continue;
                }
                $itemCode = trim((string) $d->item_code);
                $materialId = $materialMap[$itemCode] ?? null;
                if ($materialId === null) {
                    $report['details']['no_material_match']++;
                }
                $details[] = [
                    'receive_legacy_id' => $receiveLegacyId,
                    'legacy_prcv_detail_id' => $this->nullableId($d->prcv_detail_id),
                    'legacy_po_detail_id' => $this->nullableId($d->po_detail_id),
                    'material_id' => $materialId,
                    'legacy_item_code' => $itemCode !== '' ? $itemCode : null,
                    'qty_receive' => $d->qty_receive,
                    'uom_receive' => $this->blank($d->uom_receive),
                    'unit_price' => $d->unit_price,
                    'amount' => $d->amount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        });
        $report['details']['inserted'] = $this->insertChunked('tm2pur', 'po_receive_legacy_detail', $details, 2000);

        arsort($report['supplier_unmatched']);

        return $report;
    }
}
