<?php

use App\Livewire\Concerns\HasGridPagination;
use App\Models\Hrr\RcrRequisition;
use App\Models\Hrr\RcrTrack;
use App\Models\Master\OrgDepartment;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// L1 (grid) -- docs/recruitment_tier4.md SECTION 6. Record lahir dari grid ini
// sendiri (mirip PR) -> toolbar "+ New Record" (griduistandard.md §7 pola
// pur/pr/index.blade.php: Aksi cuma Buka, approval/submit divalidasi di entry).
new #[Title('Permohonan Tenaga Kerja — TELASIH MAKMUR ERP')] class extends Component
{
    use WithPagination;
    use HasGridPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function departments()
    {
        return OrgDepartment::where('activate', true)->orderBy('department_name')->pluck('department_name', 'department_id');
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => RcrRequisition::count(),
            'submitted' => RcrRequisition::where('status', 'SUBMITTED')->count(),
            'approved' => RcrRequisition::where('status', 'APPROVED')->count(),
            'rejected' => RcrRequisition::where('status', 'REJECTED')->count(),
        ];
    }

    #[Computed]
    public function rows()
    {
        return RcrRequisition::query()
            ->when($this->search, fn ($q) => $q->where(function ($q2) {
                $q2->where('requisition_no', 'like', "%{$this->search}%")
                    ->orWhere('position_title', 'like', "%{$this->search}%");
            }))
            ->orderByDesc('id_requisition')
            ->paginate($this->perPage);
    }

    public function createNew()
    {
        $track = RcrTrack::where('is_active', true)->orderBy('id_track')->first();
        $dept = OrgDepartment::where('activate', true)->orderBy('department_name')->first();
        $year = now()->format('y');
        $seq = RcrRequisition::whereYear('created_at', now()->year)->count() + 1;

        $requisition = RcrRequisition::create([
            'requisition_no' => "RCR{$year}".str_pad((string) $seq, 5, '0', STR_PAD_LEFT),
            'id_track' => $track?->id_track ?? 1,
            'department_id' => $dept?->department_id ?? 0,
            'position_title' => '',
            'headcount' => 1,
            'qualification' => '',
            'reason' => '',
            'requested_by' => Auth::id(),
            'status' => 'DRAFT',
        ]);

        return $this->redirect(route('hrm.recruitment.requisition.entry', $requisition->id_requisition));
    }

    public function avatarInitials(string $name): string
    {
        $words = array_values(array_filter(explode(' ', trim($name))));

        return $words ? mb_strtoupper(mb_substr($words[0], 0, 2)) : 'RC';
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
            <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">Total</p>
            <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->stats['total'] }}</p>
        </div>
        <div class="rounded-lg p-3.5 shadow-sm bg-[#fef6e7] border border-[#f5e0b3]">
            <p class="text-[10.5px] font-bold uppercase text-[#8a6d00]">Menunggu Approval</p>
            <p class="text-xl font-extrabold text-[#b45309] mt-0.5">{{ $this->stats['submitted'] }}</p>
        </div>
        <div class="rounded-lg p-3.5 shadow-sm bg-[#e9f9ef] border border-[#c8ecd6]">
            <p class="text-[10.5px] font-bold uppercase text-[#2f7a52]">Approved</p>
            <p class="text-xl font-extrabold text-[#16a34a] mt-0.5">{{ $this->stats['approved'] }}</p>
        </div>
        <div class="rounded-lg p-3.5 shadow-sm bg-[#fdecec] border border-[#f5c6c6]">
            <p class="text-[10.5px] font-bold uppercase text-[#a3453f]">Ditolak</p>
            <p class="text-xl font-extrabold text-[#c0392b] mt-0.5">{{ $this->stats['rejected'] }}</p>
        </div>
    </div>

    <div class="flex items-center gap-2 bg-white border border-[#d7dbe2] rounded-md px-2.5 py-2 overflow-x-auto mb-3">
        <div class="flex items-center gap-1.5 shrink-0">
            <span class="text-sm">🔍</span>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. permohonan atau posisi…"
                class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
        </div>
        <span class="flex-1"></span>
        <button type="button" wire:click="createNew"
            class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
    </div>

    <div class="bg-white border border-[#d7dbe2] rounded-md overflow-hidden">
        <table class="w-full text-xs">
            <thead class="text-[10px] text-muted uppercase border-b border-[#eef1f5] bg-[#f4f6fa]">
                <tr>
                    <th class="text-left px-3 py-2">Permohonan</th>
                    <th class="text-left px-3 py-2">Departemen</th>
                    <th class="text-left px-3 py-2">Track</th>
                    <th class="text-right px-3 py-2">Headcount</th>
                    <th class="text-left px-3 py-2">Status</th>
                    <th class="px-3 py-2 text-right w-[70px]">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#eef1f5]">
                @forelse ($this->rows as $r)
                    <tr wire:key="req-{{ $r->id_requisition }}"
                        ondblclick="window.location.href='{{ route('hrm.recruitment.requisition.entry', $r->id_requisition) }}'"
                        class="cursor-pointer hover:bg-[#f8fafc]" title="Klik 2x untuk buka detail">
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ $this->avatarGradient($r->id_requisition) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ $this->avatarInitials($r->position_title ?: 'RC') }}</div>
                                <div>
                                    <div class="font-semibold text-[13px]">{{ $r->position_title ?: '(belum diisi)' }}</div>
                                    <div class="text-[11px] text-muted font-mono">{{ $r->requisition_no }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-2">{{ $this->departments[$r->department_id] ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $r->track->name ?? '—' }}</td>
                        <td class="px-3 py-2 text-right font-mono">{{ $r->headcount }}</td>
                        <td class="px-3 py-2">
                            <span class="text-[10.5px] font-bold px-2 py-0.5 rounded-full {{ match ($r->status) {
                                'DRAFT' => 'bg-[#eef1f5] text-[#4b5568]',
                                'SUBMITTED' => 'bg-[#fff5e0] text-[#8a5a00]',
                                'APPROVED', 'CLOSED' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                                'REJECTED' => 'bg-[#fbeaea] text-[#a3241f]',
                                default => 'bg-[#eef1f5] text-[#4b5568]',
                            } }}">{{ $r->status }}</span>
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <a href="{{ route('hrm.recruitment.requisition.entry', $r->id_requisition) }}" wire:click.stop title="Buka"
                                class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">↗</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-center text-muted text-xs">Belum ada permohonan tenaga kerja.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('components.grid.pagination-footer', ['paginator' => $this->rows])
</div>
