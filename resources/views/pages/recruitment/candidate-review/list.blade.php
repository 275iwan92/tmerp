<?php

use App\Livewire\Concerns\HasGridPagination;
use App\Models\Hrr\AppApplication;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// Review Formulir Kandidat -- grid worklist utk HR meninjau isian lengkap
// "Data Calon Karyawan" (careers/apply, 11 seksi) per lamaran. Murni
// read-only; keputusan lanjut/reject tahap tetap di Pipeline Board.
new #[Title('Review Formulir Kandidat — TELASIH MAKMUR ERP')] class extends Component
{
    use WithPagination;
    use HasGridPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => AppApplication::count(),
            'baru' => AppApplication::where('status', 'APPLIED')->count(),
            'proses' => AppApplication::whereIn('status', ['IN_PROGRESS', 'OFFERING'])->count(),
            'selesai' => AppApplication::whereIn('status', ['HIRED', 'REJECTED', 'WITHDRAWN'])->count(),
        ];
    }

    #[Computed]
    public function rows()
    {
        return AppApplication::with('candidate', 'vacancy')
            ->when($this->search, fn ($q) => $q->whereHas('candidate', function ($q2) {
                $q2->where('name', 'like', "%{$this->search}%")->orWhere('national_id', 'like', "%{$this->search}%");
            }))
            ->orderByDesc('id_application')
            ->paginate($this->perPage);
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
    <div class="grid mb-3.5">
        <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
            <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">Total Lamaran</p>
            <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->stats['total'] }}</p>
        </div>
        <div class="rounded-lg p-3.5 shadow-sm bg-[#fef6e7] border border-[#f5e0b3]">
            <p class="text-[10.5px] font-bold uppercase text-[#8a6d00]">Baru Masuk</p>
            <p class="text-xl font-extrabold text-[#b45309] mt-0.5">{{ $this->stats['baru'] }}</p>
        </div>
        <div class="rounded-lg p-3.5 shadow-sm bg-[#f1ecfc] border border-[#ddd0f7]">
            <p class="text-[10.5px] font-bold uppercase text-[#5b3f9e]">Dalam Proses</p>
            <p class="text-xl font-extrabold text-[#6d28d9] mt-0.5">{{ $this->stats['proses'] }}</p>
        </div>
        <div class="rounded-lg p-3.5 shadow-sm bg-[#e9f9ef] border border-[#c8ecd6]">
            <p class="text-[10.5px] font-bold uppercase text-[#2f7a52]">Selesai</p>
            <p class="text-xl font-extrabold text-[#16a34a] mt-0.5">{{ $this->stats['selesai'] }}</p>
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
                    <th class="text-left px-3 py-2">Kandidat</th>
                    <th class="text-left px-3 py-2">Lowongan</th>
                    <th class="text-left px-3 py-2">Tanggal Lamar</th>
                    <th class="text-left px-3 py-2">Status</th>
                    <th class="px-3 py-2 text-right w-[70px]">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#eef1f5]">
                @forelse ($this->rows as $r)
                    <tr wire:key="appreview-{{ $r->id_application }}"
                        ondblclick="window.location.href='{{ route('hrm.recruitment.candidate-review.detail', $r->id_application) }}'"
                        class="cursor-pointer hover:bg-[#f8fafc]" title="Klik 2x untuk buka detail">
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ $this->avatarGradient($r->id_application) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ $this->avatarInitials($r->candidate->name ?? '—') }}</div>
                                <div>
                                    <div class="font-semibold text-[13px]">{{ $r->candidate->name ?? '—' }}</div>
                                    <div class="text-[11px] text-muted font-mono">{{ $r->candidate->national_id ?? '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-2">{{ $r->vacancy->title ?? '—' }}</td>
                        <td class="px-3 py-2 font-mono">{{ $r->applied_at?->translatedFormat('d M Y') ?? '—' }}</td>
                        <td class="px-3 py-2">
                            <span class="text-[10.5px] font-bold px-2 py-0.5 rounded-full {{ match ($r->status) {
                                'APPLIED' => 'bg-[#fff5e0] text-[#8a5a00]',
                                'OFFERING' => 'bg-[#f1ecfc] text-[#6d28d9]',
                                'HIRED' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                                'REJECTED', 'WITHDRAWN' => 'bg-[#fbeaea] text-[#a3241f]',
                                default => 'bg-[#eef1f5] text-[#4b5568]',
                            } }}">{{ $r->status }}</span>
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <a href="{{ route('hrm.recruitment.candidate-review.detail', $r->id_application) }}" wire:click.stop title="Buka"
                                class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">↗</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-center text-muted text-xs">Belum ada lamaran masuk.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('components.grid.pagination-footer', ['paginator' => $this->rows])
</div>
