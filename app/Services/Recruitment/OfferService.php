<?php

/*
 * REMARK
 * Tujuan       : Penawaran kerja (app_offer) dari layar v2 hrm-rcr-offer-form: simpan draft, Submit for Approval, Approve / Reject oleh approver yang ditugaskan, Send to Candidate, Candidate Accepted / Declined (input manual), Revise Offer. Approval berjenjang tetap dikerjakan RecruitmentApprovalService (submitOffer / decideOffer, dipakai ulang).
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::recruitment.offer.list dan entry (diarsipkan di tmerp_unused/recruitment_offer_*_v1_2026-10-20.blade.php): status DRAFT, PENDING_APPROVAL, APPROVED, SENT, ACCEPTED, DECLINED; revisi = baris baru dengan previous_offer_id; Reject approver menjadikan penawaran DECLINED. Baru (Jo 2026-10-20, mode full speed): membuka layar tidak lagi membuat penawaran (v1 membuat baris gaji 0 di mount), penawaran hanya untuk lamaran berstatus OFFERING dan hanya satu yang aktif, isian divalidasi server (gaji > 0, jenis, tanggal mulai tidak lampau), semua perubahan status dijaga urutannya di server (v1 bisa menandai ACCEPTED dari DRAFT sehingga approval terlewati), Submit ditolak bila track tanpa langkah approval atau peran tanpa approver, gaji (data rahasia) hanya terlihat dan bisa diubah oleh user berhak payroll atau approver penawaran itu.
 * Input        : lamaran (app_application), penawaran, array isian, user yang bertindak.
 * Output       : tm2hrr.app_offer dan app_offer_approval berubah; ValidationException (field / document) atau AuthorizationException bila aturan dilanggar.
 * Dipakai oleh : routes/v2_hrm.php (v2.hrm.offer.save), layar hrm-rcr-offer-form, tes OfferServiceTest.
 * Status       : Aktif (T7m HRM, 2026-10-20); cek approver, pemilik langkah, dan baris langkah memakai penolong RecruitmentApprovalService (2026-10-20).
 */

namespace App\Services\Recruitment;

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\AppOffer;
use App\Models\Hrr\AppOfferApproval;
use App\Models\Hrr\RcrOfferApprovalMatrix;
use App\Models\Sys\SecUser;
use App\Services\Payroll\PayrollAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class OfferService
{
    public const TYPES = ['TETAP' => 'Permanent', 'KONTRAK' => 'Contract', 'HARIAN' => 'Daily', 'BORONGAN' => 'Piece Rate'];

    public const STATUS_STATE = ['DRAFT' => 'draft', 'PENDING_APPROVAL' => 'approval', 'APPROVED' => 'approved', 'SENT' => 'approved', 'ACCEPTED' => 'approved', 'DECLINED' => 'rejected', 'EXPIRED' => 'rejected'];

    /** Penawaran terbaru lamaran ini (putaran terakhir), atau null. */
    public static function latest(AppApplication $a): ?AppOffer
    {
        return $a->offers()->with('approvals')->first();
    }

    public static function editSalary(?SecUser $user): bool
    {
        return PayrollAccess::canProcess($user);
    }

    /** Gaji hanya untuk user berhak payroll atau approver penawaran itu. */
    public static function canSeeSalary(?AppOffer $o, ?SecUser $user): bool
    {
        return self::editSalary($user) || ($o && $user && $o->approvals->contains(fn ($a) => (int) $a->approver_id === (int) $user->getKey()));
    }

    public function save(AppApplication $a, ?AppOffer $o, array $in, SecUser $user): AppOffer
    {
        if (! self::editSalary($user)) {
            throw new AuthorizationException('Only users with payroll rights can enter the proposed salary.');
        }
        $d = Validator::make($in, [
            'proposed_salary' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'employment_type' => ['required', Rule::in(array_keys(self::TYPES))],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ], ['proposed_salary.gt' => 'Proposed salary is required.', 'start_date.after_or_equal' => 'The start date cannot be in the past.'], ['proposed_salary' => 'Proposed salary', 'employment_type' => 'Contract type', 'start_date' => 'Start date'])->validate();

        return DB::connection('tm2hrr')->transaction(function () use ($a, $o, $d, $user) {
            $app = AppApplication::whereKey($a->getKey())->lockForUpdate()->firstOrFail();
            $latest = $app->offers()->first();
            if ($o?->exists) {
                if (! $latest || $latest->getKey() !== $o->getKey() || $latest->status !== 'DRAFT') {
                    throw ValidationException::withMessages(['document' => 'Only the latest draft offer can be changed.']);
                }
                $latest->update($d);

                return $latest;
            }
            if ($app->status !== 'OFFERING') {
                throw ValidationException::withMessages(['document' => 'An offer can only be made for an application in the Offering stage.']);
            }
            if ($latest && ! in_array($latest->status, ['DECLINED', 'EXPIRED'], true)) {
                throw ValidationException::withMessages(['document' => 'This application already has an active offer.']);
            }

            return AppOffer::create($d + ['id_application' => $app->getKey(), 'previous_offer_id' => $latest?->getKey(), 'status' => 'DRAFT', 'created_by' => $user->getKey()]);
        });
    }

    public function submit(AppOffer $o): void
    {
        $this->move($o, 'DRAFT', function (AppOffer $fresh) {
            if ((float) $fresh->proposed_salary <= 0) {
                throw ValidationException::withMessages(['document' => 'Proposed salary is required.']);
            }
            $trackId = $fresh->application->vacancy->requisition->id_track;
            $steps = RcrOfferApprovalMatrix::where('id_track', $trackId)->orderBy('approval_seq')->get();
            RecruitmentApprovalService::assertApproversReady($steps, null, 'This track has no offer approval steps configured.');
            app(RecruitmentApprovalService::class)->submitOffer($fresh);
        });
    }

    /** Langkah approval yang menunggu dan milik user ini (seq PENDING terkecil), atau null. */
    public static function pendingFor(AppOffer $o, SecUser $user): ?AppOfferApproval
    {
        if ($o->status !== 'PENDING_APPROVAL') {
            return null;
        }
        $current = AppOfferApproval::where('id_offer', $o->getKey())->where('decision', 'PENDING')->orderBy('approval_seq')->first();

        return RecruitmentApprovalService::ownedBy($current, $user);
    }

    public function decide(AppOffer $o, SecUser $user, string $decision): void
    {
        if (! in_array($decision, ['APPROVED', 'REJECTED'], true)) {
            throw ValidationException::withMessages(['document' => 'Unknown decision.']);
        }
        DB::connection('tm2hrr')->transaction(function () use ($o, $user, $decision) {
            $fresh = AppOffer::whereKey($o->getKey())->lockForUpdate()->firstOrFail();
            $pending = self::pendingFor($fresh, $user);
            if (! $pending) {
                throw new AuthorizationException('This offer is not waiting for your decision.');
            }
            app(RecruitmentApprovalService::class)->decideOffer($pending, $decision);
        });
    }

    public function send(AppOffer $o): void
    {
        $this->move($o, 'APPROVED', fn (AppOffer $f) => $f->update(['status' => 'SENT']));
    }

    public function accepted(AppOffer $o): void
    {
        $this->move($o, 'SENT', fn (AppOffer $f) => $f->update(['status' => 'ACCEPTED']));
    }

    public function declined(AppOffer $o): void
    {
        $this->move($o, 'SENT', fn (AppOffer $f) => $f->update(['status' => 'DECLINED']));
    }

    /** Revisi = putaran baru (DRAFT) dari penawaran DECLINED terbaru, selama lamaran masih OFFERING. */
    public function revise(AppOffer $o, SecUser $user): AppOffer
    {
        return DB::connection('tm2hrr')->transaction(function () use ($o, $user) {
            $app = AppApplication::whereKey($o->id_application)->lockForUpdate()->firstOrFail();
            $latest = $app->offers()->first();
            if (! $latest || $latest->getKey() !== $o->getKey() || ! in_array($latest->status, ['DECLINED', 'EXPIRED'], true)) {
                throw ValidationException::withMessages(['document' => 'Only the latest declined offer can be revised.']);
            }
            if ($app->status !== 'OFFERING') {
                throw ValidationException::withMessages(['document' => 'An offer can only be made for an application in the Offering stage.']);
            }

            return AppOffer::create(['id_application' => $app->getKey(), 'previous_offer_id' => $latest->getKey(), 'proposed_salary' => $latest->proposed_salary, 'employment_type' => $latest->employment_type,
                'start_date' => max($latest->start_date->toDateString(), now()->toDateString()), 'status' => 'DRAFT', 'created_by' => $user->getKey()]);
        });
    }

    /** Ubah status hanya dari status yang benar, dalam transaksi dengan kunci baris dan hanya untuk penawaran terbaru. */
    private function move(AppOffer $o, string $from, callable $do): void
    {
        DB::connection('tm2hrr')->transaction(function () use ($o, $from, $do) {
            $fresh = AppOffer::with('application.vacancy.requisition')->whereKey($o->getKey())->lockForUpdate()->firstOrFail();
            if ($fresh->status !== $from) {
                throw ValidationException::withMessages(['document' => 'This offer is not in the right status for that action.']);
            }
            if ($fresh->application->offers()->value('id_offer') !== $fresh->getKey()) {
                throw ValidationException::withMessages(['document' => 'A newer offer round exists for this application.']);
            }
            $do($fresh);
        });
    }

    /** Putaran penawaran lamaran ini, terbaru di atas; gaji hanya bila boleh dilihat. */
    public static function rounds(AppApplication $a, ?SecUser $user): array
    {
        $offers = $a->offers()->with('approvals')->get();

        return $offers->values()->map(fn (AppOffer $o, int $i) => ['round' => (string) ($offers->count() - $i), 'status' => $o->status, 'type' => (string) (self::TYPES[$o->employment_type] ?? $o->employment_type),
            'start' => $o->start_date?->format('d-M-Y') ?? '—', 'salary' => self::canSeeSalary($o, $user) ? number_format((float) $o->proposed_salary, 2) : '—'])->all();
    }

    /** Langkah approval penawaran: peran dari matriks track, approver, keputusan. */
    public static function steps(AppOffer $o): array
    {
        $o->loadMissing('application.vacancy.requisition');
        $roles = RcrOfferApprovalMatrix::where('id_track', $o->application->vacancy->requisition->id_track)->pluck('approver_role', 'approval_seq')->all();

        return RecruitmentApprovalService::stepRows(AppOfferApproval::where('id_offer', $o->getKey())->orderBy('approval_seq')->get(), $roles);
    }

    /** Riwayat tab History: dibuat dan tiap keputusan, terbaru di atas. */
    public function history(AppOffer $o): array
    {
        $rows = [['at' => $o->created_at?->toDateTimeString(), 'by' => (string) (SecUser::whereKey($o->created_by)->value('name') ?? ''), 'action' => 'Created', 'note' => $o->previous_offer_id ? 'Revision of offer #'.$o->previous_offer_id : '']];
        $steps = self::steps($o);
        foreach (AppOfferApproval::where('id_offer', $o->getKey())->orderBy('approval_seq')->get() as $i => $a) {
            if ($a->decided_at) {
                $rows[] = ['at' => $a->decided_at->toDateTimeString(), 'by' => $steps[$i]['approver'] ?? '', 'action' => ucfirst(strtolower($a->decision)), 'note' => $steps[$i]['role'] ?? ''];
            }
        }

        return array_reverse($rows);
    }
}
