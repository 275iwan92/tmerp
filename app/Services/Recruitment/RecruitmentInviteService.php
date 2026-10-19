<?php

/*
 * REMARK
 * Tujuan       : Talent Pool (layar v2 rcr-talent-pool-form): mengundang kandidat yang sudah ada ke lowongan terbuka tanpa mengisi ulang datanya = satu baris lamaran baru (app_application) di tahap 1.
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::recruitment.talent-pool (diarsipkan di tmerp_unused/recruitment_talent_pool_v1_2026-10-19.blade.php): lamaran baru = id_candidate, id_vacancy, current_stage_seq 1, status APPLIED, applied_at sekarang. Baru (Jo 2026-10-19): server menolak lowongan yang belum terbit atau sudah ditutup, dan kandidat yang masih punya lamaran aktif (APPLIED / IN_PROGRESS / OFFERING) pada lowongan yang sama (v1 tidak mengecek keduanya).
 * Input        : kandidat (cnd_candidate), id_vacancy.
 * Output       : tm2hrr.app_application bertambah satu baris; ValidationException (kunci id_vacancy) bila aturan dilanggar. Data kandidat tidak diubah.
 * Dipakai oleh : routes/v2_hrm.php (v2.hrm.talentpool.invite), layar rcr-talent-pool-form, lookup rcr_vacancy_open, tes RecruitmentInviteServiceTest.
 * Status       : Aktif (T7h HRM, 2026-10-19).
 */

namespace App\Services\Recruitment;

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\CndCandidate;
use App\Models\Hrr\VacVacancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecruitmentInviteService
{
    public const ACTIVE = ['APPLIED', 'IN_PROGRESS', 'OFFERING'];

    public function invite(CndCandidate $c, mixed $vacancyId): AppApplication
    {
        $id = (int) $vacancyId;
        if ($id <= 0) {
            throw ValidationException::withMessages(['id_vacancy' => 'Choose a destination vacancy.']);
        }

        return DB::connection('tm2hrr')->transaction(function () use ($c, $id) {
            $v = VacVacancy::whereKey($id)->lockForUpdate()->first();
            if (! $v) {
                throw ValidationException::withMessages(['id_vacancy' => 'Choose a destination vacancy.']);
            }
            if (! $v->is_published || $v->closed_at) {
                throw ValidationException::withMessages(['id_vacancy' => 'This vacancy is not open.']);
            }
            if (AppApplication::where('id_candidate', $c->getKey())->where('id_vacancy', $id)->whereIn('status', self::ACTIVE)->exists()) {
                throw ValidationException::withMessages(['id_vacancy' => 'This candidate already has an active application for this vacancy.']);
            }

            return AppApplication::create(['id_candidate' => $c->getKey(), 'id_vacancy' => $id, 'current_stage_seq' => 1, 'status' => 'APPLIED', 'applied_at' => now()]);
        });
    }

    /** Riwayat tab History: lamaran kandidat, terbaru di atas. */
    public function history(CndCandidate $c): array
    {
        return AppApplication::with('vacancy')->where('id_candidate', $c->getKey())->orderByDesc('applied_at')->orderByDesc('id_application')->get()
            ->map(fn (AppApplication $a) => ['at' => $a->applied_at?->toDateTimeString(), 'by' => '', 'action' => 'Applied', 'note' => trim(($a->vacancy?->title ?? '—').' · '.$a->status)])->all();
    }

    /** @return list<array<string, mixed>> lowongan terbuka untuk lookup */
    public static function vacancySearch(string $q, int $limit = 30): array
    {
        return VacVacancy::where('is_published', true)->whereNull('closed_at')->where(fn ($w) => $w->where('title', 'like', "%{$q}%")->orWhere('vacancy_code', 'like', "%{$q}%"))
            ->orderByDesc('id_vacancy')->limit($limit)->get()->map(fn (VacVacancy $v) => self::vacancyRow($v))->all();
    }

    /** @return array<string, mixed>|null */
    public static function vacancyFind(mixed $value): ?array
    {
        $v = VacVacancy::find((int) $value);

        return $v ? self::vacancyRow($v) : null;
    }

    private static function vacancyRow(VacVacancy $v): array
    {
        return ['id' => (int) $v->id_vacancy, 'code' => (string) $v->vacancy_code, 'title' => (string) $v->title, 'label' => trim($v->vacancy_code.' — '.$v->title)];
    }
}
