<?php

namespace App\Support\V2\Grid\Rcr;

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\CndCandidate;
use App\Models\Hrr\VacVacancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Grid Talent Pool: semua kandidat yang pernah melamar + riwayat lamarannya; Open membuka form v2 rcr-talent-pool-form (Invite to vacancy). Kandidat baru masuk lewat portal karir, bukan diketik.
 * Sumber hulu  : tm2hrr.cnd_candidate + app_application + vac_vacancy.
 * Input        : Pencarian, filter Last Vacancy / Last Status, kotak status.
 * Output       : Baris grid (hanya baca; undangan lewat form).
 * Dipakai oleh : Menu HRM > Recruitment (v2:rcr-talent-pool), layar rcr-talent-pool-form (kembali ke daftar).
 * Status       : Aktif. Halaman v1 pages::recruitment.talent-pool sudah dipindah ke tmerp_unused (aturan D.25, T7h HRM).
 */
class TalentPoolGrid extends RcrGrid
{
    public function slug(): string
    {
        return 'rcr-talent-pool';
    }

    public function title(): string
    {
        return 'Talent Pool';
    }

    public function icon(): string
    {
        return 'fa-solid fa-users-viewfinder';
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'rcr-talent-pool-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return null;
    }

    protected function models(): array
    {
        return [CndCandidate::class, AppApplication::class, VacVacancy::class];
    }

    public function search(): string
    {
        return 'Search candidate, phone, vacancy...';
    }

    protected function query(): Builder
    {
        return CndCandidate::query()->with(['applications' => fn ($q) => $q->with('vacancy')->orderByDesc('applied_at')->orderByDesc('id_application')])->orderBy('name');
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Candidate / Phone', 'type' => 'doc', 'party' => 'phone'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'gender', 'label' => 'Gender', 'visible' => false],
            ['key' => 'last_vacancy', 'label' => 'Last Vacancy'],
            ['key' => 'last_applied', 'label' => 'Last Applied', 'type' => 'date'],
            ['key' => 'applications', 'label' => 'Applications', 'type' => 'number'],
            ['key' => 'available', 'label' => 'Available From', 'type' => 'date', 'visible' => false],
            ['key' => 'status', 'label' => 'Last Status', 'type' => 'badge', 'tones' => self::APP_TONES],
        ];
    }

    protected function row(Model $m): array
    {
        $last = $m->applications->first();

        return [
            'name' => $m->name, 'phone' => $m->phone, 'email' => $m->email, 'gender' => $m->gender,
            'last_vacancy' => $last?->vacancy?->title, 'last_applied' => $last?->applied_at?->toDateString(), 'applications' => $m->applications->count(),
            'available' => $m->availability_date?->toDateString(), 'status' => $last?->status,
            'items' => $m->applications->map(fn (AppApplication $a) => ['vacancy' => $a->vacancy?->title, 'code' => $a->vacancy?->vacancy_code,
                'applied' => $a->applied_at?->toDateString(), 'stage' => (int) $a->current_stage_seq, 'status' => $a->status])->all(),
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Applications', 'columns' => [
            ['key' => 'code', 'label' => 'Vacancy Code'],
            ['key' => 'vacancy', 'label' => 'Vacancy', 'strong' => true],
            ['key' => 'applied', 'label' => 'Applied', 'type' => 'date'],
            ['key' => 'stage', 'label' => 'Stage', 'type' => 'number'],
            ['key' => 'status', 'label' => 'Status'],
        ]];
    }

    public function stats(): array
    {
        return $this->appStats();
    }

    public function filters(): array
    {
        return ['last_vacancy' => 'Last Vacancy', 'status' => 'Last Status'];
    }
}
