<?php

namespace App\Services\Migration;

use App\Models\Legacy\PurPo;
use App\Models\Legacy\PurPoDetail;
use App\Models\Legacy\PurPoV2;
use App\Models\Master\MatRaw;
use App\Models\Master\SupSupplier;
use App\Services\Migration\Concerns\BuildsLegacyArchive;
use Illuminate\Support\Facades\DB;

// Engine migrasi tm2old.pur_po(_v2)+pur_po_detail -> tm2pur.po_order_legacy(_detail).
// Dipicu manual dari tab "Migrasi Data Legacy" (Setup, admin-only).
//
// Header dari pur_po_v2 (rincian pajak lebih lengkap -- ppn/pph22/pph23/dst),
// tapi baris line-nya TIDAK dari pur_po_detail_v2 (0 baris, sudah dicek
// langsung ke DB -- kolom itu tidak pernah diisi sistem lama) melainkan dari
// pur_po_detail (v1), di-join manual by po_no=doc_no karena kedua tabel
// memang mewakili dokumen yang sama (v2 = header hasil "upgrade" skema di
// atas v1, bukan proses bisnis terpisah -- sudah diverifikasi: po_no v1 dan
// doc_no v2 nilainya sama persis utk PO yang sama).
//
// po_status APA ADANYA diambil dari pur_po (v1) krn pur_po_v2.stat_doc isinya
// tidak konsisten (kadang teks bebas spt "UPDATE By TEST").
//
// SENGAJA bukan po_order/po_line: po_order tidak punya blocker arsitektural
// spt co_order, tapi 22.976 PO lama statusnya historis dari alur ScriptCase
// yang sudah tidak berlaku -- tabel arsip terpisah ini tujuannya dashboard/
// laporan (mis. "total AP ke Supplier X"), bukan record yang ikut alur
// approval/receiving hidup di po_order.
//
// Idempoten via TRUNCATE+reload, sama alasannya dgn CustomerOrderMigrationService.
class PurchaseOrderMigrationService
{
    use BuildsLegacyArchive;

    public function run(string $actor): array
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(600);

        $report = [
            'headers' => ['inserted' => 0],
            'details' => ['inserted' => 0, 'no_material_match' => 0],
            'supplier_unmatched' => [],
            'errors' => [],
        ];

        $supplierMap = SupSupplier::query()
            ->whereNotNull('legacy_vendor_code')
            ->pluck('id_supplier', 'legacy_vendor_code')
            ->all();

        $materialMap = MatRaw::query()
            ->whereNotNull('old_item_code')
            ->pluck('material_id', 'old_item_code')
            ->all();

        // pur_po (v1) sumber po_status asli, keyed by po_no ditrim -- dipakai
        // resolve status krn pur_po_v2.stat_doc tidak konsisten (lihat komentar kelas).
        $v1StatusMap = PurPo::query()->pluck('po_status', 'po_no')
            ->mapWithKeys(fn ($status, $poNo) => [trim((string) $poNo) => trim((string) $status)])
            ->all();

        DB::connection('tm2pur')->table('po_order_legacy_detail')->delete();
        DB::connection('tm2pur')->table('po_order_legacy')->delete();

        $statusMap = [
            'DRAFT' => 'DRAFT',
            'APPROVE' => 'APPROVED',
            'CLOSE' => 'CLOSED',
            'DNOTE' => 'CLOSED',
            'PRINT' => 'APPROVED',
            'REJECT' => 'CANCELLED',
        ];

        $now = now();

        // Dikumpulkan ke array asosiatif keyed by legacy_po_no dulu (bukan
        // langsung insert per chunk) -- pur_po_v2.doc_no ternyata punya 2
        // pasang duplikat (ditemukan saat run pertama, tabrakan unique
        // constraint). Duplikat diselesaikan di sini: baris doc_id terbesar
        // (paling akhir/dianggap paling update) yang menang, bukan dibiarkan
        // crash atau di-insertOrIgnore diam-diam (yang menang jadi tidak
        // terkendali gantung urutan fisik tabel).
        $headerByPoNo = [];

        PurPoV2::query()->orderBy('doc_id')->chunk(1000, function ($chunk) use (&$headerByPoNo, $supplierMap, $v1StatusMap, $statusMap, $now, &$report) {
            foreach ($chunk as $po) {
                $poNo = trim((string) $po->doc_no);
                if ($poNo === '') {
                    continue;
                }

                $supCode = trim((string) $po->supplier_code);
                $supplierId = $supplierMap[$supCode] ?? null;
                if ($supplierId === null && $supCode !== '') {
                    $report['supplier_unmatched'][$supCode] = ($report['supplier_unmatched'][$supCode] ?? 0) + 1;
                }

                $rawStatus = $v1StatusMap[$poNo] ?? '';

                $headerByPoNo[$poNo] = [
                    'legacy_po_no' => $poNo,
                    'po_date' => $po->doc_date,
                    'supplier_id' => $supplierId,
                    'subtotal' => $po->subtotal,
                    'disc_amount' => $po->disc_amount,
                    'ppn_amount' => $po->ppn_amount,
                    'grand_total' => $po->grand_total,
                    'legacy_status' => $rawStatus !== '' ? $rawStatus : null,
                    'doc_status_normalized' => $statusMap[$rawStatus] ?? 'UNKNOWN',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'created_by' => 'migration:tm2old.pur_po_v2',
                ];
            }
        });

        foreach (array_chunk($headerByPoNo, 1000) as $batch) {
            DB::connection('tm2pur')->table('po_order_legacy')->insert($batch);
            $report['headers']['inserted'] += count($batch);
        }

        $poIdMap = DB::connection('tm2pur')->table('po_order_legacy')->pluck('id_po_legacy', 'legacy_po_no')->all();

        $detailBuffer = [];
        PurPoDetail::query()->orderBy('po_detail_id')->chunk(2000, function ($chunk) use (&$detailBuffer, $poIdMap, $materialMap, $now, &$report) {
            foreach ($chunk as $d) {
                $poNo = trim((string) $d->po_no);
                $poLegacyId = $poIdMap[$poNo] ?? null;
                if ($poLegacyId === null) {
                    continue; // po_no di detail tidak ketemu headernya (PO cuma ada di v1, belum sempat "diupgrade" ke v2) -- dilewati.
                }

                $itemCode = trim((string) $d->item_code);
                $materialId = $materialMap[$itemCode] ?? null;
                if ($materialId === null) {
                    $report['details']['no_material_match']++;
                }

                $detailBuffer[] = [
                    'po_legacy_id' => $poLegacyId,
                    'legacy_po_detail_id' => $this->nullableId($d->po_detail_id),
                    'legacy_pr_detail_id' => $this->nullableId($d->pr_detail_id),
                    'legacy_co_detail_id' => $this->nullableId($d->co_detail_id),
                    'material_id' => $materialId,
                    'legacy_item_code' => $itemCode !== '' ? $itemCode : null,
                    'qty' => $d->qty,
                    'uom_code' => $this->blank($d->uom_code),
                    'unit_price' => $d->unit_price,
                    'qty_unitprice' => $d->qty_unitprice,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::connection('tm2pur')->table('po_order_legacy_detail')->insert($detailBuffer);
            $report['details']['inserted'] += count($detailBuffer);
            $detailBuffer = [];
        });

        arsort($report['supplier_unmatched']);

        return $report;
    }
}
