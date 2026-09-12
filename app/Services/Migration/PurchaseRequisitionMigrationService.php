<?php

namespace App\Services\Migration;

use App\Services\Migration\Concerns\BuildsLegacyArchive;
use Illuminate\Support\Facades\DB;

// tm2old.pur_pr(_detail) -> tm2pur.pr_requisition_legacy(_detail). Arsip
// read-only utk dashboard/laporan -- BUKAN pr_requisition (alur baru punya
// approval/alloc flow sendiri, tidak cocok utk histori ScriptCase lama).
// Idempoten via truncate+reload (sama alasan dgn Service migrasi arsip lain).
class PurchaseRequisitionMigrationService
{
    use BuildsLegacyArchive;

    public function run(string $actor): array
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $report = ['headers' => ['inserted' => 0], 'details' => ['inserted' => 0, 'no_material_match' => 0], 'errors' => []];

        $materialMap = $this->preloadMap('tm2mst', 'mat_raw', 'old_item_code', 'material_id');

        DB::connection('tm2pur')->table('pr_requisition_legacy_detail')->delete();
        DB::connection('tm2pur')->table('pr_requisition_legacy')->delete();

        $statusMap = ['DRAFT' => 'DRAFT', 'APPROVE' => 'APPROVED', 'REJECT' => 'REJECTED', 'PURCHASED' => 'CLOSED'];
        $now = now();
        $headers = [];

        DB::connection('tm2old')->table('pur_pr')->orderBy('pr_no')->chunk(1000, function ($chunk) use (&$headers, $statusMap, $now) {
            foreach ($chunk as $pr) {
                $prNo = trim((string) $pr->pr_no);
                if ($prNo === '') {
                    continue;
                }
                $rawStatus = trim((string) $pr->pr_status);
                $headers[$prNo] = [
                    'legacy_pr_no' => $prNo,
                    'pr_date' => $pr->pr_date,
                    'department' => $this->blank($pr->department),
                    'legacy_status' => $rawStatus !== '' ? $rawStatus : null,
                    'doc_status_normalized' => $statusMap[$rawStatus] ?? 'UNKNOWN',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'created_by' => 'migration:tm2old.pur_pr',
                ];
            }
        });
        $report['headers']['inserted'] = $this->insertChunked('tm2pur', 'pr_requisition_legacy', array_values($headers));

        $prIdMap = DB::connection('tm2pur')->table('pr_requisition_legacy')->pluck('id_pr_legacy', 'legacy_pr_no')->all();

        $details = [];
        DB::connection('tm2old')->table('pur_pr_detail')->orderBy('pr_detail_id')->chunk(2000, function ($chunk) use (&$details, $prIdMap, $materialMap, $now, &$report) {
            foreach ($chunk as $d) {
                $prLegacyId = $prIdMap[trim((string) $d->pr_no)] ?? null;
                if ($prLegacyId === null) {
                    continue;
                }
                $itemCode = trim((string) $d->item_code);
                $materialId = $materialMap[$itemCode] ?? null;
                if ($materialId === null) {
                    $report['details']['no_material_match']++;
                }
                $details[] = [
                    'pr_legacy_id' => $prLegacyId,
                    'legacy_pr_detail_id' => $this->nullableId($d->pr_detail_id),
                    'legacy_co_detail_id' => $this->nullableId($d->co_detail_id),
                    'material_id' => $materialId,
                    'legacy_item_code' => $itemCode !== '' ? $itemCode : null,
                    'qty' => $d->qty,
                    'uom_code' => $this->blank($d->uom_code),
                    'est_unitprice' => $d->est_unitprice,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        });
        $report['details']['inserted'] = $this->insertChunked('tm2pur', 'pr_requisition_legacy_detail', $details, 2000);

        return $report;
    }
}
