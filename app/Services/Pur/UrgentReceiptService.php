<?php

declare(strict_types=1);

namespace App\Services\Pur;

use App\Models\Inventory\InvBatch;
use App\Models\Pur\PvLine;
use App\Models\Pur\PvReceipt;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysConstant;
use App\Services\Inventory\BatchService;
use App\Services\Inventory\QcAuthority;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

// Penerimaan URGENT (Q-03, Q-04, Q-11, D16): baris penerimaan yang langsung diposting dan dibawa ke produksi sementara sampling berjalan paralel.
//   Siapa     : PPIC, Supervisor Produksi, Manager Produksi, Ops Manager ke atas (QcAuthority::canMarkUrgent).
//   Syarat    : alasan (min. 5 huruf) dan WO tujuan wajib; penerimaan masih Draft / QC Pending.
//   FSC / food grade : bila INV_URGENT_FSC_APPROVAL = 1, baris perlu persetujuan kepala QC atau Ops Manager (approve) sebelum diposting.
//   Efek      : PvPostingService::blockers() tidak lagi menahan baris ini karena QC mutu PENDING; batchnya lahir CONDITIONAL (ReceiptBatchService).
final class UrgentReceiptService
{
    public const CERTIFICATIONS_NEEDING_APPROVAL = ['FSC', 'PEFC'];

    // Bahan klaim FSC / PEFC atau food grade (dari baris penerimaan, atau dari batch lama dengan item + lot yang sama).
    public static function needsApproval(PvLine $line): bool
    {
        if (SysConstant::number('INV_URGENT_FSC_APPROVAL', 1) < 1) {
            return false;
        }
        if (in_array($line->i_certification, self::CERTIFICATIONS_NEEDING_APPROVAL, true) || $line->i_food_grade) {
            return true;
        }
        $batch = $line->item_id ? BatchService::findByLot('RM', (int) $line->item_id, $line->i_lot_no) : null;

        return $batch !== null && (in_array($batch->certification, self::CERTIFICATIONS_NEEDING_APPROVAL, true) || $batch->food_grade);
    }

    public static function mark(PvLine $line, string $reason, int $woId, string|SecUser $by): PvLine
    {
        $user = QcAuthority::user($by);
        if (! QcAuthority::canMarkUrgent($user)) {
            throw new InvalidArgumentException(t('Only PPIC, a production supervisor or an operations manager can mark a receipt URGENT.'));
        }
        self::assertOpen($line);
        if (! $line->is_stock || ! $line->item_id) {
            throw new InvalidArgumentException(t('Only a stock item line can be marked URGENT.'));
        }
        if (mb_strlen(trim($reason)) < 5) {
            throw new InvalidArgumentException(t('Give the URGENT reason (at least 5 characters).'));
        }
        if (! DB::connection('tm2prd')->table('prd_wo')->where('wo_id', $woId)->exists()) {
            throw new InvalidArgumentException(t('Choose the work order that needs this material.'));
        }
        // Penandaan baru atau alasan / WO berubah = persetujuan lama tidak berlaku lagi.
        $changed = ! $line->is_urgent || (int) $line->urgent_wo_id !== $woId;
        $line->forceFill(['is_urgent' => true, 'urgent_by' => $user->login, 'urgent_at' => now(), 'urgent_reason' => mb_substr(trim($reason), 0, 255), 'urgent_wo_id' => $woId,
            'updated_by' => $user->login] + ($changed ? ['urgent_approved_by' => null, 'urgent_approved_at' => null, 'urgent_approval_note' => null] : []))->save();

        return $line;
    }

    public static function unmark(PvLine $line, string|SecUser $by): PvLine
    {
        $user = QcAuthority::user($by);
        if (! QcAuthority::canMarkUrgent($user)) {
            throw new InvalidArgumentException(t('Only PPIC, a production supervisor or an operations manager can mark a receipt URGENT.'));
        }
        self::assertOpen($line);
        $line->forceFill(['is_urgent' => false, 'urgent_by' => null, 'urgent_at' => null, 'urgent_reason' => null, 'urgent_wo_id' => null, 'urgent_approved_by' => null,
            'urgent_approved_at' => null, 'urgent_approval_note' => null, 'updated_by' => $user->login])->save();

        return $line;
    }

    public static function approve(PvLine $line, string|SecUser $by, ?string $note = null): PvLine
    {
        $user = QcAuthority::user($by);
        if (! QcAuthority::canApproveUrgent($user)) {
            throw new InvalidArgumentException(t('Only the head of QC or an operations manager can approve an URGENT FSC / food grade receipt.'));
        }
        self::assertOpen($line);
        if (! $line->is_urgent) {
            throw new InvalidArgumentException(t('This line is not marked URGENT.'));
        }
        $line->forceFill(['urgent_approved_by' => $user->login, 'urgent_approved_at' => now(), 'urgent_approval_note' => $note !== null ? mb_substr(trim($note), 0, 255) : null, 'updated_by' => $user->login])->save();

        return $line;
    }

    // Sertifikasi dan food grade dibawa ke batch saat posting; mengubahnya mencabut persetujuan lama bila perlu persetujuan baru.
    public static function setCertification(PvLine $line, string $certification, bool $foodGrade, string $login): PvLine
    {
        if (! array_key_exists($certification, InvBatch::CERTIFICATIONS)) {
            throw new InvalidArgumentException("Sertifikasi tidak dikenal: {$certification}");
        }
        self::assertOpen($line);
        $line->forceFill(['i_certification' => $certification, 'i_food_grade' => $foodGrade, 'updated_by' => $login])->save();

        return $line;
    }

    /** @return list<string> alasan baris URGENT ini belum boleh diposting (kosong = boleh) */
    public static function postBlockers(PvLine $line): array
    {
        if (! $line->is_urgent) {
            return [];
        }
        $blockers = [];
        if (mb_strlen(trim((string) $line->urgent_reason)) < 5 || ! $line->urgent_wo_id) {
            $blockers[] = tf('Line :line is URGENT but has no reason or work order.', ['line' => $line->line_no]);
        }
        if (! QcAuthority::canMarkUrgent($line->urgent_by)) {
            $blockers[] = tf('Line :line was marked URGENT by someone who is not allowed to.', ['line' => $line->line_no]);
        }
        if (self::needsApproval($line) && ! $line->urgent_approved_by) {
            $blockers[] = tf('Line :line is FSC / food grade material: the head of QC or an operations manager must approve the URGENT receipt first.', ['line' => $line->line_no]);
        }

        return $blockers;
    }

    // Baris URGENT dengan QC mutu masih PENDING belum punya qty diterima (biasanya diisi saat PASS): seluruh qty stok dianggap diterima agar bisa diposting.
    public static function prepareForPost(PvReceipt $pv): void
    {
        foreach ($pv->lines as $line) {
            if ($line->is_urgent && $line->qc_quality_status === 'PENDING' && (float) $line->c_qty_accepted <= 0) {
                $qty = (float) $line->i_qty_stock;
                $line->forceFill(['c_qty_accepted' => $qty, 'c_qty_rejected' => 0, 'c_line_value' => round($qty * (float) $line->c_unit_price, 2)])->save();
            }
        }
    }

    private static function assertOpen(PvLine $line): void
    {
        $status = PvReceipt::query()->whereKey($line->pv_id)->value('status');
        if (! in_array($status, ['DRAFT', 'QC_PENDING'], true)) {
            throw new InvalidArgumentException(t('The receipt is already posted and cannot be changed.'));
        }
    }
}
