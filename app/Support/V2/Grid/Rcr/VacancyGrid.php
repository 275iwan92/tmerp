<?php

namespace App\Support\V2\Grid\Rcr;

use App\Models\Hrr\RcrRequisition;
use App\Models\Hrr\VacVacancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Posting Lowongan & Monitoring Channel (v1: recruitment.vacancy). v1 hanya punya form Baru (tanpa edit per baris).
class VacancyGrid extends RcrGrid
{
    public function slug(): string
    {
        return 'hrm-rcr-vacancy';
    }

    public function title(): string
    {
        return 'Job Vacancy';
    }

    protected function v1Route(): ?string
    {
        return 'hrm.recruitment.vacancy';
    }

    public function openUrl(): ?string
    {
        return null;
    }

    protected function models(): array
    {
        return [VacVacancy::class, RcrRequisition::class];
    }

    public function search(): string
    {
        return 'Search vacancy, channel, position...';
    }

    protected function query(): Builder
    {
        return VacVacancy::query()->with('requisition')->withCount('applications')->orderByDesc('id_vacancy');
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Vacancy Code / Title', 'type' => 'doc', 'party' => 'title'],
            ['key' => 'requisition', 'label' => 'Requisition'],
            ['key' => 'channel', 'label' => 'Channel'],
            ['key' => 'published', 'label' => 'Published', 'type' => 'date'],
            ['key' => 'closed', 'label' => 'Closed', 'type' => 'date'],
            ['key' => 'applications', 'label' => 'Applicants', 'type' => 'number'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['Open' => 'success', 'Draft' => 'neutral', 'Closed' => 'danger']],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'code' => $m->vacancy_code, 'title' => $m->title, 'requisition' => $m->requisition?->requisition_no, 'channel' => $m->channel,
            'published' => $m->published_at?->toDateString(), 'closed' => $m->closed_at?->toDateString(), 'applications' => (int) $m->applications_count,
            'status' => $m->closed_at ? 'Closed' : ($m->is_published ? 'Open' : 'Draft'),
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Open', 'where' => ['status' => 'Open'], 'tone' => 'success'],
            ['label' => 'Draft', 'where' => ['status' => 'Draft'], 'tone' => 'neutral'],
            ['label' => 'Closed', 'where' => ['status' => 'Closed'], 'tone' => 'danger'],
        ];
    }

    public function filters(): array
    {
        return ['channel' => 'Channel', 'status' => 'Status'];
    }
}
