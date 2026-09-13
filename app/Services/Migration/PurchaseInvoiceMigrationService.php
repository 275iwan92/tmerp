<?php

namespace App\Services\Migration;

use App\Services\Migration\Concerns\BuildsLegacyArchive;
use Illuminate\Support\Facades\DB;

// tm2old.pur_pinv(_detail) -> tm2pur.po_invoice_legacy(_detail).
//
// Ini DASAR AP yang sebenarnya (3.414 invoice / Rp 30,4 M) dan sempat
// terlewat di gelombang migrasi pertama.
//
// PENTING soal saldo utang: pur_pinv.total_payment SELALU 0 di sumber --
// kolom mati, pola yang sama dgn sdi_cinv.remain_of_bill di sisi penjualan.
// Jadi saldo AP HARUS dihitung grand_total dikurangi realisasi pembayaran
// dari po_payment_legacy_detail, bukan dibaca dari header invoice.
// Untungnya sambungan pur_ppym_detail.pinv_no -> pur_pinv.pinv_no terisi
// 100%, sehingga perhitungan itu bisa dipertanggungjawabkan.
class PurchaseInvoiceMigrationService
{
    use BuildsLegacyArchive;

    public function run(string $actor): array
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $report = [
            'headers' => ['inserted' => 0],
            'details' => ['inserted' => 0, 'no_material_match' => 0],
            'payment_links' => ['resolved' => 0, 'unresolved' => 0],
            'supplier_unmatched' => [],
            'suspect_dates' => [],
            'errors' => [],
        ];

        $supplierMap = $this->preloadMap('tm2mst', 'sup_supplier', 'legacy_vendor_code', 'id_supplier');
        $materialMap = $this->preloadMap('tm2mst', 'mat_raw', 'old_item_code', 'material_id');

        DB::connection('tm2pur')->table('po_invoice_legacy_detail')->delete();
        DB::connection('tm2pur')->table('po_invoice_legacy')->delete();

        $statusMap = ['INV' => 'INVOICED', 'DRAFT' => 'DRAFT', 'PAID' => 'PAID', 'CANCEL' => 'CANCELLED'];
        $now = now();
        $headers = [];

        DB::connection('tm2old')->table('pur_pinv')->orderBy('pinv_no')
            ->chunk(1000, function ($chunk) use (&$headers, $supplierMap, $statusMap, $now, &$report) {
                foreach ($chunk as $inv) {
                    $no = trim((string) $inv->pinv_no);
                    if ($no === '') {
                        continue;
                    }

                    $supCode = trim((string) $inv->supplier_code);
                    $supplierId = $supplierMap[$supCode] ?? null;
                    if ($supplierId === null && $supCode !== '') {
                        $report['supplier_unmatched'][$supCode] = ($report['supplier_unmatched'][$supCode] ?? 0) + 1;
                    }

                    // Tanggal di luar nalar dilaporkan, TIDAK ditebak/dikoreksi
                    // diam-diam -- nomor dokumennya sendiri ambigu.
                    if ($inv->pinv_date !== null && ($inv->pinv_date < '2010-01-01' || $inv->pinv_date > '2027-12-31')) {
                        $report['suspect_dates'][] = $no . ' => ' . $inv->pinv_date;
                    }

                    $rawStatus = trim((string) $inv->doc_status);

                    $headers[$no] = [
                        'legacy_pinv_no' => $no,
                        'pinv_date' => $inv->pinv_date,
                        'supplier_id' => $supplierId,
                        'payment_term' => $this->blank($inv->payment_code),
                        'subtotal' => $inv->subtotal,
                        'disc_amount' => $inv->disc_amount,
                        'ppn_amount' => $inv->ppn_amount,
                        'grand_total' => $inv->grand_total,
                        'legacy_status' => $rawStatus !== '' ? $rawStatus : null,
                        'doc_status_normalized' => $statusMap[$rawStatus] ?? 'UNKNOWN',
                        'created_at' => $now,
                        'updated_at' => $now,
                        'created_by' => 'migration:tm2old.pur_pinv',
                    ];
                }
            });

        $report['headers']['inserted'] = $this->insertChunked('tm2pur', 'po_invoice_legacy', array_values($headers));

        $invIdMap = DB::connection('tm2pur')->table('po_invoice_legacy')
            ->pluck('id_pinv_legacy', 'legacy_pinv_no')->all();

        $details = [];
        DB::connection('tm2old')->table('pur_pinv_detail')->orderBy('pinv_detail_id')
            ->chunk(2000, function ($chunk) use (&$details, $invIdMap, $materialMap, $now, &$report) {
                foreach ($chunk as $d) {
                    $invId = $invIdMap[trim((string) $d->pinv_no)] ?? null;
                    if ($invId === null) {
                        continue;
                    }

                    $itemCode = trim((string) $d->item_code);
                    $materialId = $materialMap[$itemCode] ?? null;
                    if ($materialId === null) {
                        $report['details']['no_material_match']++;
                    }

                    $details[] = [
                        'pinv_legacy_id' => $invId,
                        'legacy_pinv_detail_id' => $this->nullableId($d->pinv_detail_id),
                        'legacy_po_detail_id' => $this->nullableId($d->po_detail_id),
                        'material_id' => $materialId,
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
        $report['details']['inserted'] = $this->insertChunked('tm2pur', 'po_invoice_legacy_detail', $details, 2000);

        // Sambungkan baris pembayaran yang sudah ada ke invoice-nya. Dikerjakan
        // di sini (bukan di PurchasePaymentMigrationService) karena baru mungkin
        // setelah po_invoice_legacy terisi -- urutan pipeline menjamin ini.
        $payments = DB::connection('tm2pur')->table('po_payment_legacy_detail')
            ->whereNotNull('legacy_pinv_no')->get(['id_payment_legacy_detail', 'legacy_pinv_no']);

        foreach ($payments->chunk(500) as $batch) {
            $cases = [];
            $ids = [];
            foreach ($batch as $row) {
                $invId = $invIdMap[trim((string) $row->legacy_pinv_no)] ?? null;
                if ($invId === null) {
                    $report['payment_links']['unresolved']++;

                    continue;
                }
                $cases[] = 'WHEN ' . (int) $row->id_payment_legacy_detail . ' THEN ' . (int) $invId;
                $ids[] = (int) $row->id_payment_legacy_detail;
                $report['payment_links']['resolved']++;
            }
            if ($ids) {
                DB::connection('tm2pur')->statement(
                    'UPDATE po_payment_legacy_detail SET pinv_legacy_id = CASE id_payment_legacy_detail '
                    . implode(' ', $cases) . ' END WHERE id_payment_legacy_detail IN (' . implode(',', $ids) . ')'
                );
            }
        }

        arsort($report['supplier_unmatched']);

        return $report;
    }
}
