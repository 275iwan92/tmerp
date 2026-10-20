<?php

declare(strict_types=1);

/* REMARK
 * Tujuan       : Gerbang QC pada baris proses Work Order: Lulus (QC_HOLD -> QC_PASSED, WO selesai bila semua proses DONE / QC_PASSED), Tolak dengan alasan (QC_HOLD -> QC_REJECTED), Rework (QC_REJECTED -> PENDING) dan terima kembali dari subkontrak (SUBCONTRACT -> QC_HOLD, wajib QC).
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::production.wo-entry (qcPass, submitQcReject, reworkFromReject, receiveFromSubcontract); status task dan transisi sah di PrdWoOperation::canTransitionTo.
 * Input        : id operasi, user yang bertindak, alasan tolak (min 5 karakter).
 * Output       : prd_wo_operation.task_status (+ rejection_reason), prd_wo.wo_status COMPLETED; AuthorizationException bila bukan QA, ValidationException (kunci document / reason) bila transisi atau alasan salah.
 * Dipakai oleh : routes/v2_production.php (v2.production.wo.op.*), layar production-wo (kartu Execution), tes WoExecutionServiceTest.
 * Status       : Aktif (T9 pindahan v1, 2026-10-20).
 */

namespace App\Services\Production;

use App\Models\Prd\PrdWoOperation;
use App\Models\Sys\SecUser;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WoQcGateService
{
    private function qa(SecUser $user): void
    {
        if (! $user->isQa()) {
            throw new AuthorizationException('Only QA can decide QC.');
        }
    }

    private function move(PrdWoOperation $op, string $target): void
    {
        try {
            $op->transitionTo($target);
        } catch (DomainException) {
            throw ValidationException::withMessages(['document' => tf('This task is :from and cannot move to :to.', ['from' => $op->task_status, 'to' => $target])]);
        }
    }

    public function pass(int $operationId, SecUser $user): PrdWoOperation
    {
        $this->qa($user);

        return DB::connection('tm2prd')->transaction(function () use ($operationId) {
            $op = PrdWoOperation::with('wo')->lockForUpdate()->findOrFail($operationId);
            $this->move($op, 'QC_PASSED');
            $allDone = PrdWoOperation::where('wo_id', $op->wo_id)->whereNotIn('task_status', ['DONE', 'QC_PASSED'])->doesntExist();
            if ($allDone && $op->wo->canTransitionTo('COMPLETED')) {
                $op->wo->transitionTo('COMPLETED');
            }

            return $op;
        });
    }

    public function reject(int $operationId, string $reason, SecUser $user): PrdWoOperation
    {
        $this->qa($user);
        if (mb_strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages(['reason' => 'The rejection reason is required (at least 5 characters).']);
        }

        return DB::connection('tm2prd')->transaction(function () use ($operationId, $reason) {
            $op = PrdWoOperation::lockForUpdate()->findOrFail($operationId);
            $op->rejection_reason = $reason;
            $this->move($op, 'QC_REJECTED');

            return $op;
        });
    }

    public function rework(int $operationId): PrdWoOperation
    {
        return DB::connection('tm2prd')->transaction(function () use ($operationId) {
            $op = PrdWoOperation::lockForUpdate()->findOrFail($operationId);
            $this->move($op, 'PENDING');

            return $op;
        });
    }

    /** Task kembali dari subkontrak: wajib melewati QC. */
    public function receiveFromSubcontract(int $operationId): PrdWoOperation
    {
        return DB::connection('tm2prd')->transaction(function () use ($operationId) {
            $op = PrdWoOperation::lockForUpdate()->findOrFail($operationId);
            $this->move($op, 'QC_HOLD');

            return $op;
        });
    }
}
