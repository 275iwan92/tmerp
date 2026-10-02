<?php

use App\Models\Sys\SysSupportReply;
use App\Models\Sys\SysSupportTicket;
use App\Models\Sys\SysSupportTicketAttachment;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

// Help Center -- SATU tempat gabungan (instruksi user 2026-08-30: "Combine
// Help and support yang ticketing and Help and support yang menjelaskan
// segala hal"), 3 sub-menu: Tiket Bantuan (submit + riwayat balasan
// developer/system analyst -- MENGGANTIKAN modal "Help & Support" lama di
// main-menu.blade.php, yang sekarang cuma link ke sini), Cara Pakai Aplikasi
// (UI/UX), Modul (detail per modul). Dibuka via F1 (partials/f1-help-hotkey)
// atau link profil dropdown, KEDUANYA target="_blank".
new #[Title('Help — TELASIH MAKMUR ERP')] class extends Component
{
    use WithFileUploads;

    #[Url(as: 'tab')]
    public string $tab = 'tickets'; // tickets | ui | modules

    // Konteks layar asal (diisi otomatis dari document.title tab yg memicu F1,
    // lihat partials/f1-help-hotkey.blade.php) -- boleh diedit manual juga.
    #[Url(as: 'ctx')]
    public string $support_context = '';

    public string $support_description = '';

    public array $support_attachments = [];

    public ?string $supportError = null;

    public ?string $supportSubmittedNo = null;

    public ?int $selectedTicketId = null;

    public string $newReply = '';

    #[Computed]
    public function myTickets()
    {
        return SysSupportTicket::with('replies')->where('created_by_user_id', Auth::id())->orderByDesc('id_ticket')->get();
    }

    #[Computed]
    public function selectedTicket(): ?SysSupportTicket
    {
        if (! $this->selectedTicketId) {
            return null;
        }

        return $this->myTickets->firstWhere('id_ticket', $this->selectedTicketId);
    }

    public function openTicket(int $id): void
    {
        $this->selectedTicketId = $id;
        $this->newReply = '';
    }

    public function submitSupportTicket(): void
    {
        $this->supportError = null;

        if (trim($this->support_description) === '') {
            $this->supportError = t('Description is required.');

            return;
        }

        $year = now()->format('y');
        $month = now()->format('m');
        $seq = SysSupportTicket::whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count() + 1;

        $ticket = SysSupportTicket::create([
            'ticket_no' => "SPT{$year}{$month}".str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'created_by_user_id' => Auth::id(),
            'screen_context' => $this->support_context ?: null,
            'description' => $this->support_description,
            'status' => 'OPEN',
        ]);

        foreach ($this->support_attachments as $file) {
            SysSupportTicketAttachment::create([
                'ticket_id' => $ticket->id_ticket,
                'file_path' => $file->store('support-tickets', 'public'),
                'uploaded_at' => now(),
            ]);
        }

        $this->supportSubmittedNo = $ticket->ticket_no;
        $this->support_description = '';
        $this->support_attachments = [];
        unset($this->myTickets);
    }

    public function resetSupportForm(): void
    {
        $this->supportSubmittedNo = null;
        $this->supportError = null;
    }

    // Balasan BALIK dari user (dua arah -- staff balas di setup/support-tickets.blade.php).
    public function addReply(): void
    {
        if (! $this->selectedTicket || trim($this->newReply) === '') {
            return;
        }

        SysSupportReply::create([
            'ticket_id' => $this->selectedTicket->id_ticket,
            'author' => Auth::user()->name ?? Auth::user()->login,
            'body' => $this->newReply,
        ]);

        $this->newReply = '';
        unset($this->myTickets);
    }
};
?>

<div class="min-h-screen bg-[#f4f6fa]" x-data="{ search: '' }">
    <header class="bg-navy text-white sticky top-0 z-30">
        <div class="max-w-[1200px] mx-auto px-5 h-16 flex items-center gap-4">
            <a href="{{ route('menu') }}" class="flex items-center gap-2.5 shrink-0">
                <span class="w-9 h-9 rounded-[10px] flex-shrink-0" style="background: conic-gradient(from 200deg, #ff5f6d, #ffc371, #2f6fed, #8a5cf6, #ff5f6d);"></span>
                <span class="leading-tight hidden sm:block">
                    <b class="block text-[14px] tracking-[.3px] text-white">TELASIH MAKMUR</b>
                    <span class="block text-[9px] tracking-[2px] text-white/60 uppercase">{{ t('Help') }}</span>
                </span>
            </a>
            @if ($tab !== 'tickets')
                <div class="flex-1 max-w-[420px] ml-auto">
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-white/50 text-sm">🔍</span>
                        <input type="text" x-model="search" placeholder="{{ t('Search help topics…') }}"
                            class="w-full pl-9 pr-3 py-2 rounded-md bg-white/10 border border-white/20 text-white placeholder-white/50 text-[13px] focus:bg-white/15 focus:outline-none">
                    </div>
                </div>
            @endif
        </div>
        {{-- Sub-menu (instruksi user: "ada sub-menu mulai dari ticket... lalu cara pakai UI/UX... lalu penjelasan modul") --}}
        <div class="max-w-[1200px] mx-auto px-5 flex gap-1 border-t border-white/10">
            <button type="button" wire:click="$set('tab', 'tickets')" class="px-4 py-2.5 text-[13px] font-semibold border-b-2 {{ $tab === 'tickets' ? 'border-white text-white' : 'border-transparent text-white/60 hover:text-white' }}">🎫 {{ t('Support Tickets') }}</button>
            <button type="button" wire:click="$set('tab', 'ui')" class="px-4 py-2.5 text-[13px] font-semibold border-b-2 {{ $tab === 'ui' ? 'border-white text-white' : 'border-transparent text-white/60 hover:text-white' }}">🧭 {{ t('How to Use the App') }}</button>
            <button type="button" wire:click="$set('tab', 'modules')" class="px-4 py-2.5 text-[13px] font-semibold border-b-2 {{ $tab === 'modules' ? 'border-white text-white' : 'border-transparent text-white/60 hover:text-white' }}">📚 {{ t('Modules') }}</button>
        </div>
    </header>

    <div class="max-w-[1200px] mx-auto px-5 py-8">

        {{-- ============ TAB 1: TIKET BANTUAN ============ --}}
        @if ($tab === 'tickets')
            <div class="grid gap-6" style="grid-template-columns: minmax(0,1fr) 320px;">
                <div class="space-y-4">
                    @if ($this->selectedTicket)
                        {{-- Detail 1 tiket + thread balasan --}}
                        <button type="button" wire:click="$set('selectedTicketId', null)" class="text-[12px] font-bold text-muted hover:text-navy">&larr; {{ t('Back to my ticket list') }}</button>
                        <div class="bg-white border border-[#e6e9ef] rounded-xl p-5">
                            <div class="flex items-center justify-between mb-1">
                                <h2 class="text-sm font-bold text-navy font-mono">{{ $this->selectedTicket->ticket_no }}</h2>
                                <span class="text-[10.5px] font-bold px-2 py-0.5 rounded-full {{ match ($this->selectedTicket->status) {
                                    'DONE' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                                    'CLOSED' => 'bg-[#eef1f5] text-[#4b5568]',
                                    'IN_PROGRESS' => 'bg-[#fff5e0] text-[#8a5a00]',
                                    default => 'bg-[#eaf2ff] text-[#1d4ed8]',
                                } }}">{{ str_replace('_', ' ', $this->selectedTicket->status) }}</span>
                            </div>
                            <p class="text-[11px] text-muted mb-3">{{ $this->selectedTicket->screen_context ?: '—' }} · {{ $this->selectedTicket->created_at->translatedFormat('d M Y, H:i') }}</p>
                            <p class="text-[13px] text-[#333a4d] mb-4">{{ $this->selectedTicket->description }}</p>

                            @if ($this->selectedTicket->replies->isNotEmpty())
                                <div class="space-y-2 mb-4">
                                    @foreach ($this->selectedTicket->replies as $r)
                                        @php $isMine = $r->author === (Auth::user()->name ?? Auth::user()->login); @endphp
                                        <div class="rounded-md px-3 py-2 text-[12.5px] {{ $isMine ? 'bg-[#eaf2ff] ml-8' : 'bg-[#f4f6fa] mr-8' }}">
                                            <div class="flex justify-between text-[10px] text-muted mb-0.5"><span class="font-bold">{{ $r->author }}{{ $isMine ? ' ('.t('You').')' : '' }}</span><span>{{ $r->created_at->diffForHumans(null, true) }}</span></div>
                                            <div>{{ $r->body }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-[12px] text-muted mb-4">{{ t('No reply from the support team yet.') }}</p>
                            @endif

                            @unless (in_array($this->selectedTicket->status, ['DONE', 'CLOSED'], true))
                                <div class="flex gap-2 pt-3 border-t border-[#eef1f5]">
                                    <textarea wire:model="newReply" rows="2" placeholder="{{ t('Reply / add info…') }}" class="flex-1 border border-[#d7dbe2] rounded-md px-2.5 py-1.5 text-[12.5px] resize-none"></textarea>
                                    <button type="button" wire:click="addReply" wire:loading.attr="disabled" class="self-end bg-navy text-white text-xs font-bold px-3.5 py-2 rounded-md shrink-0">{{ t('Send') }}</button>
                                </div>
                            @else
                                <p class="text-[11px] text-muted italic">{{ t('This ticket is already') }} {{ strtolower(str_replace('_', ' ', $this->selectedTicket->status)) }}{{ t(', it can no longer be replied to.') }}</p>
                            @endunless
                        </div>
                    @else
                        {{-- Form kirim tiket baru --}}
                        <div class="bg-white border border-[#e6e9ef] rounded-xl p-5">
                            <h2 class="text-sm font-bold text-navy mb-1">{{ t('Submit a New Ticket') }}</h2>
                            <p class="text-[12px] text-muted mb-3">{{ t('Report a bug, ask for help, or send a question to the developer/system analyst team.') }}</p>

                            @if ($supportSubmittedNo)
                                <div class="text-center py-6">
                                    <p class="text-3xl mb-1">✅</p>
                                    <p class="font-bold text-[#1f8a4c]">{{ t('Ticket sent') }}</p>
                                    <p class="text-[12px] text-muted mt-1">{{ t('Ticket number') }} <b>#{{ $supportSubmittedNo }}</b> {{ t('— our team will follow up shortly. Check its status in the "My Tickets" list on the right.') }}</p>
                                    <button type="button" wire:click="resetSupportForm" class="mt-3 text-[12px] font-bold text-navy underline">{{ t('Send another ticket') }}</button>
                                </div>
                            @else
                                @if ($supportError)
                                    <p class="text-[11px] text-[#a3241f] mb-2">⚠ {{ $supportError }}</p>
                                @endif
                                <x-form.input label="{{ t('Screen context (optional)') }}" wire:model="support_context" placeholder="{{ t('e.g. Customer Master, Customer Order #123') }}" class="mb-3" />
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ t('Description *') }}</label>
                                <textarea wire:model="support_description" rows="4" placeholder="{{ t('Tell us what happened — the more detail, the faster it gets handled.') }}" class="w-full border border-[#d7dbe2] rounded-md px-2.5 py-1.5 text-[13px] resize-y mb-3"></textarea>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ t('Screenshot attachment (optional)') }}</label>
                                <input type="file" wire:model="support_attachments" multiple accept="image/*" class="text-xs mb-2">
                                <div wire:loading wire:target="support_attachments" class="text-[11px] text-muted">{{ t('Uploading…') }}</div>
                                @if (count($support_attachments))
                                    <div class="flex gap-2 flex-wrap mb-3">
                                        @foreach ($support_attachments as $i => $file)
                                            <div class="relative w-14 h-14">
                                                <img src="{{ $file->temporaryUrl() }}" class="w-full h-full object-cover rounded-md">
                                                <button type="button" wire:click="$set('support_attachments.{{ $i }}', null)" title="{{ t('Remove') }}" class="absolute -top-1.5 -right-1.5 bg-white border border-[#d7dbe2] rounded-full w-[18px] h-[18px] text-[11px] leading-none">×</button>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                <button type="button" wire:click="submitSupportTicket" wire:loading.attr="disabled" class="bg-[#1f8a4c] text-white text-sm font-bold px-4 py-2.5 rounded-md">{{ t('Send Ticket') }}</button>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Daftar tiket saya (kanan) --}}
                <div class="bg-white border border-[#e6e9ef] rounded-xl p-4 h-fit">
                    <p class="text-[11px] font-bold text-muted uppercase mb-2">{{ t('My Tickets') }} ({{ $this->myTickets->count() }})</p>
                    <div class="space-y-1.5 max-h-[70vh] overflow-y-auto">
                        @forelse ($this->myTickets as $t)
                            <button type="button" wire:click="openTicket({{ $t->id_ticket }})"
                                class="w-full text-left px-2.5 py-2 rounded-md text-[12px] {{ $selectedTicketId === $t->id_ticket ? 'bg-[#eaf2ff]' : 'hover:bg-[#f7faff]' }}">
                                <div class="flex items-center justify-between gap-1.5">
                                    <span class="font-mono font-bold text-navy text-[11px]">{{ $t->ticket_no }}</span>
                                    <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded-full shrink-0 {{ match ($t->status) {
                                        'DONE' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                                        'CLOSED' => 'bg-[#eef1f5] text-[#4b5568]',
                                        'IN_PROGRESS' => 'bg-[#fff5e0] text-[#8a5a00]',
                                        default => 'bg-[#eaf2ff] text-[#1d4ed8]',
                                    } }}">{{ str_replace('_', ' ', $t->status) }}</span>
                                </div>
                                <p class="text-[#495166] mt-0.5">{{ Str::limit($t->description, 44) }}</p>
                                @if ($t->replies->isNotEmpty())
                                    <p class="text-[10px] text-accent font-semibold mt-0.5">💬 {{ $t->replies->count() }} {{ t('replies') }}</p>
                                @endif
                            </button>
                        @empty
                            <p class="text-[12px] text-muted px-1">{{ t('No tickets yet.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        {{-- ============ TAB 2: CARA PAKAI APLIKASI ============ --}}
        @if ($tab === 'ui')
            <div class="flex gap-8 items-start">
                <nav class="hidden lg:block w-[220px] shrink-0 sticky top-32 text-[13px]">
                    <a href="#ui-navigasi" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">{{ t('General Navigation') }}</a>
                    <a href="#ui-grid" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">{{ t('Table / Grid') }}</a>
                    <a href="#ui-grid-lanjutan" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">{{ t('Advanced Grid Features') }}</a>
                    <a href="#ui-form" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">{{ t('Entry Forms') }}</a>
                    <a href="#ui-kanban" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">{{ t('Kanban Board') }}</a>
                    <a href="#ui-gantt" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">{{ t('Gantt Board (Schedule)') }}</a>
                    <a href="#ui-cetak" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">{{ t('Printing Documents') }}</a>
                    <a href="#ui-lain" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">{{ t('Notifications, Chat & AI') }}</a>
                </nav>
                <div class="flex-1 min-w-0 space-y-6">
                    <div class="bg-[#eaf2ff] border border-[#c8ddfb] rounded-lg p-4 text-[13px] text-[#3b5b99]">
                        👋 {{ t('An explanation of the common views in the application — once you understand 1 grid/form, you understand them all because every screen uses the same pattern.') }}
                    </div>

                    <section id="ui-navigasi" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-32">
                        <h2 class="text-[15px] font-bold text-navy mb-3">🧭 {{ t('General Navigation') }}</h2>
                        <ul class="space-y-2 text-[13px] text-[#333a4d] list-disc list-inside">
                            <li><b>{{ t('Module bar at the top') }}</b> {{ t('(Sales, Procurement, Inventory, etc.) — click to see the list of screens in that module. Modules that have no content yet look dimmed/cannot be clicked.') }}</li>
                            <li><b>{{ t('Left sidebar') }}</b> {{ t('changes its content to match the selected module, grouped per group.') }} <b>{{ t('Click a group name (e.g. "Transactions") to collapse/expand') }}</b> {{ t('— this is hidden unless you try it; click the group name itself, not just its small arrow.') }}</li>
                            <li><b>{{ t('Tabs above the work area') }}</b> {{ t('— every screen you open becomes a new tab, so you can open many screens at once without losing the others. Click ✕ on a tab to close it.') }}</li>
                            <li><b>{{ t('Profile icon') }}</b> {{ t('(top right) — Edit profile, Change password, Employee Portal, My Security, and') }} <b>{{ t('Help (this page)') }}</b>.</li>
                            <li><b>{{ t('🔍 quick search icon') }}</b> {{ t('in the topbar (or press') }} <kbd>Ctrl</kbd>+<kbd>K</kbd>) {{ t('— search a screen name directly without clicking through the menus one by one.') }}</li>
                            <li><b>{{ t('Press') }} <kbd>F1</kbd> {{ t('at any time') }}</b> {{ t('— opens this Help page directly in a new tab, on whatever screen you are on, and automatically carries that screen\'s context into the ticket form.') }}</li>
                        </ul>
                    </section>

                    <section id="ui-grid" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-32">
                        <h2 class="text-[15px] font-bold text-navy mb-3">📊 {{ t('Table / Grid (Data List)') }}</h2>
                        <p class="text-[13px] text-[#333a4d] mb-3">{{ t('Almost every list screen (Customer, Item, Purchase Order, etc.) uses the SAME view — once you understand 1 grid, you understand them all.') }}</p>
                        <ul class="space-y-2 text-[13px] text-[#333a4d] list-disc list-inside">
                            <li><b>{{ t('Colourful number boxes at the top') }}</b> {{ t('— a quick summary (Total/Active/Inactive/etc.), not buttons, just information.') }}</li>
                            <li><b>{{ t('Search box') }}</b> {{ t('— just type, the results update automatically (no need to press Enter or click a search button).') }}</li>
                            <li><b>{{ t('"+ New Record" button') }}</b> {{ t('(top right corner of the table) — to add new data.') }}</li>
                            <li><b>{{ t('Double-click a row') }}</b> {{ t('of data — this is the') }} <b>{{ t('only way to open the detail/edit') }}</b> {{ t('from the row itself (a single click does nothing on many screens — this is what new users most often miss).') }}</li>
                            <li><b>{{ t('Small icons in the rightmost column (or leftmost, depending on the screen)') }}</b> {{ t('— hover over an icon to see its description (✎ = Edit, ⏻ = Activate/Deactivate, and other icons depending on the screen).') }} {{ t('For transaction document screens (PO, CO, etc.), the icons are ↗ Open and sometimes ✓ Confirm.') }}</li>
                            <li><b>{{ t('Page numbers below the table') }}</b> {{ t('— large data is split per page so it is not heavy, instead of showing everything at once.') }}</li>
                        </ul>
                    </section>

                    <section id="ui-grid-lanjutan" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-32">
                        <h2 class="text-[15px] font-bold text-navy mb-3">🛠️ {{ t('Advanced Grid Features (on certain screens)') }}</h2>
                        <ul class="space-y-2 text-[13px] text-[#333a4d] list-disc list-inside">
                            <li><b>{{ t('▸ arrow on the left of a row') }}</b> {{ t('(e.g. in Customer Order) — click to peek at the document contents (items/lines) in place, without leaving the page.') }}</li>
                            <li><b>{{ t('Checkbox on a row') }}</b> {{ t('(only for data that is still draft) — tick several rows at once for bulk delete.') }} <b>{{ t('Hold Shift and click another row') }}</b> {{ t('to select ALL rows in between at once — this shortcut is easy to miss because it is not visible on screen.') }}</li>
                            <li><b>{{ t('☰ Columns / ↕ Sort / ⬇ Export buttons') }}</b> {{ t('(on some grids with many columns) — choose which columns to show, sort the data, or download to Excel/CSV/PDF.') }}</li>
                            <li><b>{{ t('"☰ List" / "▦ Card" toggle') }}</b> {{ t('(Master Employee only) — switch the list view to photo cards.') }}</li>
                        </ul>
                    </section>

                    <section id="ui-form" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-32">
                        <h2 class="text-[15px] font-bold text-navy mb-3">📝 {{ t('Entry Forms') }}</h2>
                        <ul class="space-y-2 text-[13px] text-[#333a4d] list-disc list-inside">
                            <li><b>{{ t('Asterisk (*)') }}</b> {{ t('on a field label — required, cannot be saved if empty.') }}</li>
                            <li><b>{{ t('Entry errors') }}</b> {{ t('appear in red right below the problematic field after the Save button is clicked.') }}</li>
                            <li><b>{{ t('Grey fields / cannot be typed in') }}</b> {{ t('— usually locked data (e.g. from the login account, or a document that is already confirmed) so it does not change by accident.') }}</li>
                            <li><b>{{ t('Cancel button') }}</b> {{ t('— back to the list without saving anything.') }}</li>
                        </ul>
                    </section>

                    <section id="ui-kanban" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-32">
                        <h2 class="text-[15px] font-bold text-navy mb-3">🗂️ {{ t('Kanban Board') }}</h2>
                        <p class="text-[13px] text-[#333a4d]">{{ t('Used on screens such as Pipeline Board (Recruitment), Production Monitoring, and Shift Roster — a card represents 1 document/job/employee, a column represents a status/stage/machine.') }}</p>
                        <ul class="space-y-2 text-[13px] text-[#333a4d] list-disc list-inside mt-2">
                            <li><b>{{ t('Drag a card to another column') }}</b> {{ t('to move its status — click and hold the card, drag it to the target column, release. Hover over a card to see this description too.') }}</li>
                            <li>{{ t('If the move is not allowed (e.g. skipping a stage), the system rejects it automatically — try again with the action button on the card as an alternative if dragging does not work.') }}</li>
                        </ul>
                    </section>

                    <section id="ui-gantt" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-32">
                        <h2 class="text-[15px] font-bold text-navy mb-3">📅 {{ t('Gantt Board (Production Schedule)') }}</h2>
                        <ul class="space-y-2 text-[13px] text-[#333a4d] list-disc list-inside">
                            <li><b>{{ t('Drag the body of a schedule bar') }}</b> {{ t('— move left/right to change the time, up/down to change the machine/resource.') }}</li>
                            <li><b>{{ t('Drag the right edge of a bar') }}</b> {{ t('— change the duration/working time (enlarge/shrink the bar).') }}</li>
                            <li>{{ t('A highlight appears automatically when schedules clash across machines.') }}</li>
                        </ul>
                    </section>

                    <section id="ui-cetak" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-32">
                        <h2 class="text-[15px] font-bold text-navy mb-3">🖨️ {{ t('Printing Documents (Nota, Quotation, etc.)') }}</h2>
                        <p class="text-[13px] text-[#333a4d]">{{ t('Documents such as the Inquiry Nota, Quotation, and other official documents have a "Print" button/printer icon on their detail screen') }} {{ t('— click it to open the print view, then use the browser\'s built-in print/save-as-PDF command (Ctrl+P).') }}</p>
                    </section>

                    <section id="ui-lain" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-32">
                        <h2 class="text-[15px] font-bold text-navy mb-3">💬 {{ t('Notifications, Chat & AI Assistant') }}</h2>
                        <ul class="space-y-2 text-[13px] text-[#333a4d] list-disc list-inside">
                            <li><b>🔔 {{ t('Notifications') }}</b> {{ t('— information from the system (e.g. a document needs your approval).') }}</li>
                            <li><b>💬 {{ t('Chat') }}</b> {{ t('— internal messages between application users.') }}</li>
                            <li><b>✨ {{ t('AI Assistant') }}</b> {{ t('— quick AI-based help inside the application.') }}</li>
                            <li>{{ t('All are in the profile/bell icon at the top right of the topbar.') }}</li>
                        </ul>
                    </section>
                </div>
            </div>
        @endif

        {{-- ============ TAB 3: MODUL ============ --}}
        @if ($tab === 'modules')
            @php
                $modules = [
                    [
                        'id' => 'mod-sales', 'icon' => '💼', 'name' => t('Sales'),
                        'desc' => t('The complete sales flow from a product specification request until the goods are shipped to the customer.'),
                        'steps' => [
                            t('Inquiry — record the product specification requested by the customer (size, material, printing process).').' '.t('Type NEW (new)/RECALC (recalculate price, same spec)/REVISE (spec changed, becomes a new row, the old history remains).'),
                            t('Imposition & Routing — the Product Designer sets the plano layout (how many sheets fit in 1 plano) and the order of production processes.'),
                            t('Product Costing — calculate COGS & margin based on the Inquiry+Imposition results;').' '.t('prices & margins here are CONFIDENTIAL (Costing team & Directors only), visible to Sales only after being "published".'),
                            t('Quotation — create an official price offer to the customer from the published Costing results.'),
                            t('Customer Order (CO) — once the customer agrees, the Quotation is confirmed into a CO (firm, binding order).'),
                            t('Plan Delivery — schedule when & how many goods are shipped (can be split per instalment).'),
                            t('Delivery Order (DO) / Delivery Note — the physical document for shipping goods to the customer.'),
                        ],
                        'note' => t('These stages are sequential and LOCK EACH OTHER — a later stage (e.g. Quotation) cannot change the data of an earlier stage (Inquiry).').' '.t('If the specification changes after the Inquiry is confirmed, it must go through a REVISION (create a new row), not a direct edit.'),
                    ],
                    [
                        'id' => 'mod-proc', 'icon' => '🛒', 'name' => t('Procurement'),
                        'desc' => t('The process of purchasing goods/raw materials from suppliers, triggered by production needs or manual requests.'),
                        'steps' => [
                            t('Purchase Requisition (PR) — a purchase request from a user/department, going through an approval flow before continuing.'),
                            t('Purchase Order (PO) — the official PO to the supplier, created from an approved PR, also through approval.'),
                            t('Purchase Voucher (PV) — proof of payment to the supplier, automatically creates an accounting journal.'),
                            t('Purchase Receive (PRT) — physical receipt of goods from the supplier, 3 different paths depending on the source:').' '.t('a regular PO, customer-consigned goods, or subcontract output (becoming semi-finished goods/SFG).'),
                        ],
                        'note' => t('PR and PO each have their own approval levels (cannot be skipped) before they may continue to the next stage.'),
                    ],
                    [
                        'id' => 'mod-inv', 'icon' => '📦', 'name' => t('Inventory'),
                        'desc' => t('Real-time records of stock — raw materials, finished goods, and semi-finished goods.'),
                        'steps' => [
                            t('Stock Master & Stock Card — see the stock balance per item, can be filtered per type (Raw Material/Finished Goods/Semi-Finished).'),
                            t('Stock In — goods coming in (from Purchase Receive, production returns, etc.).'),
                            t('Stock Out — goods going out (used by production, shipped to customers, etc.).'),
                            t('Stock Return — returning goods (to the supplier or from production).'),
                            t('Stock Adjustment — manual correction of the difference between physical stock and system records, needs approval.'),
                        ],
                        'note' => t('Every stock movement is recorded permanently in the Stock Card per item — there is no way to "delete" history, corrections always go through a new Adjustment transaction.'),
                    ],
                    [
                        'id' => 'mod-prod', 'icon' => '🏭', 'name' => t('Production'),
                        'desc' => t('Planning, scheduling, and monitoring of the production process on the factory floor.'),
                        'steps' => [
                            t('Work Order (WO) — created automatically from the Sales Plan Delivery, 1 WO = 1 product part.'),
                            t('Scheduling — WOs are scheduled to machines via the Gantt Board (drag to shift time) or the Kanban Board (drag to move status/machine).'),
                            t('Process execution — standard order: Plano Cutting → Printing → Coating → Lamination → Die-cut → Gluing → Stripping → Packing → Shipping (Coating ALWAYS before Lamination).'),
                            t('Production Output — record the result of each stage (good qty, failed/scrap qty with its cause category).'),
                        ],
                        'note' => t('1 order with several product parts will have several related WOs — monitor them all through the Monitoring Dashboard, not 1 by 1.'),
                    ],
                    [
                        'id' => 'mod-hrm', 'icon' => '👥', 'name' => t('Human Resources'),
                        'desc' => t('Employee data, organisation structure, work schedules, up to the recruitment of new staff.'),
                        'steps' => [
                            t('Master Employee & Organisation Structure — complete employee data and each one\'s position in the company hierarchy.'),
                            t('Shift Roster — schedule employees to work shifts per machine/day (drag a name from the "pool" to a schedule slot).'),
                            t('Leave/Sick/Permit — request & approval, automatically records Absent if Permit is more than 4x a month.'),
                            t('Recruitment — from a request for new staff, job posting, candidate selection (Pipeline Board drag-drop between stages), until the candidate is hired as an employee.'),
                        ],
                        'note' => t('There is a SEPARATE Employee Portal (profile icon → Employee Portal) for employees to see their own data — different from this HR screen, which is for HRD to manage all employees.'),
                    ],
                    [
                        'id' => 'mod-mdata', 'icon' => '🗄️', 'name' => t('Master Data'),
                        'desc' => t('Master data shared by ALL other modules — the single source of truth.'),
                        'steps' => [
                            t('Customer & Supplier — business partner data (address, contact, payment terms).'),
                            t('Products & Raw Material Items — specifications of finished products and raw materials.'),
                            t('Unit (UoM), Currency, Region — general reference data across modules.'),
                        ],
                        'note' => t('If the data here is wrong/incomplete, the impact spreads to ALL modules that use it').' '.t('— e.g. a wrong Customer address here will also be wrong in the Customer Order & Delivery Order.'),
                    ],
                    [
                        'id' => 'mod-finacc', 'icon' => '💰', 'name' => t('Finance & Accounting'),
                        'desc' => t('Financial and accounting records of the company, from daily cash to reports.'),
                        'steps' => [
                            t('Chart of Account (COA) — the company\'s standard list of accounting accounts.'),
                            t('Cash/Bank Journal (BKM/BKK/BBM/BBK) — recording money in/out, Draft → Post flow.'),
                            t('DP Invoice — down payment invoice to the customer.'),
                        ],
                        'note' => t('Most journals here are created AUTOMATICALLY from transactions in other modules (Purchase Voucher, raw material Stock Out)').' '.t('— do not enter them manually twice for transactions that are already recorded automatically.'),
                    ],
                    [
                        'id' => 'mod-setup', 'icon' => '🔒', 'name' => t('Setup & Security'),
                        'desc' => t('Account settings, access rights, and system administration — admin accounts only.'),
                        'steps' => [
                            t('Users — create & manage login accounts (username/name/group), reset passwords, activate/deactivate accounts.'),
                            t('Groups — a set of users sharing the same access rights.'),
                            t('Group Access Rights — set permissions per module (Access/Input/Approve/Export) for each group.'),
                            t('Legacy Data Migration — pull data from the old system (ScriptCase) into the new system.'),
                        ],
                        'note' => t('This menu only appears for admin accounts — regular users will not see the Security group in the sidebar at all.'),
                    ],
                ];
            @endphp

            <div class="space-y-4">
                @foreach ($modules as $m)
                    <section id="{{ $m['id'] }}" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5">
                        <h2 class="text-[16px] font-bold text-navy mb-1">{{ $m['icon'] }} {{ $m['name'] }}</h2>
                        <p class="text-[13px] text-[#333a4d] mb-3">{{ $m['desc'] }}</p>
                        <p class="text-[11px] font-bold text-muted uppercase mb-2">{{ t('Flow / Stages') }}</p>
                        <ol class="space-y-1.5 text-[12.5px] text-[#333a4d] list-decimal list-inside mb-3">
                            @foreach ($m['steps'] as $step)
                                <li>{{ $step }}</li>
                            @endforeach
                        </ol>
                        <p class="text-[11.5px] text-[#8a6d00] bg-[#fef6e7] border border-[#f5e0b3] rounded-md px-3 py-2">💡 {{ $m['note'] }}</p>
                    </section>
                @endforeach
            </div>
        @endif

        <div class="text-center text-[11.5px] text-muted py-6">
            {{ t('Can’t find what you’re looking for?') }} <button type="button" wire:click="$set('tab', 'tickets')" class="text-accent font-bold underline">{{ t('Send a ticket to the support team →') }}</button>
        </div>
    </div>
</div>
