<?php

/*
 * REMARK
 * Tujuan       : Permohonan tenaga kerja (rcr_requisition) dari layar v2 hrm-rcr-requisition-form: Save draft, Delete draft, Submit for Approval (membuat langkah approval dari matriks track), Approve / Reject oleh approver yang ditugaskan. Approval berjenjang tetap dikerjakan RecruitmentApprovalService (dipakai ulang, juga oleh Offer).
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::recruitment.requisition.list dan entry (diarsipkan di tmerp_unused/recruitment_requisition_*_v1_2026-10-19.blade.php): nomor RCR{yy}{5 digit}, status DRAFT / SUBMITTED / APPROVED / REJECTED, submit wajib posisi + kualifikasi + alasan, keputusan hanya oleh approver seq PENDING terkecil, Reject wajib alasan, Delete hanya DRAFT. Baru (Jo 2026-10-19): nomor dari nomor terbesar tahun itu dalam transaksi (v1 menghitung jumlah baris dan bentrok dengan indeks unik setelah draft dihapus), draft kosong tidak lagi dibuat saat New (baris lahir saat Save), header divalidasi server (track dan department aktif, headcount 1-999, panjang teks), Submit ditolak bila track tak punya langkah approval atau ada peran tanpa approver ditugaskan (v1 menyimpan approver_id 0 sehingga macet), keputusan dalam transaksi dengan kunci baris, draft hanya bisa diubah / dihapus pembuatnya atau HR Officer.
 * Input        : requisition (opsional), array isian header, user yang bertindak, keputusan dan catatan.
 * Output       : tm2hrr.rcr_requisition dan rcr_requisition_approval berubah; ValidationException (field / document) atau AuthorizationException bila aturan dilanggar.
 * Dipakai oleh : routes/v2_hrm.php (v2.hrm.requisition.save), layar hrm-rcr-requisition-form, tes RequisitionServiceTest.
 * Status       : Aktif (T7l HRM, 2026-10-19); cek approver, pemilik langkah, dan baris langkah memakai penolong RecruitmentApprovalService (2026-10-20).
 */

namespace App\Services\Recruitment;

use App\Models\Hrr\RcrApprovalMatrix;
use App\Models\Hrr\RcrRequisition;
use App\Models\Hrr\RcrRequisitionApproval;
use App\Models\Sys\SecUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class RequisitionService
{
    public const STATUS_STATE = ['DRAFT' => 'draft', 'SUBMITTED' => 'approval', 'APPROVED' => 'approved', 'REJECTED' => 'rejected'];

    /** Draft hanya diubah / dihapus pembuatnya atau HR Officer (status DRAFT dicek terpisah); baris baru boleh dibuat siapa pun. */
    public static function canEdit(?RcrRequisition $r, SecUser $user): bool
    {
        return ! $r?->exists || (int) $r->requested_by === (int) $user->getKey() || $user->isHrOfficer();
    }

    public function save(?RcrRequisition $r, array $in, SecUser $user): RcrRequisition
    {
        if (! self::canEdit($r, $user)) {
            throw new AuthorizationException('Only the creator or the HR Officer can change a draft requisition.');
        }
        $d = Validator::make($in, [
            'id_track' => ['required', 'integer', Rule::exists('tm2hrr.rcr_track', 'id_track')->where('is_active', 1)],
            'department_id' => ['required', 'integer', Rule::exists('tm2mst.org_department', 'department_id')->where('activate', 1)],
            'position_title' => ['required', 'string', 'max:150'],
            'headcount' => ['required', 'integer', 'between:1,999'],
            'qualification' => ['nullable', 'string', 'max:5000'],
            'reason' => ['nullable', 'string', 'max:5000'],
        ], [], ['id_track' => 'Track', 'department_id' => 'Department', 'position_title' => 'Position needed', 'headcount' => 'Headcount', 'qualification' => 'Qualification', 'reason' => 'Requisition reason'])->validate();
        $data = ['id_track' => (int) $d['id_track'], 'department_id' => (int) $d['department_id'], 'position_title' => trim($d['position_title']), 'headcount' => (int) $d['headcount'],
            'qualification' => trim((string) ($d['qualification'] ?? '')), 'reason' => trim((string) ($d['reason'] ?? ''))];

        return DB::connection('tm2hrr')->transaction(function () use ($r, $data, $user) {
            if ($r?->exists) {
                $fresh = RcrRequisition::whereKey($r->getKey())->lockForUpdate()->firstOrFail();
                if ($fresh->status !== 'DRAFT') {
                    throw ValidationException::withMessages(['document' => 'Only a draft requisition can be changed.']);
                }
                $fresh->update($data);

                return $fresh;
            }
            $yy = now()->format('y');
            $last = (string) RcrRequisition::where('requisition_no', 'like', "RCR{$yy}%")->lockForUpdate()->max('requisition_no');

            return RcrRequisition::create($data + ['requisition_no' => "RCR{$yy}".str_pad((string) (((int) substr($last, 5)) + 1), 5, '0', STR_PAD_LEFT), 'requested_by' => $user->getKey(), 'status' => 'DRAFT']);
        });
    }

    public function delete(RcrRequisition $r, SecUser $user): void
    {
        if (! self::canEdit($r, $user)) {
            throw new AuthorizationException('Only the creator or the HR Officer can delete a draft requisition.');
        }
        DB::connection('tm2hrr')->transaction(function () use ($r) {
            $fresh = RcrRequisition::whereKey($r->getKey())->lockForUpdate()->firstOrFail();
            if ($fresh->status !== 'DRAFT') {
                throw ValidationException::withMessages(['document' => 'Only a draft requisition can be deleted.']);
            }
            $fresh->delete();
        });
    }

    public function submit(RcrRequisition $r, SecUser $user): void
    {
        if (! self::canEdit($r, $user)) {
            throw new AuthorizationException('Only the creator or the HR Officer can submit a draft requisition.');
        }
        DB::connection('tm2hrr')->transaction(function () use ($r) {
            $fresh = RcrRequisition::whereKey($r->getKey())->lockForUpdate()->firstOrFail();
            if ($fresh->status !== 'DRAFT') {
                throw ValidationException::withMessages(['document' => 'Only a draft requisition can be submitted.']);
            }
            if (trim($fresh->position_title) === '' || trim($fresh->qualification) === '' || trim($fresh->reason) === '') {
                throw ValidationException::withMessages(['document' => 'Position, qualification and reason are required before submitting.']);
            }
            $steps = RcrApprovalMatrix::where('id_track', $fresh->id_track)->orderBy('approval_seq')->get();
            RecruitmentApprovalService::assertApproversReady($steps, $fresh->department_id, 'This track has no approval steps configured.');
            app(RecruitmentApprovalService::class)->submitRequisition($fresh);
        });
    }

    /** Langkah approval yang menunggu dan milik user ini (seq PENDING terkecil), atau null. */
    public static function pendingFor(RcrRequisition $r, SecUser $user): ?RcrRequisitionApproval
    {
        if ($r->status !== 'SUBMITTED') {
            return null;
        }
        $current = $r->approvals()->where('decision', 'PENDING')->orderBy('approval_seq')->first();

        return RecruitmentApprovalService::ownedBy($current, $user);
    }

    public function decide(RcrRequisition $r, SecUser $user, string $decision, ?string $note): void
    {
        if (! in_array($decision, ['APPROVED', 'REJECTED'], true)) {
            throw ValidationException::withMessages(['document' => 'Unknown decision.']);
        }
        if ($decision === 'REJECTED' && trim((string) $note) === '') {
            throw ValidationException::withMessages(['reason' => 'Rejection reason is required.']);
        }
        DB::connection('tm2hrr')->transaction(function () use ($r, $user, $decision, $note) {
            $fresh = RcrRequisition::whereKey($r->getKey())->lockForUpdate()->firstOrFail();
            $pending = self::pendingFor($fresh, $user);
            if (! $pending) {
                throw new AuthorizationException('This requisition is not waiting for your decision.');
            }
            app(RecruitmentApprovalService::class)->decideRequisition($pending, $decision, trim((string) $note) ?: null);
        });
    }

    /** Langkah approval untuk tabel di form: peran dari matriks track, approver, keputusan. */
    public static function steps(RcrRequisition $r): array
    {
        return RecruitmentApprovalService::stepRows($r->approvals()->get(), RcrApprovalMatrix::where('id_track', $r->id_track)->pluck('approver_role', 'approval_seq')->all(), true);
    }

    /** Riwayat tab History: dibuat dan tiap keputusan, terbaru di atas. */
    public function history(RcrRequisition $r): array
    {
        $rows = [['at' => $r->created_at?->toDateTimeString(), 'by' => (string) (SecUser::whereKey($r->requested_by)->value('name') ?? ''), 'action' => 'Created', 'note' => '']];
        foreach (self::steps($r) as $i => $s) {
            $a = $r->approvals[$i] ?? null;
            if ($a && $a->decided_at) {
                $rows[] = ['at' => $a->decided_at->toDateTimeString(), 'by' => $s['approver'], 'action' => ucfirst(strtolower($a->decision)), 'note' => trim($s['role'].' '.$s['note'])];
            }
        }

        return array_reverse($rows);
    }
}
