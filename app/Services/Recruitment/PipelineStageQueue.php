<?php

/* REMARK
 * Tujuan       : Dasar bersama antrean "lamaran yang menunggu pekerjaan di tahap pipeline" (hasil psikotes/interview, jadwal interview):
 *                tahap sekarang lamaran, ada/tidaknya pekerjaan yang sudah tercatat, pencarian dan pembacaan satu lamaran untuk lookup.
 * Sumber hulu  : app_application, app_pipeline_stage_config (track requisition lamaran + stage_seq sekarang) di tm2hrr.
 * Input        : lamaran (AppApplication); kata cari; id lamaran. Turunan mengisi stages() dan handled().
 * Output       : tahap yang menunggu (AppPipelineStageConfig) atau null; baris lookup {id, candidate, stage, vacancy, label}.
 * Dipakai oleh : AssessmentResultService, InterviewScheduleService (lewat lookup rcr_assessment_awaiting, rcr_interview_awaiting).
 * Status       : Aktif; menggantikan dua salinan kode yang sama (Jo 2026-10-20).
 */

namespace App\Services\Recruitment;

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\AppPipelineStageConfig;

abstract class PipelineStageQueue
{
    /** @return list<string> kode tahap yang dilayani turunan ini */
    abstract protected static function stages(): array;

    /** Sudah ada pekerjaan (hasil / jadwal) untuk lamaran dan tahap ini? */
    abstract protected static function handled(AppApplication $a, AppPipelineStageConfig $stage): bool;

    /** Teks lowongan pada baris lookup. */
    abstract protected static function vacancyText(AppApplication $a): string;

    /** Tahap lamaran ini sekarang bila termasuk stages() dan belum dikerjakan, selain itu null. */
    public static function pendingStage(AppApplication $a): ?AppPipelineStageConfig
    {
        if ($a->status !== 'IN_PROGRESS') {
            return null;
        }
        $a->loadMissing('vacancy.requisition');
        $stage = AppPipelineStageConfig::where('id_track', $a->vacancy?->requisition?->id_track)->where('stage_seq', $a->current_stage_seq)->whereIn('stage_code', static::stages())->first();

        return $stage && ! static::handled($a, $stage) ? $stage : null;
    }

    /** @return list<array<string, mixed>> lamaran yang menunggu, untuk lookup */
    public static function awaitingSearch(string $q, int $limit = 30): array
    {
        return AppApplication::with(['candidate', 'vacancy.requisition'])->where('status', 'IN_PROGRESS')
            ->when($q !== '', fn ($w) => $w->whereHas('candidate', fn ($c) => $c->where('name', 'like', "%{$q}%")))
            ->orderByDesc('id_application')->get()
            ->map(fn (AppApplication $a) => ($s = static::pendingStage($a)) ? static::row($a, $s) : null)->filter()->take($limit)->values()->all();
    }

    /** @return array<string, mixed>|null */
    public static function awaitingFind(mixed $value): ?array
    {
        $a = AppApplication::with(['candidate', 'vacancy.requisition'])->find((int) $value);
        $s = $a ? static::pendingStage($a) : null;

        return $s ? static::row($a, $s) : null;
    }

    private static function row(AppApplication $a, AppPipelineStageConfig $s): array
    {
        $name = (string) ($a->candidate?->name ?? '—');

        return ['id' => (int) $a->id_application, 'candidate' => $name, 'stage' => (string) $s->stage_name, 'vacancy' => static::vacancyText($a), 'label' => $name.' — '.$s->stage_name];
    }
}
