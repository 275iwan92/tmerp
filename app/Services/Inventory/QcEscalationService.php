<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\InvBatch;

// Eskalasi keputusan mutu (D16, Q-06): batch Conditional / Quarantine yang melewati INV_QC_ESCALATE_HOURS diberitahukan ke kepala QC dan Ops Manager;
// yang melewati INV_QC_DEADLINE_HOURS diberitahukan lagi (Usage Decision wajib) ke kepala QC, QA, Ops Manager dan PPIC. Umur dari QcDecisionService::age.
// Satu pesan per batch per tahap (judul + isi sama tidak dikirim dua kali), jadi aman dijalankan berulang. Dijalankan perintah inv:qc-escalate.
final class QcEscalationService
{
    private const ESCALATE_TO = ['QA_HEAD', 'OPS_MGR'];

    private const OVERDUE_TO = ['QA_HEAD', 'QA', 'OPS_MGR', 'PROD_PLANNING'];

    /** @return list<array{batch_no: string, state: string, hours: float, sent: int}> */
    public static function run(bool $dryRun = false): array
    {
        $rows = [];
        foreach (InvBatch::query()->whereIn('qc_status', ['CONDITIONAL', 'QUARANTINE'])->orderBy('qc_status_at')->get() as $batch) {
            $age = QcDecisionService::age($batch);
            if ($age['state'] === 'OK') {
                continue;
            }
            $overdue = $age['state'] === 'OVERDUE';
            $text = tf(':batch · :item (:status): no quality decision yet.', ['batch' => $batch->batch_no, 'item' => $batch->item_code, 'status' => $batch->qc_status]);
            $sent = $dryRun ? 0 : QcNotifier::toGroups($overdue ? self::OVERDUE_TO : self::ESCALATE_TO, $overdue ? 'Quality decision overdue' : 'Quality decision escalated',
                $text.($overdue ? ' '.t('A usage decision is required now.') : ' '.t('Decide before the deadline.')), $overdue ? 'error' : 'warning');
            $rows[] = ['batch_no' => (string) $batch->batch_no, 'state' => $age['state'], 'hours' => $age['hours'], 'sent' => $sent];
        }

        return $rows;
    }
}
