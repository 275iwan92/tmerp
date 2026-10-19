<?php

/*
 * REMARK
 * Tujuan       : Hasil Psikotes / Interview terstruktur (app_assessment_result) dari layar v2 hrm-rcr-assessment-form: satu hasil per lamaran per tahap, wajib ada sebelum kartu pipeline boleh maju dari tahap itu (guard di RecruitmentPipeline::move). Hasil yang sudah ada baca-saja.
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::recruitment.assessment-result (diarsipkan di tmerp_unused/recruitment_assessment_result_v1_2026-10-19.blade.php): antrean "Awaiting Result" = lamaran IN_PROGRESS yang tahap sekarang (track requisition, stage_seq) berkode PSIKOTES / INTERVIEW_HR / INTERVIEW_USER dan belum punya hasil tahap itu; simpan = id_application, stage_code, assessor_id (user login), score, recommendation, notes wajib, assessed_at sekarang. Baru (Jo 2026-10-19): tahap diturunkan server dari lamaran (bukan dikirim browser), lamaran harus masih menunggu hasil (cegah ganda), skor 0-100, recommendation dari daftar.
 * Input        : id_application, recommendation, score (opsional), notes; id user penilai.
 * Output       : tm2hrr.app_assessment_result bertambah satu baris; ValidationException (kunci id_application / field) bila aturan dilanggar.
 * Dipakai oleh : routes/v2_hrm.php (v2.hrm.assessment.save), layar hrm-rcr-assessment-form, lookup rcr_assessment_awaiting, tes AssessmentResultServiceTest.
 * Status       : Aktif (T7i HRM, 2026-10-19).
 */

namespace App\Services\Recruitment;

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\AppAssessmentResult;
use App\Models\Hrr\AppPipelineStageConfig;
use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class AssessmentResultService
{
    public const RECS = ['LANJUT' => 'Proceed', 'TIDAK_LANJUT' => 'Do Not Proceed', 'PERTIMBANGAN' => 'Consider'];

    public const STAGES = ['PSIKOTES', 'INTERVIEW_HR', 'INTERVIEW_USER'];

    /** Tahap penilaian lamaran ini sekarang, atau null bila lamaran tidak sedang menunggu hasil. */
    public static function pendingStage(AppApplication $a): ?AppPipelineStageConfig
    {
        if ($a->status !== 'IN_PROGRESS') {
            return null;
        }
        $a->loadMissing('vacancy.requisition');
        $stage = AppPipelineStageConfig::where('id_track', $a->vacancy?->requisition?->id_track)->where('stage_seq', $a->current_stage_seq)->whereIn('stage_code', self::STAGES)->first();
        if (! $stage || AppAssessmentResult::where('id_application', $a->getKey())->where('stage_code', $stage->stage_code)->exists()) {
            return null;
        }

        return $stage;
    }

    public function record(array $in, int $userId): AppAssessmentResult
    {
        $d = Validator::make($in, [
            'id_application' => ['required', 'integer'],
            'recommendation' => ['required', Rule::in(array_keys(self::RECS))],
            'score' => ['nullable', 'numeric', 'between:0,100'],
            'notes' => ['required', 'string', 'max:5000'],
        ], ['notes.required' => 'Notes are required.', 'id_application.required' => 'Choose the application.'], ['score' => 'Score', 'notes' => 'Notes'])->validate();

        return DB::connection('tm2hrr')->transaction(function () use ($d, $userId) {
            $a = AppApplication::whereKey((int) $d['id_application'])->lockForUpdate()->first();
            $stage = $a ? self::pendingStage($a) : null;
            if (! $stage) {
                throw ValidationException::withMessages(['id_application' => 'This application is not waiting for a result.']);
            }

            return AppAssessmentResult::create(['id_application' => $a->getKey(), 'stage_code' => $stage->stage_code, 'assessor_id' => $userId, 'score' => ($d['score'] ?? '') === '' ? null : $d['score'],
                'recommendation' => $d['recommendation'], 'notes' => trim($d['notes']), 'assessed_at' => now()]);
        });
    }

    /** Riwayat tab History hasil yang ada. */
    public function history(AppAssessmentResult $r): array
    {
        $by = SecUser::whereKey($r->assessor_id)->value('name') ?? '';

        return [['at' => $r->assessed_at?->toDateTimeString(), 'by' => (string) $by, 'action' => 'Recorded', 'note' => $r->stage_code.' · '.(self::RECS[$r->recommendation] ?? $r->recommendation)]];
    }

    /** @return list<array<string, mixed>> lamaran yang menunggu hasil, untuk lookup */
    public static function awaitingSearch(string $q, int $limit = 30): array
    {
        return AppApplication::with(['candidate', 'vacancy.requisition'])->where('status', 'IN_PROGRESS')
            ->when($q !== '', fn ($w) => $w->whereHas('candidate', fn ($c) => $c->where('name', 'like', "%{$q}%")))
            ->orderByDesc('id_application')->get()
            ->map(fn (AppApplication $a) => ($s = self::pendingStage($a)) ? self::row($a, $s) : null)->filter()->take($limit)->values()->all();
    }

    /** @return array<string, mixed>|null */
    public static function awaitingFind(mixed $value): ?array
    {
        $a = AppApplication::with(['candidate', 'vacancy.requisition'])->find((int) $value);
        $s = $a ? self::pendingStage($a) : null;

        return $s ? self::row($a, $s) : null;
    }

    private static function row(AppApplication $a, AppPipelineStageConfig $s): array
    {
        $name = (string) ($a->candidate?->name ?? '—');

        return ['id' => (int) $a->id_application, 'candidate' => $name, 'stage' => (string) $s->stage_name, 'vacancy' => (string) ($a->vacancy?->vacancy_code ?? ''), 'label' => $name.' — '.$s->stage_name];
    }
}
