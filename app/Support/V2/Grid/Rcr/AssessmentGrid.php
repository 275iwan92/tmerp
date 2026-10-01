<?php

namespace App\Support\V2\Grid\Rcr;

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\AppAssessmentResult;
use App\Models\Hrr\CndCandidate;
use App\Models\Sys\SecUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Input Hasil Psikotes/Interview (v1: recruitment.assessment-result). Input baru dimulai dari antrean tahap di v1
// (butuh lamaran + tahap), jadi grid ini daftar hasil; New membuka layar v1.
class AssessmentGrid extends RcrGrid
{
    private array $users = [];

    public function slug(): string
    {
        return 'hrm-rcr-assessment';
    }

    public function title(): string
    {
        return 'Assessment Result';
    }

    public function openUrl(): ?string
    {
        return null;
    }

    public function newUrl(): ?string
    {
        return route('hrm.recruitment.assessment-result');
    }

    protected function models(): array
    {
        return [AppAssessmentResult::class, AppApplication::class, CndCandidate::class];
    }

    public function search(): string
    {
        return 'Search candidate, stage, notes...';
    }

    protected function query(): Builder
    {
        return AppAssessmentResult::query()->with('application.candidate')->orderByDesc('assessed_at');
    }

    protected function prefetch(Collection $items): void
    {
        $this->users = SecUser::query()->whereKey($items->pluck('assessor_id')->filter()->unique()->all())->pluck('name', 'id_user')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Candidate / Phone', 'type' => 'doc', 'party' => 'phone'],
            ['key' => 'stage', 'label' => 'Stage'],
            ['key' => 'score', 'label' => 'Score', 'type' => 'number'],
            ['key' => 'recommendation', 'label' => 'Recommendation', 'type' => 'badge', 'tones' => ['LANJUT' => 'success', 'TIDAK_LANJUT' => 'danger', 'PERTIMBANGKAN' => 'warn']],
            ['key' => 'assessor', 'label' => 'Assessor'],
            ['key' => 'at', 'label' => 'Assessed', 'type' => 'date'],
            ['key' => 'notes', 'label' => 'Notes'],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'name' => $m->application?->candidate?->name, 'phone' => $m->application?->candidate?->phone, 'stage' => $m->stage_code,
            'score' => $m->score !== null ? (float) $m->score : null, 'recommendation' => $m->recommendation,
            'assessor' => $this->users[$m->assessor_id] ?? null, 'at' => $m->assessed_at?->toDateString(), 'notes' => $m->notes,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Proceed', 'where' => ['recommendation' => 'LANJUT'], 'tone' => 'success'],
            ['label' => 'Stop', 'where' => ['recommendation' => 'TIDAK_LANJUT'], 'tone' => 'danger'],
        ];
    }

    public function filters(): array
    {
        return ['stage' => 'Stage', 'recommendation' => 'Recommendation', 'assessor' => 'Assessor'];
    }
}
