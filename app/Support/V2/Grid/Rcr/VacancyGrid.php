<?php

namespace App\Support\V2\Grid\Rcr;

use App\Models\Hrr\RcrRequisition;
use App\Models\Hrr\VacVacancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Grid Job Vacancy (lowongan kerja dan monitoring channel); Open membuka form v2 hrm-rcr-vacancy-form (baca-saja, Close vacancy), + New Record menerbitkan lowongan dari requisition APPROVED.
 * Sumber hulu  : tm2hrr.vac_vacancy + rcr_requisition + app_application.
 * Input        : Pencarian, filter Channel / Status, kotak status.
 * Output       : Baris grid (hanya baca; perubahan lewat form).
 * Dipakai oleh : Menu HRM > Recruitment (v2:hrm-rcr-vacancy), layar hrm-rcr-vacancy-form (kembali ke daftar), widget Recruitment Pipeline.
 * Status       : Aktif. Halaman v1 pages::recruitment.vacancy sudah dipindah ke tmerp_unused (aturan D.25, T7g HRM).
 */
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

    public function openUrl(): ?string
    {
        return route('v2.screen', 'hrm-rcr-vacancy-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return route('v2.screen', 'hrm-rcr-vacancy-form').'?new=1';
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
