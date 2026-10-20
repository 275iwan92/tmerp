<?php

/*
 * REMARK
 * Tujuan       : Hasil Psikotes / Interview terstruktur (app_assessment_result) dari layar v2 hrm-rcr-assessment-form: satu hasil per lamaran per tahap, wajib ada sebelum kartu pipeline boleh maju dari tahap itu (guard di RecruitmentPipeline::move). Hasil yang sudah ada baca-saja.
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::recruitment.assessment-result (diarsipkan di tmerp_unused/recruitment_assessment_result_v1_2026-10-19.blade.php): antrean "Awaiting Result" = lamaran IN_PROGRESS yang tahap sekarang (track requisition, stage_seq) berkode PSIKOTES / INTERVIEW_HR / INTERVIEW_USER dan belum punya hasil tahap itu; simpan = id_application, stage_code, assessor_id (user login), score, recommendation, notes wajib, assessed_at sekarang. Baru (Jo 2026-10-19): tahap diturunkan server dari lamaran (bukan dikirim browser), lamaran harus masih menunggu hasil (cegah ganda), skor 0-100, recommendation dari daftar.
 * Input        : id_application, recommendation, score (opsional), notes; id user penilai.
 * Output       : tm2hrr.app_assessment_result bertambah satu baris; ValidationException (kunci id_application / field) bila aturan dilanggar.
 * Dipakai oleh : routes/v2_hrm.php (v2.hrm.assessment.save), layar hrm-rcr-assessment-form, lookup rcr_assessment_awaiting, tes AssessmentResultServiceTest.
 * Status       : Aktif (T7i HRM, 2026-10-19); antrean tahap (pendingStage, awaitingSearch/Find) diwarisi dari PipelineStageQueue (2026-10-20).
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

final class AssessmentResultService extends PipelineStageQueue
{
    public const RECS = ['LANJUT' => 'Proceed', 'TIDAK_LANJUT' => 'Do Not Proceed', 'PERTIMBANGAN' => 'Consider'];

    public const STAGES = ['PSIKOTES', 'INTERVIEW_HR', 'INTERVIEW_USER'];

    protected static function stages(): array
    {
        return self::STAGES;
    }

    protected static function handled(AppApplication $a, AppPipelineStageConfig $stage): bool
    {
        return AppAssessmentResult::where('id_application', $a->getKey())->where('stage_code', $stage->stage_code)->exists();
    }

    protected static function vacancyText(AppApplication $a): string
    {
        return (string) ($a->vacancy?->vacancy_code ?? '');
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
}
