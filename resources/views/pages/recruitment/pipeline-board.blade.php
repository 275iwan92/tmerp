<?php

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\AppAssessmentResult;
use App\Models\Hrr\AppPipelineStageConfig;
use App\Models\Hrr\VacVacancy;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// L4 -- docs/recruitment_tier4.md SECTION 9. Kolom = app_pipeline_stage_config
// milik track vacancy terpilih (beda jumlah kolom per track).
// REVISI 2026-08-29 (instruksi user, deviasi sadar dari Tier4 §15 "TIDAK
// boleh drag-drop bebas"): drag-drop kartu diaktifkan (pola sama
// production/monitoring-dashboard.blade.php: x-data draggedId + x-on:dragstart/
// dragover.prevent/drop), TAPI validasi tetap ditegakkan penuh di server --
// cuma boleh maju TEPAT 1 kolom (tidak bisa lompat tahap), dan tetap wajib
// assessment terstruktur dulu (Tier2 §4) sebelum lolos ke tahap yang
// mensyaratkannya. Drop yang melanggar salah satu aturan itu DITOLAK, kartu
// otomatis kembali (re-render dari state DB yang tak berubah) + pesan error.
// Tombol "Lanjutkan"/Reject/Withdraw tetap ada sbg alternatif non-drag.
new #[Title('Pipeline Board — TELASIH MAKMUR ERP')] class extends Component
{
    public ?int $vacancyId = null;

    public ?int $rejectingId = null;

    public string $rejectReason = '';

    public function mount(): void
    {
        $this->vacancyId = VacVacancy::where('is_published', true)->orderByDesc('id_vacancy')->value('id_vacancy');
    }

    #[Computed]
    public function openVacancies()
    {
        return VacVacancy::where('is_published', true)->orderByDesc('id_vacancy')->get();
    }

    #[Computed]
    public function vacancy(): ?VacVacancy
    {
        return $this->vacancyId ? VacVacancy::with('requisition.track')->find($this->vacancyId) : null;
    }

    #[Computed]
    public function stages()
    {
        if (! $this->vacancy) {
            return collect();
        }

        return AppPipelineStageConfig::where('id_track', $this->vacancy->requisition->id_track)->orderBy('stage_seq')->get();
    }

    #[Computed]
    public function cardsByStage(): array
    {
        if (! $this->vacancyId) {
            return [];
        }

        return AppApplication::with('candidate')
            ->where('id_vacancy', $this->vacancyId)
            ->whereIn('status', ['APPLIED', 'IN_PROGRESS', 'OFFERING'])
            ->get()
            ->groupBy('current_stage_seq')
            ->all();
    }

    // Stage yang wajib assessment terstruktur sebelum boleh lanjut -- Tier2 §4.
    protected function requiresAssessment(string $stageCode): bool
    {
        return in_array($stageCode, ['PSIKOTES', 'INTERVIEW_HR', 'INTERVIEW_USER'], true);
    }

    // Tombol "Lanjutkan" -- selalu ke stage berikutnya persis.
    public function advance(int $applicationId): void
    {
        $app = AppApplication::findOrFail($applicationId);
        $next = AppPipelineStageConfig::where('id_track', $this->vacancy->requisition->id_track)
            ->where('stage_seq', '>', $app->current_stage_seq)->orderBy('stage_seq')->first();

        if (! $next) {
            return;
        }

        $this->moveCard($applicationId, $next->stage_seq);
    }

    // Drag-drop -- target boleh kolom manapun secara visual, TAPI server
    // cuma menerima TEPAT stage_seq+1 (tidak bisa lompat tahap) & tetap
    // menegakkan guard assessment (Tier2 §4). Pelanggaran = ditolak diam-diam
    // ke state semula (unset cardsByStage memaksa re-render dari DB).
    public function moveCard(int $applicationId, int $targetStageSeq): void
    {
        $app = AppApplication::findOrFail($applicationId);
        $trackId = $this->vacancy->requisition->id_track;

        $current = AppPipelineStageConfig::where('id_track', $trackId)->where('stage_seq', $app->current_stage_seq)->first();

        if (! $current) {
            return;
        }

        if ($targetStageSeq !== $current->stage_seq + 1) {
            $this->addError('advance', 'Cuma boleh maju 1 tahap tiap kali digeser, tidak bisa lompat tahap.');
            unset($this->cardsByStage);

            return;
        }

        if ($this->requiresAssessment($current->stage_code)) {
            $hasResult = AppAssessmentResult::where('id_application', $applicationId)->where('stage_code', $current->stage_code)->exists();
            if (! $hasResult) {
                $this->addError('advance', "Isi hasil {$current->stage_name} dulu (layar Input Hasil Psikotes/Interview) sebelum lanjut.");
                unset($this->cardsByStage);

                return;
            }
        }

        $next = AppPipelineStageConfig::where('id_track', $trackId)->where('stage_seq', $targetStageSeq)->first();

        if (! $next) {
            return;
        }

        $app->update([
            'current_stage_seq' => $next->stage_seq,
            'status' => $next->stage_code === 'OFFERING' ? 'OFFERING' : 'IN_PROGRESS',
        ]);

        unset($this->cardsByStage);
    }

    public function openReject(int $id): void
    {
        $this->rejectingId = $id;
        $this->rejectReason = '';
    }

    public function reject(): void
    {
        if (trim($this->rejectReason) === '') {
            $this->addError('reject', 'Alasan reject wajib diisi.');

            return;
        }

        AppApplication::where('id_application', $this->rejectingId)->update(['status' => 'REJECTED', 'reject_reason' => $this->rejectReason]);
        $this->rejectingId = null;
        unset($this->cardsByStage);
    }

    public function withdraw(int $id): void
    {
        AppApplication::where('id_application', $id)->update(['status' => 'WITHDRAWN']);
        unset($this->cardsByStage);
    }
};
?>

<div class="max-w-[1500px] mx-auto my-5 px-4" x-data="{ draggedId: null }">
    <div class="flex items-center gap-2 bg-white border border-[#d7dbe2] rounded-md px-3 py-2.5 mb-4">
        <label class="text-[10.5px] text-muted uppercase font-semibold">Lowongan</label>
        <select wire:model.live="vacancyId" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs min-w-[280px]">
            @foreach ($this->openVacancies as $v)
                <option value="{{ $v->id_vacancy }}">{{ $v->title }} ({{ $v->vacancy_code }})</option>
            @endforeach
        </select>
        <span class="ml-auto text-[10.5px] text-muted">🖱 Geser kartu ke kolom berikutnya, atau pakai tombol "Lanjutkan"</span>
    </div>

    @error('advance') <div class="bg-[#fdecec] border border-[#f5c6c6] text-[#a3453f] text-xs rounded-md px-4 py-2.5 mb-4">⚠ {{ $message }}</div> @enderror

    @if (! $this->vacancy)
        <p class="text-xs text-muted">Belum ada lowongan terbuka.</p>
    @else
        <div class="flex gap-3 overflow-x-auto pb-2">
            @foreach ($this->stages as $stage)
                @php $cards = $this->cardsByStage[$stage->stage_seq] ?? collect(); @endphp
                <div class="w-[280px] shrink-0 bg-[#f4f6fa] rounded-lg p-2.5"
                    x-on:dragover.prevent x-on:drop="$wire.moveCard(draggedId, {{ $stage->stage_seq }}); draggedId = null"
                    x-bind:class="draggedId ? 'ring-2 ring-[#93c5fd]' : ''">
                    <div class="flex items-center justify-between mb-2 px-1">
                        <p class="text-[11px] font-bold text-navy uppercase">{{ $stage->stage_name }}</p>
                        <span class="text-[10px] font-bold text-muted bg-white px-1.5 py-0.5 rounded-full">{{ $cards->count() }}</span>
                    </div>
                    <div class="space-y-2 min-h-[60px]">
                        @forelse ($cards as $card)
                            <div wire:key="card-{{ $card->id_application }}" draggable="true"
                                x-on:dragstart="draggedId = {{ $card->id_application }}"
                                class="bg-white border border-[#d7dbe2] rounded-md p-2.5 shadow-sm cursor-grab active:cursor-grabbing hover:shadow-md">
                                <p class="text-[12.5px] font-semibold">{{ $card->candidate->name ?? '—' }}</p>
                                <p class="text-[10.5px] text-muted font-mono mb-2">{{ $card->candidate->national_id ?? '' }}</p>
                                <div class="flex flex-wrap gap-1">
                                    @if ($stage->stage_code !== 'OFFERING')
                                        <button type="button" wire:click="advance({{ $card->id_application }})" class="text-[10px] font-bold text-white bg-[#1f8a4c] px-2 py-1 rounded">Lanjutkan →</button>
                                    @endif
                                    <button type="button" wire:click="openReject({{ $card->id_application }})" class="text-[10px] font-bold text-[#a3241f] bg-[#fdecec] px-2 py-1 rounded">Reject</button>
                                    <button type="button" wire:click="withdraw({{ $card->id_application }})" wire:confirm="Tandai kandidat ini mundur (withdraw)?" class="text-[10px] font-bold text-muted bg-[#eef1f5] px-2 py-1 rounded">Withdraw</button>
                                </div>
                            </div>
                        @empty
                            <p class="text-[10.5px] text-muted text-center py-4">Kosong</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($rejectingId)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-[60] p-4" wire:click.self="$set('rejectingId', null)">
            <div class="bg-white rounded-xl w-full max-w-[420px] p-5">
                <h3 class="text-sm font-bold text-navy mb-3">Reject Kandidat</h3>
                @error('reject') <p class="text-[11px] text-[#a3241f] mb-2">⚠ {{ $message }}</p> @enderror
                <x-form.textarea wire:model="rejectReason" rows="3"><x-slot:label>Alasan</x-slot:label></x-form.textarea>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" wire:click="$set('rejectingId', null)" class="border border-[#d7dbe2] text-xs font-bold px-4 py-2 rounded-md">Batal</button>
                    <button type="button" wire:click="reject" class="bg-[#a3241f] text-white text-xs font-bold px-4 py-2 rounded-md">Reject</button>
                </div>
            </div>
        </div>
    @endif
</div>
