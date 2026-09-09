{{--
    docs/kanbanboardstandard.md -- komponen reusable "x-kanban.board" (dibangun
    2026-09-09 atas instruksi eksplisit user, meng-override §1 dokumen itu yang
    tadinya minta tunggu kanban ke-3 dgn pola serupa). Replika docs/mockups/
    mockup_UIUX_kanban.html (Trello-style): kolom judul-editable, composer
    kartu, tag/prioritas/due-date/assignee, drag-drop antar+dalam kolom,
    modal detail (Labels/Dates/Checklist/Members popover, deskripsi
    inline-edit, checklist+progress, komentar). Tema warna ikut terang
    halaman pemanggil (mockup asli dark) -- bentuk/perilaku yang ditiru.

    Kontrak (sama pola components/gantt/board.blade.php): HALAMAN resolve
    VM `columns` (tiap kolom sudah bawa `cards` miliknya sendiri, terurut),
    komponen ini cuma urus mekanika UI & panggil method Livewire lewat NAMA
    yang dioper prop -- komponen tak pernah tahu domain bisnis di baliknya.

    x-data SENGAJA inline penuh di sini (BUKAN Alpine.data() didaftar lewat
    <script>+alpine:init spt gantt/board.blade.php) -- pola itu terbukti
    gagal teregistrasi di halaman Library Component (root cause belum
    ketemu, lihat catatan di component-library.blade.php tab Gantt). x-data
    inline tak butuh registrasi global sama sekali, jadi otomatis lolos dari
    seluruh kelas bug tsb.
--}}
@props([
    'columns',
    'activeCardId' => null,
    'moveMethod' => 'kanbanMoveCard',
    'addCardMethod' => 'kanbanAddCard',
    'addColumnMethod' => 'kanbanAddColumn',
    'renameColumnMethod' => 'kanbanRenameColumn',
    'updateCardMethod' => 'kanbanUpdateCard',
    'addTagMethod' => 'kanbanAddTag',
    'removeTagMethod' => 'kanbanRemoveTag',
    'addChecklistMethod' => 'kanbanAddChecklist',
    'deleteChecklistMethod' => 'kanbanDeleteChecklist',
    'addChecklistItemMethod' => 'kanbanAddChecklistItem',
    'toggleChecklistItemMethod' => 'kanbanToggleChecklistItem',
    'addCommentMethod' => 'kanbanAddComment',
    'openCardMethod' => 'kanbanOpenCard',
    'closeCardMethod' => 'kanbanCloseCard',
    'currentUserName' => 'Johannes Iwan',
])
@php
    // Kartu aktif (modal) di-resolve SEKALI di sini server-side, bukan via
    // <template x-if> per-kartu -- lihat catatan panjang di $activeCardId
    // (component-library.blade.php) soal kenapa: Alpine memperlakukan
    // cabang x-if yg sudah true sbg "miliknya", isinya BEKU tak ikut ter-
    // update morph Livewire selama modal masih terbuka (bug nyata, checklist
    // %/komentar/tag jadi basi). Satu blok modal, activeCard sudah pasti,
    // Livewire morph biasa (bukan lewat Alpine x-if) yg jaga kontennya fresh.
    $activeCard = null;
    $activeColLabel = null;
    if ($activeCardId !== null) {
        foreach ($columns as $col) {
            foreach ($col['cards'] as $c) {
                if ($c['id'] === $activeCardId) {
                    $activeCard = $c;
                    $activeColLabel = $col['label'];
                    break 2;
                }
            }
        }
    }
@endphp
@php
    $tagTones = [
        'blue' => 'bg-[#eaf2ff] text-[#1d4ed8]',
        'orange' => 'bg-[#fef6e7] text-[#b45309]',
        'green' => 'bg-[#e9f9ef] text-[#16a34a]',
        'purple' => 'bg-[#f1ecfc] text-[#6d28d9]',
    ];
    $prioTones = [
        'high' => 'bg-[#fdecec] text-[#c0392b]',
        'medium' => 'bg-[#fef6e7] text-[#b45309]',
        'low' => 'bg-[#eef1f5] text-[#4b5568]',
    ];
    $prioLabels = ['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'];
@endphp
<div
    x-data="{
        draggedId: null,
        overColumn: null,
        overIndex: null,
        composingColumn: null,
        composerTitle: '',
        composerDue: '',
        addingColumn: false,
        newColumnTitle: '',
    }"
    x-on:keydown.escape.window="if ({{ $activeCardId !== null ? 'true' : 'false' }}) { $wire.{{ $closeCardMethod }}(); }"
>
    <div class="flex gap-3 overflow-x-auto pb-2 items-start">
        @foreach ($columns as $col)
            @php $cardCount = count($col['cards']); @endphp
            <div class="w-[260px] shrink-0 bg-[#f4f6fa] border border-[#e6e9ef] rounded-xl flex flex-col max-h-[640px]">
                <div class="flex items-center justify-between px-3 pt-3 pb-2">
                    <span contenteditable="true"
                        x-on:blur="$wire.{{ $renameColumnMethod }}('{{ $col['key'] }}', $event.target.innerText.trim())"
                        class="text-[11.5px] font-bold text-navy uppercase tracking-wide outline-none focus:bg-white focus:px-1 focus:py-0.5 focus:rounded flex-1">{{ $col['label'] }}</span>
                    <span class="text-[10px] font-bold text-muted bg-white px-1.5 py-0.5 rounded-full ml-2">{{ $cardCount }}</span>
                </div>

                <div class="flex-1 overflow-y-auto tm-scroll px-2 flex flex-col gap-1 min-h-[40px]"
                    x-on:dragover.prevent="if (! $event.target.closest('[data-kcard]')) { overColumn = '{{ $col['key'] }}'; overIndex = {{ $cardCount }}; }"
                    x-on:drop.prevent="$wire.{{ $moveMethod }}(draggedId, '{{ $col['key'] }}', overIndex); draggedId = null; overColumn = null; overIndex = null">

                    @foreach ($col['cards'] as $idx => $card)
                        <div class="h-1.5 rounded-full transition-colors" x-bind:class="(overColumn === '{{ $col['key'] }}' && overIndex === {{ $idx }}) ? 'bg-accent' : 'bg-transparent'"></div>

                        @php
                            $isOverdue = ! empty($card['dueDate']) && \Illuminate\Support\Carbon::parse($card['dueDate'])->isPast();
                            $dueLabel = ! empty($card['dueDate']) ? \Illuminate\Support\Carbon::parse($card['dueDate'])->format('d M, H:i') : null;
                            $doneCount = collect($card['checklist'])->where('done', true)->count();
                            $totalCount = count($card['checklist']);
                        @endphp
                        <div wire:key="kcard-{{ $card['id'] }}" data-kcard draggable="true"
                            x-on:dragstart="draggedId = {{ $card['id'] }}"
                            x-on:dragend="draggedId = null; overColumn = null; overIndex = null"
                            x-on:dragover.prevent.stop="overColumn = '{{ $col['key'] }}'; overIndex = (($event.clientY - $event.currentTarget.getBoundingClientRect().top) < ($event.currentTarget.getBoundingClientRect().height / 2)) ? {{ $idx }} : {{ $idx + 1 }}"
                            wire:dblclick="{{ $openCardMethod }}({{ $card['id'] }})"
                            x-bind:class="draggedId === {{ $card['id'] }} ? 'opacity-40 scale-95' : ''"
                            class="bg-white border border-[#dbe1ea] rounded-lg p-2.5 flex flex-col gap-1.5 shadow-sm cursor-grab active:cursor-grabbing hover:border-accent hover:shadow-md transition-all text-[12px]">
                            @if (count($card['tags']))
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($card['tags'] as $tag)
                                        <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded {{ $tagTones[$tag['color']] ?? $tagTones['blue'] }}">{{ $tag['label'] }}</span>
                                    @endforeach
                                </div>
                            @endif
                            <div class="font-semibold text-[#1f2937] leading-snug">{{ $card['title'] }}</div>
                            @if ($card['desc'])
                                <div class="text-[11px] text-muted leading-snug line-clamp-2">{{ $card['desc'] }}</div>
                            @endif
                            @if ($totalCount > 0)
                                <div class="flex items-center gap-1 text-[10px] text-muted font-semibold">☑️ {{ $doneCount }}/{{ $totalCount }}</div>
                            @endif
                            <div class="flex items-center justify-between mt-0.5">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    @if ($card['priority'])
                                        <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded {{ $prioTones[$card['priority']] }}">{{ $prioLabels[$card['priority']] }}</span>
                                    @endif
                                    @if ($dueLabel)
                                        <span class="text-[9.5px] font-semibold px-1.5 py-0.5 rounded flex items-center gap-1 {{ $isOverdue ? 'bg-[#fdecec] text-[#c0392b]' : 'bg-[#eef1f5] text-[#4b5568]' }}">{{ $isOverdue ? '⚠️' : '📅' }} {{ $dueLabel }}</span>
                                    @endif
                                </div>
                                @if ($card['assignee'])
                                    <div class="w-5 h-5 rounded-full bg-gradient-to-br {{ \App\Support\Grid\Avatar::gradient((int) $card['id']) }} text-white text-[8.5px] font-bold flex items-center justify-center shrink-0" title="{{ $card['assignee'] }}">{{ \App\Support\Grid\Avatar::initials($card['assignee']) }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    <div class="h-1.5 rounded-full transition-colors" x-bind:class="(overColumn === '{{ $col['key'] }}' && overIndex === {{ $cardCount }}) ? 'bg-accent' : 'bg-transparent'"></div>
                </div>

                <div class="p-2 flex flex-col gap-1.5">
                    <template x-if="composingColumn === '{{ $col['key'] }}'">
                        <div class="flex flex-col gap-1.5">
                            <textarea x-model="composerTitle" rows="2" placeholder="Judul kartu..."
                                class="w-full border border-accent rounded-lg p-2 text-[11.5px] resize-none outline-none"
                                x-on:keydown.enter.prevent="if (composerTitle.trim()) { $wire.{{ $addCardMethod }}('{{ $col['key'] }}', composerTitle, composerDue); composerTitle=''; composerDue=''; composingColumn=null; }"></textarea>
                            <div class="flex items-center gap-1.5 bg-white border border-[#d7dbe2] rounded-lg px-2 py-1">
                                <span class="text-[11px]">⏰</span>
                                <input type="datetime-local" x-model="composerDue" class="flex-1 text-[10.5px] outline-none">
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" class="px-3 py-1 rounded-md bg-accent text-white text-[11px] font-semibold cursor-pointer"
                                    x-on:click="if (composerTitle.trim()) { $wire.{{ $addCardMethod }}('{{ $col['key'] }}', composerTitle, composerDue); composerTitle=''; composerDue=''; composingColumn=null; }">Save</button>
                                <button type="button" class="w-7 h-7 rounded-md text-muted hover:bg-[#e2e8f0] cursor-pointer" x-on:click="composingColumn=null; composerTitle=''; composerDue=''">✕</button>
                            </div>
                        </div>
                    </template>
                    <button type="button" x-show="composingColumn !== '{{ $col['key'] }}'" x-on:click="composingColumn = '{{ $col['key'] }}'; composerTitle=''; composerDue=''"
                        class="w-full text-left px-2 py-1.5 rounded-md text-[11.5px] font-semibold text-muted hover:bg-[#e9edf3] cursor-pointer">➕ Add a card</button>
                </div>
            </div>
        @endforeach

        <div class="w-[260px] shrink-0">
            <template x-if="addingColumn">
                <div class="bg-white border border-[#d7dbe2] rounded-xl p-2.5 flex flex-col gap-1.5">
                    <input type="text" x-model="newColumnTitle" placeholder="Nama daftar..." autofocus
                        class="w-full border border-accent rounded-md px-2 py-1.5 text-[11.5px] outline-none"
                        x-on:keydown.enter="if (newColumnTitle.trim()) { $wire.{{ $addColumnMethod }}(newColumnTitle); newColumnTitle=''; addingColumn=false; }">
                    <div class="flex items-center gap-2">
                        <button type="button" class="px-3 py-1 rounded-md bg-accent text-white text-[11px] font-semibold cursor-pointer"
                            x-on:click="if (newColumnTitle.trim()) { $wire.{{ $addColumnMethod }}(newColumnTitle); newColumnTitle=''; addingColumn=false; }">Add list</button>
                        <button type="button" class="w-7 h-7 rounded-md text-muted hover:bg-[#e2e8f0] cursor-pointer" x-on:click="addingColumn=false; newColumnTitle=''">✕</button>
                    </div>
                </div>
            </template>
            <button type="button" x-show="! addingColumn" x-on:click="addingColumn = true"
                class="w-full text-left px-3.5 py-3 rounded-xl border border-dashed border-[#c7cfda] text-[12px] font-semibold text-muted hover:border-accent hover:text-navy cursor-pointer">➕ Add another list</button>
        </div>
    </div>

    {{-- MODAL DETAIL -- SELALU dirender (hidden class, bukan x-if) supaya
    kontennya selalu ikut segar hasil morph Livewire biasa (lihat catatan
    $activeCard di atas). --}}
    <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-[1000] p-4 {{ $activeCard ? '' : 'hidden' }}" wire:click.self="{{ $closeCardMethod }}()">
        @if ($activeCard)
            @php $card = $activeCard; @endphp
            <div wire:key="kanban-modal-{{ $card['id'] }}" x-data="{
                    popoverOpen: null,
                    newTagLabel: '',
                    newTagColor: 'blue',
                    newChecklistTitle: 'Checklist',
                    addingItem: false,
                    newItemText: '',
                    newComment: '',
                    editingDesc: false,
                    descDraft: {{ Js::from($card['desc']) }},
                }" class="bg-white w-full max-w-2xl max-h-[90vh] rounded-xl shadow-2xl flex flex-col overflow-hidden">
                <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-[#eef1f5]">
                    <div class="flex items-start gap-3 flex-1">
                        <span class="text-accent text-lg mt-0.5">☑️</span>
                        <div class="flex-1">
                            <input type="text" value="{{ $card['title'] }}"
                                x-on:change="$wire.{{ $updateCardMethod }}({{ $card['id'] }}, 'title', $event.target.value)"
                                class="w-full text-base font-bold text-navy outline-none border-2 border-transparent focus:border-accent rounded px-1.5 py-0.5 -ml-1.5">
                            <div class="text-[11px] text-muted mt-1">in list <strong>{{ $activeColLabel }}</strong></div>
                        </div>
                    </div>
                    <button type="button" wire:click="{{ $closeCardMethod }}()" class="w-7 h-7 rounded-md text-muted hover:bg-[#eef1f5] cursor-pointer shrink-0">✕</button>
                </div>

                <div class="flex-1 overflow-y-auto tm-scroll flex gap-6 px-6 py-4">
                    <div class="flex-[2] flex flex-col gap-4 min-w-0">
                        <div class="flex flex-wrap gap-2 relative">
                            <div class="relative">
                                <button type="button" x-on:click="popoverOpen = (popoverOpen === 'labels' ? null : 'labels')" class="bg-[#eef1f5] hover:bg-[#e2e8f0] text-[#374151] px-2.5 py-1.5 rounded-md text-[11.5px] font-semibold cursor-pointer">🏷️ Labels</button>
                                <div x-show="popoverOpen === 'labels'" x-on:click.outside="popoverOpen = null" x-cloak
                                    class="absolute top-[110%] left-0 w-[260px] bg-white border border-[#e2e8f0] rounded-lg shadow-xl p-3 z-10">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-[11px] font-bold text-navy">Labels</span>
                                        <button type="button" x-on:click="popoverOpen = null" class="text-muted cursor-pointer">✕</button>
                                    </div>
                                    <div class="flex flex-col gap-1 mb-2">
                                        @foreach ($card['tags'] as $ti => $tag)
                                            <div class="flex items-center justify-between px-2 py-1 rounded {{ $tagTones[$tag['color']] ?? $tagTones['blue'] }}">
                                                <span class="text-[11px] font-bold">{{ $tag['label'] }}</span>
                                                <button type="button" x-on:click="$wire.{{ $removeTagMethod }}({{ $card['id'] }}, {{ $ti }})" class="text-[10px] cursor-pointer">✕</button>
                                            </div>
                                        @endforeach
                                    </div>
                                    <input type="text" x-model="newTagLabel" placeholder="Nama label..." class="w-full border border-[#d7dbe2] rounded px-2 py-1 text-[11px] mb-1.5 outline-none focus:border-accent">
                                    <div class="flex items-center gap-1.5 mb-2">
                                        @foreach (['blue', 'orange', 'green', 'purple'] as $c)
                                            <button type="button" x-on:click="newTagColor = '{{ $c }}'"
                                                x-bind:class="newTagColor === '{{ $c }}' ? 'ring-2 ring-offset-1 ring-navy' : ''"
                                                class="w-5 h-5 rounded {{ $tagTones[$c] }} cursor-pointer"></button>
                                        @endforeach
                                    </div>
                                    <button type="button" class="w-full bg-accent text-white rounded py-1.5 text-[11px] font-semibold cursor-pointer"
                                        x-on:click="if (newTagLabel.trim()) { $wire.{{ $addTagMethod }}({{ $card['id'] }}, newTagLabel, newTagColor); newTagLabel=''; }">Add</button>
                                </div>
                            </div>

                            <div class="relative">
                                <button type="button" x-on:click="popoverOpen = (popoverOpen === 'dates' ? null : 'dates')" class="bg-[#eef1f5] hover:bg-[#e2e8f0] text-[#374151] px-2.5 py-1.5 rounded-md text-[11.5px] font-semibold cursor-pointer">🕒 Dates</button>
                                <div x-show="popoverOpen === 'dates'" x-on:click.outside="popoverOpen = null" x-cloak
                                    x-data="{ v: {{ Js::from($card['dueDate'] ?? '') }} }"
                                    class="absolute top-[110%] left-0 w-[260px] bg-white border border-[#e2e8f0] rounded-lg shadow-xl p-3 z-10">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-[11px] font-bold text-navy">Due date & time</span>
                                        <button type="button" x-on:click="popoverOpen = null" class="text-muted cursor-pointer">✕</button>
                                    </div>
                                    <input type="datetime-local" x-model="v" class="w-full border border-[#d7dbe2] rounded px-2 py-1 text-[11px] mb-2 outline-none focus:border-accent">
                                    <button type="button" class="w-full bg-accent text-white rounded py-1.5 text-[11px] font-semibold cursor-pointer"
                                        x-on:click="$wire.{{ $updateCardMethod }}({{ $card['id'] }}, 'dueDate', v); popoverOpen = null">Save</button>
                                </div>
                            </div>

                            <div class="relative">
                                <button type="button" x-on:click="popoverOpen = (popoverOpen === 'checklist' ? null : 'checklist')" class="bg-[#eef1f5] hover:bg-[#e2e8f0] text-[#374151] px-2.5 py-1.5 rounded-md text-[11.5px] font-semibold cursor-pointer">☑️ Checklist</button>
                                <div x-show="popoverOpen === 'checklist'" x-on:click.outside="popoverOpen = null" x-cloak
                                    class="absolute top-[110%] left-0 w-[260px] bg-white border border-[#e2e8f0] rounded-lg shadow-xl p-3 z-10">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-[11px] font-bold text-navy">Add checklist</span>
                                        <button type="button" x-on:click="popoverOpen = null" class="text-muted cursor-pointer">✕</button>
                                    </div>
                                    <input type="text" x-model="newChecklistTitle" class="w-full border border-[#d7dbe2] rounded px-2 py-1 text-[11px] mb-2 outline-none focus:border-accent">
                                    <button type="button" class="w-full bg-accent text-white rounded py-1.5 text-[11px] font-semibold cursor-pointer"
                                        x-on:click="$wire.{{ $addChecklistMethod }}({{ $card['id'] }}, newChecklistTitle); popoverOpen = null">Add</button>
                                </div>
                            </div>

                            <div class="relative">
                                <button type="button" x-on:click="popoverOpen = (popoverOpen === 'members' ? null : 'members')" class="bg-[#eef1f5] hover:bg-[#e2e8f0] text-[#374151] px-2.5 py-1.5 rounded-md text-[11.5px] font-semibold cursor-pointer">👤 Members</button>
                                <div x-show="popoverOpen === 'members'" x-on:click.outside="popoverOpen = null" x-cloak
                                    class="absolute top-[110%] left-0 w-[240px] bg-white border border-[#e2e8f0] rounded-lg shadow-xl p-3 z-10">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-[11px] font-bold text-navy">Members</span>
                                        <button type="button" x-on:click="popoverOpen = null" class="text-muted cursor-pointer">✕</button>
                                    </div>
                                    <div class="flex items-center gap-2 px-1.5 py-1.5 rounded hover:bg-[#f4f6fa] cursor-pointer"
                                        x-on:click="$wire.{{ $updateCardMethod }}({{ $card['id'] }}, 'assignee', {{ Js::from($currentUserName) }}); popoverOpen = null">
                                        <div class="w-6 h-6 rounded-full bg-accent text-white text-[10px] font-bold flex items-center justify-center">{{ \App\Support\Grid\Avatar::initials($currentUserName) }}</div>
                                        <span class="text-[11.5px]">{{ $currentUserName }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="text-[12.5px] font-bold text-navy flex items-center gap-1.5 mb-1.5">≡ Description</div>
                            <template x-if="! editingDesc">
                                <div x-on:click="editingDesc = true" class="bg-[#f9fafb] border border-[#eef1f5] rounded-lg p-3 text-[12px] text-[#374151] min-h-[56px] cursor-pointer hover:border-accent whitespace-pre-line" x-text="descDraft || 'Add a more detailed description...'"></div>
                            </template>
                            <template x-if="editingDesc">
                                <div class="border-2 border-accent rounded-lg overflow-hidden">
                                    <textarea x-model="descDraft" rows="4" class="w-full p-3 text-[12px] outline-none resize-y"></textarea>
                                    <div class="flex items-center gap-2 px-3 py-2 bg-[#f9fafb]">
                                        <button type="button" class="px-3 py-1 rounded-md bg-accent text-white text-[11px] font-semibold cursor-pointer"
                                            x-on:click="$wire.{{ $updateCardMethod }}({{ $card['id'] }}, 'desc', descDraft); editingDesc = false">Save</button>
                                        <button type="button" class="text-[11px] text-muted cursor-pointer" x-on:click="editingDesc = false">Cancel</button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        @if ($card['checklistTitle'])
                            @php $done = collect($card['checklist'])->where('done', true)->count(); $total = count($card['checklist']); $pct = $total ? (int) round($done / $total * 100) : 0; @endphp
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <div class="text-[12.5px] font-bold text-navy flex items-center gap-1.5">☑️ {{ $card['checklistTitle'] }}</div>
                                    <button type="button" wire:click="{{ $deleteChecklistMethod }}({{ $card['id'] }})" class="text-[10.5px] font-semibold text-[#c0392b] bg-[#fdecec] px-2 py-1 rounded cursor-pointer">Delete</button>
                                </div>
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-[10.5px] text-muted font-semibold">{{ $pct }}%</span>
                                    <div class="flex-1 h-1.5 bg-[#eef1f5] rounded-full overflow-hidden"><div class="h-full bg-accent" style="width:{{ $pct }}%"></div></div>
                                </div>
                                <div class="flex flex-col gap-1 mb-2">
                                    @foreach ($card['checklist'] as $item)
                                        <label class="flex items-center gap-2 text-[12px] cursor-pointer">
                                            <input type="checkbox" @checked($item['done']) wire:change="{{ $toggleChecklistItemMethod }}({{ $card['id'] }}, {{ $item['id'] }})" class="w-3.5 h-3.5 accent-accent cursor-pointer">
                                            <span class="{{ $item['done'] ? 'line-through text-muted' : 'text-[#374151]' }}">{{ $item['text'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <template x-if="! addingItem">
                                    <button type="button" x-on:click="addingItem = true" class="bg-[#eef1f5] hover:bg-[#e2e8f0] text-[#374151] px-2.5 py-1.5 rounded-md text-[11px] font-semibold cursor-pointer">Add an item</button>
                                </template>
                                <template x-if="addingItem">
                                    <div class="border border-accent rounded-md p-2 flex flex-col gap-1.5">
                                        <textarea x-model="newItemText" rows="2" placeholder="Add an item" class="w-full text-[11.5px] outline-none resize-none"></textarea>
                                        <div class="flex items-center gap-2">
                                            <button type="button" class="px-2.5 py-1 rounded-md bg-accent text-white text-[11px] font-semibold cursor-pointer"
                                                x-on:click="if (newItemText.trim()) { $wire.{{ $addChecklistItemMethod }}({{ $card['id'] }}, newItemText); newItemText=''; }">Add</button>
                                            <button type="button" class="text-[11px] text-muted cursor-pointer" x-on:click="addingItem = false; newItemText=''">✕</button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        @endif
                    </div>

                    <div class="flex-1 flex flex-col gap-3 min-w-[200px]">
                        <div class="text-[12.5px] font-bold text-navy flex items-center gap-1.5">💬 Comments</div>
                        <div class="flex gap-2">
                            <div class="w-7 h-7 rounded-full bg-accent text-white text-[10px] font-bold flex items-center justify-center shrink-0">{{ \App\Support\Grid\Avatar::initials($currentUserName) }}</div>
                            <div class="flex-1 flex flex-col gap-1.5">
                                <textarea x-model="newComment" rows="2" placeholder="Write a comment..." class="w-full border border-[#d7dbe2] rounded-lg p-2 text-[11.5px] outline-none focus:border-accent resize-none"></textarea>
                                <button type="button" x-show="newComment.trim() !== ''" class="self-start px-3 py-1 rounded-md bg-accent text-white text-[11px] font-semibold cursor-pointer"
                                    x-on:click="$wire.{{ $addCommentMethod }}({{ $card['id'] }}, newComment); newComment=''">Save</button>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2.5">
                            @foreach (array_reverse($card['comments']) as $comment)
                                <div class="flex gap-2">
                                    <div class="w-7 h-7 rounded-full bg-[#8b5cf6] text-white text-[10px] font-bold flex items-center justify-center shrink-0">{{ $comment['initials'] }}</div>
                                    <div class="text-[11.5px]">
                                        <div><strong class="text-[#1f2937]">{{ $comment['author'] }}</strong></div>
                                        <div class="text-[#374151]">{{ $comment['text'] }}</div>
                                        <div class="text-[10px] text-muted mt-0.5">{{ $comment['at'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
