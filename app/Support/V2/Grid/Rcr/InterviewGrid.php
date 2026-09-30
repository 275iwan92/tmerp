<?php

namespace App\Support\V2\Grid\Rcr;

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\AppInterviewSchedule;
use App\Models\Hrr\CndCandidate;
use App\Models\Sys\SecUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Jadwal Interview (v1: recruitment.interview-schedule; ?open={id} memilih jadwal itu di panel kanan v1).
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

    protected function v1Route(): ?string
    {
        return 'hrm.recruitment.interview-schedule';
    }

    public function newUrl(): ?string
    {
        return null;
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
            ['key' => 'name', 'label' => 'Candidate', 'type' => 'identity', 'code' => 'phone'],
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
