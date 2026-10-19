<?php

namespace App\Support\V2\Grid\Rcr;

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\AppInterviewSchedule;
use App\Models\Hrr\CndCandidate;
use App\Models\Sys\SecUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Grid Interview Schedule (jadwal interview per lamaran); Open membuka form v2 hrm-rcr-interview-form (baca-saja, Mark Done / Cancel), + New Record menjadwalkan lamaran yang menunggu. Kalender v1 belum ada padanannya.
 * Sumber hulu  : tm2hrr.app_interview_schedule + app_application + cnd_candidate + sec_user.
 * Input        : Pencarian, filter Stage / Interviewer / Status, kotak status.
 * Output       : Baris grid (hanya baca; perubahan lewat form).
 * Dipakai oleh : Menu HRM > Recruitment (v2:hrm-rcr-interview), layar hrm-rcr-interview-form (kembali ke daftar).
 * Status       : Aktif. Halaman v1 pages::recruitment.interview-schedule sudah dipindah ke tmerp_unused (aturan D.25, T7k HRM).
 */
class InterviewGrid extends RcrGrid
{
    private array $users = [];

    public function slug(): string
    {
        return 'hrm-rcr-interview';
    }

    public function title(): string
    {
        return 'Interview Schedule';
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'hrm-rcr-interview-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return route('v2.screen', 'hrm-rcr-interview-form').'?new=1';
    }

    protected function models(): array
    {
        return [AppInterviewSchedule::class, AppApplication::class, CndCandidate::class];
    }

    public function search(): string
    {
        return 'Search candidate, stage, location...';
    }

    protected function query(): Builder
    {
        return AppInterviewSchedule::query()->with('application.candidate')->orderByDesc('scheduled_at');
    }

    protected function prefetch(Collection $items): void
    {
        $this->users = SecUser::query()->whereKey($items->pluck('interviewer_id')->filter()->unique()->all())->pluck('name', 'id_user')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Candidate / Phone', 'type' => 'doc', 'party' => 'phone'],
            ['key' => 'stage', 'label' => 'Stage'],
            ['key' => 'at', 'label' => 'Schedule', 'type' => 'date'],
            ['key' => 'time', 'label' => 'Time'],
            ['key' => 'interviewer', 'label' => 'Interviewer'],
            ['key' => 'location', 'label' => 'Location'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['SCHEDULED' => 'info', 'DONE' => 'success', 'CANCELLED' => 'danger', 'NO_SHOW' => 'warn']],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'name' => $m->application?->candidate?->name, 'phone' => $m->application?->candidate?->phone, 'stage' => $m->stage_code,
            'at' => $m->scheduled_at?->toDateString(), 'time' => $m->scheduled_at?->format('H:i'), 'interviewer' => $this->users[$m->interviewer_id] ?? null,
            'location' => $m->location, 'status' => $m->status,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Scheduled', 'where' => ['status' => 'SCHEDULED'], 'tone' => 'info'],
            ['label' => 'Done', 'where' => ['status' => 'DONE'], 'tone' => 'success'],
        ];
    }

    public function filters(): array
    {
        return ['stage' => 'Stage', 'interviewer' => 'Interviewer', 'status' => 'Status'];
    }
}
