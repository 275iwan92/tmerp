<?php

use App\Models\Sys\SysNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Notification Center — TELASIH MAKMUR ERP')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public bool $unreadOnly = false;

    // grid_performance (GLOBAL_RULES.yaml): server-side pagination + WHERE.
    #[Computed]
    public function notifications()
    {
        return SysNotification::forUser(Auth::user()->login)
            ->when($this->search !== '', function ($q) {
                $term = $this->search;
                $q->where(function ($qq) use ($term) {
                    $qq->where('title', 'like', "%{$term}%")->orWhere('message', 'like', "%{$term}%");
                });
            })
            ->when($this->typeFilter !== '', fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->unreadOnly, fn ($q) => $q->where('is_read', false))
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    #[Computed]
    public function unreadCount(): int
    {
        return SysNotification::forUser(Auth::user()->login)->where('is_read', false)->count();
    }

    public function markRead(int $id)
    {
        $notif = SysNotification::forUser(Auth::user()->login)->findOrFail($id);
        $notif->update(['is_read' => true]);

        unset($this->notifications, $this->unreadCount);

        if ($notif->link) {
            return $this->redirect($notif->link);
        }
    }

    public function markAllRead(): void
    {
        SysNotification::forUser(Auth::user()->login)->where('is_read', false)->update(['is_read' => true]);
        unset($this->notifications, $this->unreadCount);
    }
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">🔔 Notification Center</b>
        @if ($this->unreadCount > 0)
            <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full bg-[#fff5e0] text-[#8a5a00]">{{ $this->unreadCount }} belum dibaca</span>
        @endif
        <a href="{{ route('menu') }}" title="Kembali ke Menu Utama"
            class="ml-auto inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full no-underline hover:brightness-110">
            <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-xs font-bold">←</span>
        </a>
    </header>

    <div class="max-w-[1000px] mx-auto my-5 px-4">
        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-4 py-2.5 bg-[#f4f6fa] border-b border-[#d7dbe2] flex-wrap">
                <div class="flex items-center gap-2 flex-wrap">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari judul / isi..." class="w-56 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    <select wire:model.live="typeFilter" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        <option value="">Semua Tipe</option>
                        <option value="info">Info</option>
                        <option value="success">Sukses</option>
                        <option value="warning">Peringatan</option>
                        <option value="error">Error</option>
                    </select>
                    <label class="flex items-center gap-1.5 text-xs text-[#4b5568] cursor-pointer">
                        <input type="checkbox" wire:model.live="unreadOnly" class="rounded border-[#d7dbe2]">
                        Hanya belum dibaca
                    </label>
                </div>
                <button type="button" wire:click="markAllRead" class="text-[11px] font-bold text-accent">Tandai semua terbaca</button>
            </div>

            <div class="divide-y divide-[#eef1f5]">
                @forelse ($this->notifications as $n)
                    <div wire:click="markRead({{ $n->id }})" wire:key="notif-{{ $n->id }}"
                        class="flex items-start gap-3 px-4 py-3 cursor-pointer hover:bg-[#fbfcfe] {{ ! $n->is_read ? 'bg-[#f7f9fe]' : '' }}">
                        <span class="mt-1.5 w-2.5 h-2.5 rounded-full shrink-0" style="background: {{ match ($n->type) {
                            'success' => '#22b07d', 'warning' => '#e5a324', 'error' => '#e5484d', default => '#2f6fed',
                        } }}"></span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm {{ ! $n->is_read ? 'font-bold text-navy' : 'font-medium text-[#4b5568]' }}">{{ $n->title }}</span>
                                @if (! $n->is_read)
                                    <span class="w-1.5 h-1.5 rounded-full bg-accent"></span>
                                @endif
                            </div>
                            @if ($n->message)
                                <p class="text-xs text-muted mt-0.5">{{ $n->message }}</p>
                            @endif
                            <p class="text-[10.5px] text-muted mt-1">{{ $n->created_at?->translatedFormat('d M Y, H:i') }} · {{ $n->created_at?->diffForHumans() }}</p>
                        </div>
                        @if ($n->link)
                            <span class="text-[10.5px] text-accent shrink-0">Buka →</span>
                        @endif
                    </div>
                @empty
                    <div class="text-center py-10 text-muted text-sm">Belum ada notifikasi.</div>
                @endforelse
            </div>

            <div class="px-4 py-3 border-t border-[#eef1f5]">
                {{ $this->notifications->links() }}
            </div>
        </div>
    </div>
</div>
