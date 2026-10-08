<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\InvQcDecision;
use App\Models\Inventory\InvQcFirstArticle;
use App\Models\Sys\SysConstant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

// Satu pintu keputusan mutu atas batch (Q-05, Q-06, Q-07): sampling di dok, first article di mesin, Usage Decision susulan, deviasi.
// Setiap keputusan mengubah status batch lewat BatchService::setStatus dan tercatat di inv_qc_decision (riwayat sampling supplier dibaca dari sana).
// Ditolak / diblokir = alasan wajib, WO yang memakai batch ditahan dan klaim supplier dibuka (QcTraceService); diputuskan lain = hold dibebaskan.
// Hak memutuskan dijaga pemanggil (QcAuthority::canDecide), bukan di sini, supaya layar dan uji memakai jalur yang sama.
final class QcDecisionService
{
    public const TYPES = ['SAMPLING_DOCK' => 'Sampling at dock', 'FIRST_ARTICLE' => 'First article', 'USAGE_DECISION' => 'Usage decision', 'DEVIATION' => 'Deviation'];

    public const DECISIONS = ['RELEASED' => 'Released', 'REJECTED' => 'Rejected', 'BLOCKED' => 'Blocked', 'CONDITIONAL_USE' => 'Conditional use'];

    // keputusan -> status batch yang dihasilkan
    private const STATUS_OF = ['RELEASED' => 'RELEASED', 'REJECTED' => 'REJECTED', 'BLOCKED' => 'BLOCKED', 'CONDITIONAL_USE' => 'CONDITIONAL'];

    /**
     * @param  array{reason?: ?string, lot_qty?: float|string|null, sample_size?: int|string|null, sample_defects?: int|string|null, test_result?: ?string, wo_id?: int|string|null, intensity?: ?string}  $in
     */
    public static function record(InvBatch $batch, string $type, string $decision, string $login, array $in = []): InvQcDecision
    {
        if (! isset(self::TYPES[$type]) || ! isset(self::DECISIONS[$decision])) {
            throw new InvalidArgumentException("Jenis atau keputusan QC tidak dikenal: {$type} / {$decision}");
        }
        $reason = trim((string) ($in['reason'] ?? ''));
        if (in_array($decision, ['REJECTED', 'BLOCKED'], true) && mb_strlen($reason) < 5) {
            throw new InvalidArgumentException(t('Give a reason (at least 5 characters) when a batch is rejected or blocked.'));
        }
        $lotQty = isset($in['lot_qty']) && $in['lot_qty'] !== '' ? (float) $in['lot_qty'] : null;
        $plan = $type === 'SAMPLING_DOCK' && $lotQty ? QcSamplingService::planFor((int) $batch->item_id, $batch->supplier_id ? (int) $batch->supplier_id : null, $lotQty) : null;

        return DB::connection('tm2inv')->transaction(function () use ($batch, $type, $decision, $login, $in, $reason, $lotQty, $plan) {
            $before = (string) $batch->qc_status;
            $after = self::STATUS_OF[$decision];
            BatchService::setStatus($batch, $after, $login, $reason !== '' ? $reason : null);
            $row = InvQcDecision::query()->create(['batch_id' => $batch->id_batch, 'decision_type' => $type, 'decision' => $decision, 'status_before' => $before, 'status_after' => $after,
                'supplier_id' => $batch->supplier_id, 'risk_class' => $plan['risk_class'] ?? QcSamplingService::riskClass((int) $batch->item_id), 'intensity' => $in['intensity'] ?? ($plan['intensity'] ?? null),
                'lot_qty' => $lotQty, 'sample_size' => isset($in['sample_size']) && $in['sample_size'] !== '' ? (int) $in['sample_size'] : ($plan['sample_size'] ?? null),
                'sample_defects' => isset($in['sample_defects']) && $in['sample_defects'] !== '' ? (int) $in['sample_defects'] : null,
                'test_result' => isset($in['test_result']) ? mb_substr(trim((string) $in['test_result']), 0, 255) : null, 'wo_id' => $in['wo_id'] ?? null,
                'reason' => $reason !== '' ? mb_substr($reason, 0, 255) : null, 'decided_by' => $login, 'decided_at' => now()]);
            if ($after === 'REJECTED') {
                QcTraceService::onReject($batch, $reason, $login);
            } elseif ($after === 'BLOCKED') {
                QcTraceService::holdWorkOrders($batch, $reason, $login);
            } else {
                QcTraceService::clearHolds($batch, $login);
            }

            return $row;
        });
    }

    // First article di mesin (Q-05): PASS melepas batch, FAIL menolaknya (catatan wajib). Hasilnya tersimpan juga sebagai keputusan FIRST_ARTICLE.
    public static function firstArticle(int $woId, InvBatch $batch, string $result, string $login, ?string $notes = null): InvQcFirstArticle
    {
        if (! in_array($result, ['PASS', 'FAIL'], true)) {
            throw new InvalidArgumentException("Hasil first article harus PASS atau FAIL, bukan {$result}");
        }
        $wo = DB::connection('tm2prd')->table('prd_wo')->where('wo_id', $woId)->first(['wo_id', 'wo_no']) ?? throw new InvalidArgumentException(t('Work order not found.'));
        $decision = self::record($batch, 'FIRST_ARTICLE', $result === 'PASS' ? 'RELEASED' : 'REJECTED', $login, ['reason' => $notes, 'wo_id' => $woId, 'test_result' => $result]);

        return InvQcFirstArticle::query()->create(['wo_id' => $woId, 'wo_no' => $wo->wo_no, 'batch_id' => $batch->id_batch, 'result' => $result, 'notes' => $notes !== null ? mb_substr(trim($notes), 0, 255) : null,
            'decision_id' => $decision->id_decision, 'checked_by' => $login, 'checked_at' => now()]);
    }

    /**
     * Umur keputusan stok Conditional / Quarantine sejak status terakhir (jam jam-dinding): OK, ESCALATE (lewat INV_QC_ESCALATE_HOURS), OVERDUE (lewat INV_QC_DEADLINE_HOURS).
     *
     * @return array{hours: float, state: string}
     */
    public static function age(InvBatch $batch): array
    {
        $hours = $batch->qc_status_at ? round($batch->qc_status_at->diffInMinutes(now(), true) / 60, 1) : 0.0;
        $state = match (true) {
            $hours >= SysConstant::number('INV_QC_DEADLINE_HOURS', 8) => 'OVERDUE',
            $hours >= SysConstant::number('INV_QC_ESCALATE_HOURS', 4) => 'ESCALATE',
            default => 'OK',
        };

        return ['hours' => $hours, 'state' => $state];
    }
}
