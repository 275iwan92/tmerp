<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\AppAssessmentResult;
use App\Models\Hrr\AppPipelineStageConfig;
use App\Models\Hrr\VacVacancy;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

// HRM > Recruitment > Pipeline Board (v2): kolom = tahap track lowongan (app_pipeline_stage_config), kartu = lamaran aktif (APPLIED / IN_PROGRESS / OFFERING).
// Aturan sama dengan layar v1 (docs/recruitment_tier4.md §9, deviasi sadar user 2026-08-29): kartu hanya boleh maju TEPAT satu tahap (tidak bisa lompat);
// tahap Psikotes / Interview wajib punya hasil assessment terstruktur dulu (Tier 2 §4); Reject wajib beralasan; Withdraw = kandidat mundur.
// Papan = x-dl::kanban dengan :move-url; drag di layar hanya usulan, server memutuskan dan kartu kembali bila ditolak.
final class RecruitmentPipeline
{
    private const ASSESSED_STAGES = ['PSIKOTES', 'INTERVIEW_HR', 'INTERVIEW_USER'];

    private const ACTIVE = ['APPLIED', 'IN_PROGRESS', 'OFFERING'];

    private const TONES = ['neutral', 'accent', 'info', 'violet', 'warn', 'success'];

    /** @return Collection<int, VacVacancy> */
    public static function vacancies(): Collection
    {
        return VacVacancy::where('is_published', true)->orderByDesc('id_vacancy')->get();
    }

    public static function defaultVacancy(): ?int
    {
        return VacVacancy::where('is_published', true)->orderByDesc('id_vacancy')->value('id_vacancy');
    }

    public static function vacancy(?int $id): ?VacVacancy
    {
        return $id ? VacVacancy::with('requisition.track')->find($id) : null;
    }

    /** @return Collection<int, AppPipelineStageConfig> */
    public static function stages(VacVacancy $vacancy): Collection
    {
        return AppPipelineStageConfig::where('id_track', $vacancy->requisition->id_track)->orderBy('stage_seq')->get();
    }

    /** @return array{columns: list<array<string, mixed>>, cards: list<array<string, mixed>>, transitions: array<string, list<string>>} */
    public static function board(VacVacancy $vacancy): array
    {
        $stages = self::stages($vacancy);
        $columns = $stages->values()->map(fn ($s, $i) => ['key' => (string) $s->stage_seq, 'label' => $s->stage_name, 'tone' => self::TONES[$i % count(self::TONES)]])->all();
        $transitions = [];
        foreach ($stages as $s) {
            $transitions[(string) $s->stage_seq] = $stages->where('stage_seq', $s->stage_seq + 1)->map(fn ($n) => (string) $n->stage_seq)->values()->all();
        }
        $cards = AppApplication::with('candidate')->where('id_vacancy', $vacancy->id_vacancy)->whereIn('status', self::ACTIVE)->get()
            ->map(fn (AppApplication $a) => ['id' => $a->id_application, 'col' => (string) $a->current_stage_seq, 'title' => $a->candidate->name ?? '—', 'desc' => (string) ($a->candidate->national_id ?? '')])->all();

        return ['columns' => $columns, 'cards' => $cards, 'transitions' => $transitions];
    }

    /** @return Collection<int, AppApplication> lamaran aktif lowongan ini (untuk tabel Reject / Withdraw) */
    public static function active(VacVacancy $vacancy): Collection
    {
        return AppApplication::with('candidate')->where('id_vacancy', $vacancy->id_vacancy)->whereIn('status', self::ACTIVE)->orderBy('current_stage_seq')->get();
    }

    /** Pindah tahap: hanya maju tepat satu tahap + guard assessment. Menolak dengan ValidationException (pesan sudah diterjemahkan). */
    public static function move(int $applicationId, int $targetSeq): void
    {
        $app = AppApplication::whereIn('status', self::ACTIVE)->findOrFail($applicationId);
        $trackId = self::vacancy($app->id_vacancy)->requisition->id_track;
        $current = AppPipelineStageConfig::where('id_track', $trackId)->where('stage_seq', $app->current_stage_seq)->first();
        if (! $current) {
            throw ValidationException::withMessages(['advance' => t('This application has no valid stage.')]);
        }
        if ($targetSeq !== $current->stage_seq + 1) {
            throw ValidationException::withMessages(['advance' => t('A card can only move forward one stage at a time; stages cannot be skipped.')]);
        }
        if (in_array($current->stage_code, self::ASSESSED_STAGES, true) && ! AppAssessmentResult::where('id_application', $applicationId)->where('stage_code', $current->stage_code)->exists()) {
            throw ValidationException::withMessages(['advance' => tf('Fill in the :stage result first (Assessment Result screen) before moving on.', ['stage' => $current->stage_name])]);
        }
        $next = AppPipelineStageConfig::where('id_track', $trackId)->where('stage_seq', $targetSeq)->first()
            ?? throw ValidationException::withMessages(['advance' => t('There is no next stage.')]);
        $app->update(['current_stage_seq' => $next->stage_seq, 'status' => $next->stage_code === 'OFFERING' ? 'OFFERING' : 'IN_PROGRESS']);
    }

    /** Tombol Continue = pindah ke tahap berikutnya persis. */
    public static function advance(int $applicationId): void
    {
        $app = AppApplication::whereIn('status', self::ACTIVE)->findOrFail($applicationId);
        self::move($applicationId, (int) $app->current_stage_seq + 1);
    }

    public static function reject(int $applicationId, string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reject' => t('A reject reason is required.')]);
        }
        AppApplication::whereIn('status', self::ACTIVE)->findOrFail($applicationId)->update(['status' => 'REJECTED', 'reject_reason' => trim($reason)]);
    }

    public static function withdraw(int $applicationId): void
    {
        AppApplication::whereIn('status', self::ACTIVE)->findOrFail($applicationId)->update(['status' => 'WITHDRAWN']);
    }
}
