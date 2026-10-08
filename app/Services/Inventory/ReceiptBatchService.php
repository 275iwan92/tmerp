<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Pur\PvLine;
use App\Models\Pur\PvReceipt;
use Illuminate\Support\Facades\DB;

// Batch lahir dari penerimaan barang yang diposting (Q-02, Q-03): satu batch per item + lot supplier (BatchService::ensure).
//   Baris URGENT dengan QC mutu masih PENDING  -> batch CONDITIONAL (boleh dipakai, bertanda, sampling menyusul lewat Conditional Stock).
//   Baris biasa -> QC mutu sudah diputuskan sebelum posting (PvPostingService::blockers), jadi batch RELEASED; keputusan sampling di dok
//   dicatat otomatis (SAMPLING_DOCK / RELEASED) supaya riwayat lulus supplier terisi.
// Baris dokumen stok dan kartu stok yang dibuat penerimaan itu diberi batch_id (hanya yang masih kosong).
final class ReceiptBatchService
{
    /** @return list<string> peringatan; kegagalan membuat batch tidak membatalkan posting penerimaan yang sudah terjadi */
    public static function forPostedReceipt(PvReceipt $pv, string $login): array
    {
        $warnings = [];
        $pv->loadMissing('lines');
        foreach ($pv->lines as $line) {
            if (! $line->is_stock || ! $line->item_id || (float) $line->c_qty_accepted <= 0) {
                continue;
            }

            try {
                self::forLine($pv, $line, $login);
            } catch (\Throwable $e) {
                $warnings[] = tf('Batch for line :line was not created: :error', ['line' => $line->line_no, 'error' => $e->getMessage()]);
            }
        }

        return $warnings;
    }

    private static function forLine(PvReceipt $pv, PvLine $line, string $login): void
    {
        $conditional = $line->is_urgent && $line->qc_quality_status === 'PENDING';
        $existing = BatchService::findByLot('RM', (int) $line->item_id, $line->i_lot_no);
        $batch = $existing ?? BatchService::create('RM', (int) $line->item_id, $line->i_lot_no, [
            'qc_status' => $conditional ? 'CONDITIONAL' : 'RELEASED', 'supplier_id' => $pv->i_supplier_id, 'item_code' => $line->c_item_code, 'item_name' => mb_substr((string) $line->i_description, 0, 255),
            'certification' => $line->i_certification ?: 'NONE', 'food_grade' => (bool) $line->i_food_grade], $login);

        DB::connection('tm2pur')->table('pv_line')->where('pv_line_id', $line->pv_line_id)->update(['i_batch_id' => $batch->id_batch]);

        if ($pv->rm_stock_doc_id) {
            $lines = DB::connection('tm2inv')->table('rm_stock_d')->where('doc_id', $pv->rm_stock_doc_id)->where('material_id', $line->item_id)->whereNull('batch_id')
                ->when($line->i_lot_no, fn ($q) => $q->where('lot_no', $line->i_lot_no));
            $detailIds = (clone $lines)->pluck('doc_detail_id');
            $lines->update(['batch_id' => $batch->id_batch]);
            DB::connection('tm2inv')->table('rm_stockcard')->whereIn('reference_detail_id', $detailIds)->whereNull('batch_id')->update(['batch_id' => $batch->id_batch]);
        }

        if (! $existing && ! $conditional) {
            QcDecisionService::record($batch, 'SAMPLING_DOCK', 'RELEASED', $line->qc_by ?: $login, ['lot_qty' => (float) $line->c_qty_accepted,
                'test_result' => 'Dock QC: '.($line->qc_quality_status ?: 'PASS')]);
        }
    }
}
