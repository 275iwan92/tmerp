<?php

namespace App\Support\V2\Grid\Rcr;

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\CndCandidate;
use App\Models\Hrr\VacVacancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Grid Candidate Form Review: semua lamaran (lahir dari sync portal karir, tanpa New); Open membuka tampilan baca-saja formulir kandidat di layar v2 hrm-rcr-review-form.
 * Sumber hulu  : tm2hrr.app_application + cnd_candidate + vac_vacancy.
 * Input        : Pencarian, filter Vacancy / Status, kotak status.
 * Output       : Baris grid (hanya baca).
 * Dipakai oleh : Menu HRM > Recruitment (v2:hrm-rcr-review), layar hrm-rcr-review-form (kembali ke daftar).
 * Status       : Aktif. Halaman v1 pages::recruitment.candidate-review.list dan detail sudah dipindah ke tmerp_unused (aturan D.25, T7n HRM).
 */
class ApplicationGrid extends RcrGrid
{
    public function slug(): string
    {
        return 'hrm-rcr-review';
    }

    public function title(): string
    {
        return 'Candidate Form Review';
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'hrm-rcr-review-form').'?application={id}';
    }

    public function newUrl(): ?string
    {
        return null;
    }

    protected function models(): array
    {
        return [AppApplication::class, CndCandidate::class, VacVacancy::class];
    }

    public function search(): string
    {
        return 'Search candidate, vacancy...';
    }

    protected function query(): Builder
    {
        return AppApplication::query()->with(['candidate', 'vacancy'])->orderByDesc('applied_at')->orderByDesc('id_application');
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Candidate / Phone', 'type' => 'doc', 'party' => 'phone'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'vacancy', 'label' => 'Vacancy'],
            ['key' => 'applied', 'label' => 'Applied', 'type' => 'date'],
            ['key' => 'stage', 'label' => 'Stage', 'type' => 'number'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::APP_TONES],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'name' => $m->candidate?->name, 'phone' => $m->candidate?->phone, 'email' => $m->candidate?->email, 'vacancy' => $m->vacancy?->title,
            'applied' => $m->applied_at?->toDateString(), 'stage' => (int) $m->current_stage_seq, 'status' => $m->status,
        ];
    }

    public function stats(): array
    {
        return $this->appStats();
    }

    public function filters(): array
    {
        return ['vacancy' => 'Vacancy', 'status' => 'Status'];
    }
}
