<?php

use App\Models\Sys\SysSupportTicket;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// Admin grid utk "Help & Support" tickets (dropdown profil main-menu.blade.php)
// -- instruksi user 2026-08-29. Labelnya Bahasa Inggris (kebijakan baru utk
// hal yang baru dibangun sesi ini, lihat CLAUDE.md soal cakupan -- BUKAN
// rename retroaktif layar lain).
new #[Title('Support Tickets — TELASIH MAKMUR ERP')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public ?int $selectedId = null;

    public string $resolution_note = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'open' => SysSupportTicket::where('status', 'OPEN')->count(),
            'in_progress' => SysSupportTicket::where('status', 'IN_PROGRESS')->count(),
            'done' => SysSupportTicket::whereIn('status', ['DONE', 'CLOSED'])->count(),
        ];
    }

    #[Computed]
    public function rows()
    {
        return SysSupportTicket::query()
            ->with('creator')
            ->when($this->search, fn ($q) => $q->where(function ($q2) {
                $q2->where('ticket_no', 'like', "%{$this->search}%")->orWhere('description', 'like', "%{$this->search}%");
            }))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id_ticket')
            ->paginate(20);
    }

    #[Computed]
    public function selected(): ?SysSupportTicket
    {
        return $this->selectedId ? SysSupportTicket::with(['creator', 'attachments'])->find($this->selectedId) : null;
    }

    public function open(int $id): void
    {
        $this->selectedId = $id;
        $this->resolution_note = SysSupportTicket::find($id)?->resolution_note ?? '';
    }

    public function setStatus(string $status): void
    {
        if (! $this->selected) {
            return;
        }

        $this->selected->update([
            'status' => $status,
            'resolution_note' => $this->resolution_note ?: $this->selected->resolution_note,
            'resolved_by' => in_array($status, ['DONE', 'CLOSED'], true) ? (Auth::user()->login ?? 'system') : $this->selected->resolved_by,
            'resolved_at' => in_array($status, ['DONE', 'CLOSED'], true) ? now() : $this->selected->resolved_at,
        ]);

        unset($this->rows, $this->stats, $this->selected);
    }
};
?>

<div class="max-w-[1300px] mx-auto my-5 px-4">
    <div class="grid three mb-3.5">
        <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
            <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">Open</p>
            <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->stats['open'] }}</p>
        </div>
        <div class="rounded-lg p-3.5 shadow-sm bg-[#fef6e7] border border-[#f5e0b3]">
            <p class="text-[10.5px] font-bold uppercase text-[#8a6d00]">In progress</p>
            <p class="text-xl font-extrabold text-[#b45309] mt-0.5">{{ $this->stats['in_progress'] }}</p>
        </div>
        <div class="rounded-lg p-3.5 shadow-sm bg-[#e9f9ef] border border-[#c8ecd6]">
            <p class="text-[10.5px] font-bold uppercase text-[#2f7a52]">Done / closed</p>
            <p class="text-xl font-extrabold text-[#16a34a] mt-0.5">{{ $this->stats['done'] }}</p>
        </div>
    </div>

    <div class="flex items-center gap-2 bg-white border border-[#d7dbe2] rounded-md px-2.5 py-2 overflow-x-auto mb-3">
        <div class="flex items-center gap-1.5 shrink-0">
            <span class="text-sm">🔍</span>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search ticket no. or description…" class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
        </div>
        <select wire:model.live="statusFilter" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
            <option value="">All statuses</option>
            <option value="OPEN">Open</option>
            <option value="IN_PROGRESS">In progress</option>
            <option value="DONE">Done</option>
            <option value="CLOSED">Closed</option>
        </select>
    </div>

    <div class="bg-white border border-[#d7dbe2] rounded-md overflow-hidden">
        <table class="w-full text-xs">
            <thead class="text-[10px] text-muted uppercase border-b border-[#eef1f5] bg-[#f4f6fa]">
                <tr>
                    <th class="text-left px-3 py-2">Ticket</th>
                    <th class="text-left px-3 py-2">Reported by</th>
                    <th class="text-left px-3 py-2">Screen</th>
                    <th class="text-left px-3 py-2">Description</th>
                    <th class="text-left px-3 py-2">Status</th>
                    <th class="px-3 py-2 text-right w-[70px]">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#eef1f5]">
                @forelse ($this->rows as $t)
                    <tr wire:key="ticket-{{ $t->id_ticket }}" wire:dblclick="open({{ $t->id_ticket }})" class="cursor-pointer hover:bg-[#f8fafc]" title="Double-click to open">
                        <td class="px-3 py-2 font-mono font-semibold">{{ $t->ticket_no }}</td>
                        <td class="px-3 py-2">{{ $t->creator->name ?? '—' }}</td>
                        <td class="px-3 py-2 text-muted">{{ Str::limit($t->screen_context, 30) }}</td>
                        <td class="px-3 py-2">{{ Str::limit($t->description, 50) }}</td>
                        <td class="px-3 py-2">
                            <span class="text-[10.5px] font-bold px-2 py-0.5 rounded-full {{ match ($t->status) {
                                'DONE' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                                'CLOSED' => 'bg-[#eef1f5] text-[#4b5568]',
                                'IN_PROGRESS' => 'bg-[#fff5e0] text-[#8a5a00]',
                                default => 'bg-[#eaf2ff] text-[#1d4ed8]',
                            } }}">{{ str_replace('_', ' ', $t->status) }}</span>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <button type="button" wire:click.stop="open({{ $t->id_ticket }})" title="Open" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">↗</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-center text-muted text-xs">No tickets yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $this->rows->links() }}</div>

    @if ($this->selected)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-[60] p-4" wire:click.self="$set('selectedId', null)">
            <div class="bg-white rounded-xl w-full max-w-[480px] p-5">
                <div class="flex items-center justify-between mb-1">
                    <h3 class="text-sm font-bold text-navy font-mono">{{ $this->selected->ticket_no }}</h3>
                    <span class="text-[10.5px] font-bold px-2 py-0.5 rounded-full {{ match ($this->selected->status) {
                        'DONE' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                        'CLOSED' => 'bg-[#eef1f5] text-[#4b5568]',
                        'IN_PROGRESS' => 'bg-[#fff5e0] text-[#8a5a00]',
                        default => 'bg-[#eaf2ff] text-[#1d4ed8]',
                    } }}">{{ str_replace('_', ' ', $this->selected->status) }}</span>
                </div>
                <p class="text-[11px] text-muted mb-3">{{ $this->selected->creator->name ?? '—' }} · {{ $this->selected->screen_context }} · {{ $this->selected->created_at->translatedFormat('d M Y, H:i') }}</p>
                <p class="text-[12.5px] mb-3">{{ $this->selected->description }}</p>

                @if ($this->selected->attachments->isNotEmpty())
                    <div class="flex flex-wrap gap-2 mb-3">
                        @foreach ($this->selected->attachments as $a)
                            <a href="{{ asset('storage/'.$a->file_path) }}" target="_blank" rel="noopener">
                                <img src="{{ asset('storage/'.$a->file_path) }}" class="w-16 h-16 object-cover rounded-md border border-[#d7dbe2]">
                            </a>
                        @endforeach
                    </div>
                @endif

                <x-form.textarea wire:model="resolution_note" rows="2" placeholder="What was fixed / why closed…"><x-slot:label>Resolution note</x-slot:label></x-form.textarea>

                <div class="flex flex-wrap justify-end gap-2 mt-4">
                    <button type="button" wire:click="$set('selectedId', null)" class="border border-[#d7dbe2] text-xs font-bold px-3 py-2 rounded-md">Close panel</button>
                    <button type="button" wire:click="setStatus('IN_PROGRESS')" class="bg-[#fff5e0] text-[#8a5a00] text-xs font-bold px-3 py-2 rounded-md">In progress</button>
                    <button type="button" wire:click="setStatus('CLOSED')" class="bg-[#eef1f5] text-[#4b5568] text-xs font-bold px-3 py-2 rounded-md">Not a bug (close)</button>
                    <button type="button" wire:click="setStatus('DONE')" class="bg-[#1f8a4c] text-white text-xs font-bold px-3 py-2 rounded-md">Mark done</button>
                </div>
            </div>
        </div>
    @endif
</div>
