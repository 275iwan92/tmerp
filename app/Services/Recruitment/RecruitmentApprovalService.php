<?php

/* REMARK
 * Tujuan       : Approval berjenjang Requisition dan Offer rekrutmen (matriks track, resolusi peran ke user) serta tiga penolong yang dipakai bersama
 *                RequisitionService dan OfferService: cek kesiapan approver, pemilik langkah PENDING, dan baris langkah untuk tabel form.
 * Sumber hulu  : rcr_approval_matrix, rcr_offer_approval_matrix, RcrApproverAssignment (tm2hrr); user (tm2sys).
 * Input        : dokumen Requisition / Offer, langkah matriks, keputusan.
 * Output       : baris approval PENDING / APPROVED / REJECTED dan status dokumen; ValidationException kunci document bila approver belum siap.
 * Dipakai oleh : RequisitionService, OfferService, layar approval rekrutmen.
 * Status       : Aktif; tiga penolong statis ditarik dari dua service (duplikasi, Jo 2026-10-20).
 */

namespace App\Services\Recruitment;

use App\Models\Hrr\AppOffer;
use App\Models\Hrr\AppOfferApproval;
use App\Models\Hrr\RcrApprovalMatrix;
use App\Models\Hrr\RcrApproverAssignment;
use App\Models\Hrr\RcrOfferApprovalMatrix;
use App\Models\Hrr\RcrRequisition;
use App\Models\Hrr\RcrRequisitionApproval;
use App\Models\Sys\SecUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

// Approval berjenjang utk Requisition (rcr_approval_matrix) & Offer
// (rcr_offer_approval_matrix) -- BESPOKE, bukan FlowEngineService (itu utk
// modul yg sudah pakai org_position/flow_config, Tier3 Recruitment sengaja
// didesain lebih simpel: role String, tanpa tiering by amount). Resolusi
// approver_role -> sec_user via RcrApproverAssignment (lihat model itu utk
// alasan lapisan ini ada).
class RecruitmentApprovalService
{
    public function submitRequisition(RcrRequisition $requisition): void
    {
        $matrix = RcrApprovalMatrix::where('id_track', $requisition->id_track)
            ->orderBy('approval_seq')
            ->get();

        foreach ($matrix as $step) {
            RcrRequisitionApproval::create([
                'id_requisition' => $requisition->id_requisition,
                'approval_seq' => $step->approval_seq,
                'approver_id' => RcrApproverAssignment::resolve($step->approver_role, $requisition->department_id) ?? 0,
                'decision' => 'PENDING',
            ]);
        }

        $requisition->update(['status' => 'SUBMITTED']);
    }

    public function decideRequisition(RcrRequisitionApproval $approval, string $decision, ?string $note = null): void
    {
        $approval->update(['decision' => $decision, 'decided_at' => now(), 'note' => $note]);

        $requisition = $approval->requisition;

        if ($decision === 'REJECTED') {
            $requisition->update(['status' => 'REJECTED']);

            return;
        }

        $hasMorePending = RcrRequisitionApproval::where('id_requisition', $requisition->id_requisition)
            ->where('decision', 'PENDING')
            ->exists();

        if (! $hasMorePending) {
            $requisition->update(['status' => 'APPROVED']);
        }
    }

    // Approval seq yang sedang aktif ditunggu (seq PENDING terkecil) --
    // seq berikutnya baru relevan setelah seq sebelumnya APPROVED (Tier2 §2).
    public function pendingRequisitionApprovalFor(RcrRequisition $requisition, int $secUserId): ?RcrRequisitionApproval
    {
        $current = $requisition->approvals()->where('decision', 'PENDING')->orderBy('approval_seq')->first();

        return ($current && (int) $current->approver_id === $secUserId) ? $current : null;
    }

    public function submitOffer(AppOffer $offer): void
    {
        $trackId = $offer->application->vacancy->requisition->id_track;

        $matrix = RcrOfferApprovalMatrix::where('id_track', $trackId)->orderBy('approval_seq')->get();

        foreach ($matrix as $step) {
            AppOfferApproval::create([
                'id_offer' => $offer->id_offer,
                'approval_seq' => $step->approval_seq,
                'approver_id' => RcrApproverAssignment::resolve($step->approver_role, null) ?? 0,
                'decision' => 'PENDING',
            ]);
        }

        $offer->update(['status' => 'PENDING_APPROVAL']);
    }

    public function decideOffer(AppOfferApproval $approval, string $decision): void
    {
        $approval->update(['decision' => $decision, 'decided_at' => now()]);

        $offer = AppOffer::findOrFail($approval->id_offer);

        if ($decision === 'REJECTED') {
            $offer->update(['status' => 'DECLINED']);

            return;
        }

        $hasMorePending = AppOfferApproval::where('id_offer', $offer->id_offer)->where('decision', 'PENDING')->exists();

        if (! $hasMorePending) {
            $offer->update(['status' => 'APPROVED']);
        }
    }

    // Antrian approval user login ini -- dipakai badge/queue di layar L1/L7.
    public function pendingRequisitionsFor(int $secUserId)
    {
        return RcrRequisitionApproval::where('approver_id', $secUserId)
            ->where('decision', 'PENDING')
            ->whereIn('id_requisition', function ($q) {
                $q->select('id_requisition')->from('rcr_requisition')->where('status', 'SUBMITTED');
            })
            ->get();
    }

    /** Track harus punya langkah dan tiap peran di langkah itu harus punya approver (departemen bila perlu); kalau tidak: ValidationException kunci document. */
    public static function assertApproversReady(Collection $steps, ?int $departmentId, string $noStepsMessage): void
    {
        if ($steps->isEmpty()) {
            throw ValidationException::withMessages(['document' => $noStepsMessage]);
        }
        $missing = $steps->filter(fn ($s) => ! RcrApproverAssignment::resolve($s->approver_role, $departmentId))->pluck('approver_role')->unique()->implode(', ');
        if ($missing !== '') {
            throw ValidationException::withMessages(['document' => 'No approver is assigned for: '.$missing.'.']);
        }
    }

    /** Langkah PENDING terkecil bila miliknya user ini, selain itu null; $current = langkah PENDING terkecil dokumen (atau null). */
    public static function ownedBy(?Model $current, SecUser $user): ?Model
    {
        return $current && (int) $current->approver_id === (int) $user->getKey() ? $current : null;
    }

    /** Baris tabel langkah approval di form: peran dari matriks track, nama approver, keputusan, waktu (+ catatan bila diminta). */
    public static function stepRows(Collection $approvals, array $roles, bool $withNote = false): array
    {
        $names = SecUser::whereKey($approvals->pluck('approver_id')->filter()->all())->pluck('name', 'id_user')->all();

        return $approvals->map(fn (Model $a) => ['seq' => (string) $a->approval_seq, 'role' => (string) ($roles[$a->approval_seq] ?? '—'), 'approver' => (string) ($names[$a->approver_id] ?? '—'),
            'decision' => (string) $a->decision, 'at' => $a->decided_at?->format('d-M-Y H:i') ?? '—'] + ($withNote ? ['note' => (string) ($a->note ?? '')] : []))->all();
    }
}
