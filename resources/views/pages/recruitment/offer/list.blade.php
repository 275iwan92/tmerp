<?php

use App\Livewire\Concerns\HasGridPagination;
use App\Models\Hrr\AppApplication;
use App\Models\Hrr\AppOffer;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// L7 (grid) -- docs/recruitment_tier4.md SECTION 12.
new #[Title('Offering — TELASIH MAKMUR ERP')] class extends Component
{
    use WithPagination;
    use HasGridPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    // Application status=OFFERING yang belum punya offer sama sekali (offer
    // pertama dibuat lewat entry screen, bukan dari sini).
    #[Computed]
    public function awaitingOffer()
    {
        return AppApplication::with('candidate')
            ->where('status', 'OFFERING')
            ->whereDoesntHave('offers')
            ->get();
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'awaiting' => $this->awaitingOffer->count(),
            'pending_approval' => AppOffer::where('status', 'PENDING_APPROVAL')->count(),
            'accepted' => AppOffer::where('status', 'ACCEPTED')->count(),
        ];
    }

    #[Computed]
    public function rows()
    {
        return AppOffer::query()
            ->with('application.candidate')
            ->when($this->search, fn ($q) => $q->whereHas('application.candidate', fn ($q2) => $q2->where('name', 'like', "%{$this->search}%")))
            ->orderByDesc('id_offer')
            ->paginate($this->perPage);
    }
};
?>

<div class="max-w-[1400px] mx-auto my-5 px-4">
    <div class="grid three mb-3.5">
        <div class="rounded-lg p-3.5 shadow-sm bg-[#fef6e7] border border-[#f5e0b3]">
            <p class="text-[10.5px] font-bold uppercase text-[#8a6d00]">{{ t('Awaiting Offer') }}</p>
            <p class="text-xl font-extrabold text-[#b45309] mt-0.5">{{ $this->stats['awaiting'] }}</p>
        </div>
        <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
            <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">{{ t('Awaiting Approval') }}</p>
            <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->stats['pending_approval'] }}</p>
        </div>
        <div class="rounded-lg p-3.5 shadow-sm bg-[#e9f9ef] border border-[#c8ecd6]">
            <p class="text-[10.5px] font-bold uppercase text-[#2f7a52]">{{ t('Accepted by Candidate') }}</p>
            <p class="text-xl font-extrabold text-[#16a34a] mt-0.5">{{ $this->stats['accepted'] }}</p>
        </div>
    </div>

    @if ($this->awaitingOffer->isNotEmpty())
        <div class="bg-white border border-[#d7dbe2] rounded-md p-3 mb-3.5">
            <p class="text-[10.5px] font-bold text-muted uppercase mb-2">{{ t('Waiting for an Offer to Be Made') }}</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($this->awaitingOffer as $app)
                    <a href="{{ route('hrm.recruitment.offer.entry', $app->id_application) }}"
                        class="text-[11px] px-2.5 py-1.5 rounded-md border border-[#d7dbe2] hover:bg-[#eef3fb] no-underline text-inherit">
                        {{ $app->candidate->name ?? '—' }}
                    </a>
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
                    <th class="text-right px-3 py-2">{{ t('Proposed Salary') }}</th>
                    <th class="text-left px-3 py-2">{{ t('Contract Type') }}</th>
                    <th class="text-left px-3 py-2">{{ t('Status') }}</th>
                    <th class="px-3 py-2 text-right w-[70px]">{{ t('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#eef1f5]">
                @forelse ($this->rows as $o)
                    <tr wire:key="offer-{{ $o->id_offer }}"
                        ondblclick="window.location.href='{{ route('hrm.recruitment.offer.entry', $o->id_application) }}'"
                        class="cursor-pointer hover:bg-[#f8fafc]" title="{{ t('Double-click to open detail') }}">
                        <td class="px-3 py-2 font-semibold">{{ $o->application->candidate->name ?? '—' }}</td>
                        <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $o->proposed_salary, 0, ',', '.') }}</td>
                        <td class="px-3 py-2">{{ $o->employment_type }}</td>
                        <td class="px-3 py-2">
                            <span class="text-[10.5px] font-bold px-2 py-0.5 rounded-full {{ match ($o->status) {
                                'ACCEPTED' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                                'DECLINED', 'EXPIRED' => 'bg-[#fbeaea] text-[#a3241f]',
                                'APPROVED', 'SENT' => 'bg-[#f1ecfc] text-[#6d28d9]',
                                default => 'bg-[#fff5e0] text-[#8a5a00]',
                            } }}">{{ $o->status }}</span>
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <a href="{{ route('hrm.recruitment.offer.entry', $o->id_application) }}" wire:click.stop title="{{ t('Open') }}"
                                class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">↗</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-center text-muted text-xs">{{ t('No offers created yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('components.grid.pagination-footer', ['paginator' => $this->rows])
</div>
