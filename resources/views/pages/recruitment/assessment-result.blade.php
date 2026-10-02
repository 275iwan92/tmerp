<?php

use App\Livewire\Concerns\HasGridPagination;
use App\Models\Hrr\AppApplication;
use App\Models\Hrr\AppAssessmentResult;
use App\Models\Hrr\AppPipelineStageConfig;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// L6 -- docs/recruitment_tier4.md SECTION 11. Wajib diisi terstruktur sebelum
// bisa "Lanjutkan ke Stage Berikutnya" di L4 (Tier2 §4) -- guard-nya ada di
// pipeline-board.blade.php::advance(), layar ini tempat mengisinya.
new #[Title('Hasil Psikotes/Interview — TELASIH MAKMUR ERP')] class extends Component
{
    use WithPagination;
    use HasGridPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $id_application = null;

    public string $stage_code = '';

    public ?float $score = null;

    public string $recommendation = 'LANJUT';

    public string $notes = '';

    protected const RECS = ['LANJUT' => 'Proceed', 'TIDAK_LANJUT' => 'Do Not Proceed', 'PERTIMBANGAN' => 'Under Consideration'];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function recommendations(): array
    {
        return self::RECS;
    }

    #[Computed]
    public function candidatesAwaiting()
    {
        $stageConfigs = AppPipelineStageConfig::whereIn('stage_code', ['PSIKOTES', 'INTERVIEW_HR', 'INTERVIEW_USER'])->get();
        $apps = AppApplication::with(['candidate', 'vacancy.requisition'])->where('status', 'IN_PROGRESS')->get();

        return $apps->map(function ($app) use ($stageConfigs) {
            $trackId = $app->vacancy->requisition->id_track ?? null;
            $stage = $stageConfigs->firstWhere(fn ($s) => $s->id_track === $trackId && $s->stage_seq === $app->current_stage_seq);

            if (! $stage) {
                return null;
            }

            $hasResult = AppAssessmentResult::where('id_application', $app->id_application)->where('stage_code', $stage->stage_code)->exists();

            return $hasResult ? null : (object) ['app' => $app, 'stage' => $stage];
        })->filter()->values();
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'awaiting' => $this->candidatesAwaiting->count(),
            'total' => AppAssessmentResult::count(),
            'lanjut' => AppAssessmentResult::where('recommendation', 'LANJUT')->count(),
        ];
    }

    #[Computed]
    public function rows()
    {
        return AppAssessmentResult::query()
            ->with('application.candidate')
            ->when($this->search, fn ($q) => $q->whereHas('application.candidate', fn ($q2) => $q2->where('name', 'like', "%{$this->search}%")))
            ->orderByDesc('id_result')
            ->paginate($this->perPage);
    }

    public function openNew(int $applicationId, string $stageCode): void
    {
        $this->id_application = $applicationId;
        $this->stage_code = $stageCode;
        $this->score = null;
        $this->recommendation = 'LANJUT';
        $this->notes = '';
        $this->showModal = true;
    }

    public function save(): void
    {
        if (trim($this->notes) === '') {
            $this->addError('form', t('Notes are required.'));

            return;
        }

        AppAssessmentResult::create([
            'id_application' => $this->id_application,
            'stage_code' => $this->stage_code,
            'assessor_id' => Auth::id(),
            'score' => $this->score,
            'recommendation' => $this->recommendation,
            'notes' => $this->notes,
            'assessed_at' => now(),
        ]);

        $this->showModal = false;
        unset($this->rows, $this->candidatesAwaiting, $this->stats);
    }
};
?>

<div class="max-w-[1400px] mx-auto my-5 px-4">
    <div class="grid three mb-3.5">
        <div class="rounded-lg p-3.5 shadow-sm bg-[#fef6e7] border border-[#f5e0b3]">
            <p class="text-[10.5px] font-bold uppercase text-[#8a6d00]">{{ t('Awaiting Input') }}</p>
            <p class="text-xl font-extrabold text-[#b45309] mt-0.5">{{ $this->stats['awaiting'] }}</p>
        </div>
        <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
            <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">{{ t('Total Results') }}</p>
            <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->stats['total'] }}</p>
        </div>
        <div class="rounded-lg p-3.5 shadow-sm bg-[#e9f9ef] border border-[#c8ecd6]">
            <p class="text-[10.5px] font-bold uppercase text-[#2f7a52]">{{ t('Recommended to Proceed') }}</p>
            <p class="text-xl font-extrabold text-[#16a34a] mt-0.5">{{ $this->stats['lanjut'] }}</p>
        </div>
    </div>

    @if ($this->candidatesAwaiting->isNotEmpty())
        <div class="bg-white border border-[#d7dbe2] rounded-md p-3 mb-3.5">
            <p class="text-[10.5px] font-bold text-muted uppercase mb-2">{{ t('Awaiting Input') }}</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($this->candidatesAwaiting as $row)
                    <button type="button" wire:click="openNew({{ $row->app->id_application }}, '{{ $row->stage->stage_code }}')"
                        class="text-[11px] px-2.5 py-1.5 rounded-md border border-[#d7dbe2] hover:bg-[#eef3fb]">
                        {{ $row->app->candidate->name ?? '—' }} <span class="text-muted">— {{ $row->stage->stage_name }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    <div class="flex items-center gap-2 bg-white border border-[#d7dbe2] rounded-md px-2.5 py-2 overflow-x-auto mb-3">
        <div class="flex items-center gap-1.5 shrink-0">
            <span class="text-sm">🔍</span>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search candidate name…') }}"
                class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
        </div>
    </div>

    <div class="bg-white border border-[#d7dbe2] rounded-md overflow-hidden">
        <table class="w-full text-xs">
            <thead class="text-[10px] text-muted uppercase border-b border-[#eef1f5] bg-[#f4f6fa]">
                <tr>
                    <th class="text-left px-3 py-2">{{ t('Candidate') }}</th>
                    <th class="text-left px-3 py-2">{{ t('Stage') }}</th>
                    <th class="text-right px-3 py-2">{{ t('Score') }}</th>
                    <th class="text-left px-3 py-2">{{ t('Recommendation') }}</th>
                    <th class="text-left px-3 py-2">{{ t('Notes') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#eef1f5]">
                @forelse ($this->rows as $r)
                    <tr wire:key="assess-{{ $r->id_result }}">
                        <td class="px-3 py-2 font-semibold">{{ $r->application->candidate->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $r->stage_code }}</td>
                        <td class="px-3 py-2 text-right font-mono">{{ $r->score ?? '—' }}</td>
                        <td class="px-3 py-2">
                            <span class="text-[10.5px] font-bold px-2 py-0.5 rounded-full {{ match ($r->recommendation) {
                                'LANJUT' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                                'TIDAK_LANJUT' => 'bg-[#fbeaea] text-[#a3241f]',
                                default => 'bg-[#fff5e0] text-[#8a5a00]',
                            } }}">{{ t(self::RECS[$r->recommendation] ?? $r->recommendation) }}</span>
                        </td>
                        <td class="px-3 py-2 text-muted">{{ Str::limit($r->notes, 60) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-center text-muted text-xs">{{ t('No assessment results yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('components.grid.pagination-footer', ['paginator' => $this->rows])

    @if ($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-[60] p-4" wire:click.self="$set('showModal', false)">
            <div class="bg-white rounded-xl w-full max-w-[420px] p-5">
                <h3 class="text-sm font-bold text-navy mb-3">{{ t('Enter Result') }} — {{ $stage_code }}</h3>
                @error('form') <p class="text-[11px] text-[#a3241f] mb-2">⚠ {{ $message }}</p> @enderror
                <div class="space-y-3">
                    <x-form.input :label="t('Score')" type="number" step="0.01" wire:model="score" :placeholder="t('optional')" />
                    <x-form.select wire:model="recommendation" :options="array_map('t', $this->recommendations)" :placeholder="false">
                        <x-slot:label>{{ t('Recommendation') }}</x-slot:label>
                    </x-form.select>
                    <x-form.textarea wire:model="notes" rows="3"><x-slot:label>{{ t('Notes') }}</x-slot:label></x-form.textarea>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" wire:click="$set('showModal', false)" class="border border-[#d7dbe2] text-xs font-bold px-4 py-2 rounded-md">{{ t('Cancel') }}</button>
                    <button type="button" wire:click="save" class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">{{ t('Save') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
