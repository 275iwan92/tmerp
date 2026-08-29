<?php

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\CndCandidate;
use App\Models\Hrr\VacVacancy;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// L3 -- docs/recruitment_tier4.md SECTION 8. Search kandidat existing, panel
// riwayat lamaran, "Undang ke Lowongan" tanpa isi ulang data (Tier2 §6/F8 Tier1).
new #[Title('Talent Pool — TELASIH MAKMUR ERP')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $expandedId = null;

    public ?int $inviteCandidateId = null;

    public ?int $inviteVacancyId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => CndCandidate::count(),
            'active_apps' => AppApplication::whereIn('status', ['APPLIED', 'IN_PROGRESS', 'OFFERING'])->count(),
            'hired' => AppApplication::where('status', 'HIRED')->count(),
        ];
    }

    #[Computed]
    public function rows()
    {
        return CndCandidate::query()
            ->when($this->search, fn ($q) => $q->where(function ($q2) {
                $q2->where('name', 'like', "%{$this->search}%")->orWhere('national_id', 'like', "%{$this->search}%");
            }))
            ->orderBy('name')
            ->paginate(20);
    }

    #[Computed]
    public function openVacancies()
    {
        return VacVacancy::where('is_published', true)->whereNull('closed_at')->orderByDesc('id_vacancy')->get();
    }

    public function toggleExpand(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    #[Computed]
    public function historyFor(): array
    {
        if (! $this->expandedId) {
            return [];
        }

        return AppApplication::with('vacancy')->where('id_candidate', $this->expandedId)->orderByDesc('id_application')->get()->all();
    }

    public function openInvite(int $candidateId): void
    {
        $this->inviteCandidateId = $candidateId;
        $this->inviteVacancyId = null;
    }

    public function invite(): void
    {
        if (! $this->inviteVacancyId) {
            $this->addError('invite', 'Pilih lowongan tujuan.');

            return;
        }

        AppApplication::create([
            'id_candidate' => $this->inviteCandidateId,
            'id_vacancy' => $this->inviteVacancyId,
            'current_stage_seq' => 1,
            'status' => 'APPLIED',
            'applied_at' => now(),
        ]);

        $this->inviteCandidateId = null;
        unset($this->historyFor, $this->stats);
    }

    public function avatarInitials(string $name): string
    {
        $words = array_values(array_filter(explode(' ', trim($name))));

        return count($words) >= 2 ? mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1)) : mb_strtoupper(mb_substr($name, 0, 2));
    }

    private const AVATAR_PALETTE = [
        'from-[#2563eb] to-[#1d4ed8]', 'from-[#10b981] to-[#059669]', 'from-[#ef4444] to-[#dc2626]',
        'from-[#8b5cf6] to-[#6d28d9]', 'from-[#f59e0b] to-[#d97706]', 'from-[#0ea5e9] to-[#0284c7]',
    ];

    public function avatarGradient(int $id): string
    {
        return self::AVATAR_PALETTE[$id % count(self::AVATAR_PALETTE)];
    }
};
?>

<div class="max-w-[1400px] mx-auto my-5 px-4">
    <div class="grid three mb-3.5">
        <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
            <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">Total Kandidat</p>
            <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->stats['total'] }}</p>
        </div>
        <div class="rounded-lg p-3.5 shadow-sm bg-[#fef6e7] border border-[#f5e0b3]">
            <p class="text-[10.5px] font-bold uppercase text-[#8a6d00]">Lamaran Aktif</p>
            <p class="text-xl font-extrabold text-[#b45309] mt-0.5">{{ $this->stats['active_apps'] }}</p>
        </div>
        <div class="rounded-lg p-3.5 shadow-sm bg-[#e9f9ef] border border-[#c8ecd6]">
            <p class="text-[10.5px] font-bold uppercase text-[#2f7a52]">Sudah Diangkat</p>
            <p class="text-xl font-extrabold text-[#16a34a] mt-0.5">{{ $this->stats['hired'] }}</p>
        </div>
    </div>

    <div class="flex items-center gap-2 bg-white border border-[#d7dbe2] rounded-md px-2.5 py-2 overflow-x-auto mb-3">
        <div class="flex items-center gap-1.5 shrink-0">
            <span class="text-sm">🔍</span>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama atau No. KTP…"
                class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
        </div>
    </div>

    <div class="bg-white border border-[#d7dbe2] rounded-md overflow-hidden">
        <table class="w-full text-xs">
            <thead class="text-[10px] text-muted uppercase border-b border-[#eef1f5] bg-[#f4f6fa]">
                <tr>
                    <th class="w-[32px] px-3 py-2"></th>
                    <th class="text-left px-3 py-2">Kandidat</th>
                    <th class="text-left px-3 py-2">Kontak</th>
                    <th class="px-3 py-2 text-right w-[90px]">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#eef1f5]">
                @forelse ($this->rows as $c)
                    <tr wire:key="cnd-{{ $c->id_candidate }}">
                        <td class="px-3 py-2 text-center">
                            <button type="button" wire:click.stop="toggleExpand({{ $c->id_candidate }})" class="text-muted">{{ $this->expandedId === $c->id_candidate ? '▾' : '▸' }}</button>
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ $this->avatarGradient($c->id_candidate) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ $this->avatarInitials($c->name) }}</div>
                                <div>
                                    <div class="font-semibold text-[13px]">{{ $c->name }}</div>
                                    <div class="text-[11px] text-muted font-mono">{{ $c->national_id }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-2">{{ $c->email }}{{ $c->email && $c->phone ? ' · ' : '' }}{{ $c->phone }}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <button type="button" wire:click.stop="openInvite({{ $c->id_candidate }})" title="Undang ke Lowongan"
                                class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">✉</button>
                        </td>
                    </tr>
                    @if ($this->expandedId === $c->id_candidate)
                        <tr wire:key="cnd-hist-{{ $c->id_candidate }}">
                            <td></td>
                            <td colspan="3" class="px-3 py-3 bg-[#f8fafc]">
                                <p class="text-[10.5px] font-bold text-muted uppercase mb-2">Riwayat Lamaran</p>
                                @forelse ($this->historyFor as $app)
                                    <div class="flex items-center justify-between text-[11.5px] py-1 border-b border-[#eef1f5] last:border-0">
                                        <span>{{ $app->vacancy->title ?? '—' }}</span>
                                        <span class="font-mono text-muted">{{ $app->applied_at?->translatedFormat('d M Y') }}</span>
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ match ($app->status) {
                                            'HIRED' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                                            'REJECTED', 'WITHDRAWN' => 'bg-[#fbeaea] text-[#a3241f]',
                                            'OFFERING' => 'bg-[#f1ecfc] text-[#6d28d9]',
                                            default => 'bg-[#fff5e0] text-[#8a5a00]',
                                        } }}">{{ $app->status }}</span>
                                    </div>
                                @empty
                                    <p class="text-[11px] text-muted">Belum pernah melamar.</p>
                                @endforelse
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="4" class="px-3 py-6 text-center text-muted text-xs">Belum ada kandidat di talent pool.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $this->rows->links() }}</div>

    @if ($inviteCandidateId)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-[60] p-4" wire:click.self="$set('inviteCandidateId', null)">
            <div class="bg-white rounded-xl w-full max-w-[420px] p-5">
                <h3 class="text-sm font-bold text-navy mb-3">Undang ke Lowongan</h3>
                @error('invite') <p class="text-[11px] text-[#a3241f] mb-2">⚠ {{ $message }}</p> @enderror
                <x-form.select wire:model="inviteVacancyId" :options="$this->openVacancies->pluck('title', 'id_vacancy')->all()" placeholder="— pilih lowongan —">
                    <x-slot:label>Lowongan Tujuan</x-slot:label>
                </x-form.select>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" wire:click="$set('inviteCandidateId', null)" class="border border-[#d7dbe2] text-xs font-bold px-4 py-2 rounded-md">Batal</button>
                    <button type="button" wire:click="invite" class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">Undang</button>
                </div>
            </div>
        </div>
    @endif
</div>
