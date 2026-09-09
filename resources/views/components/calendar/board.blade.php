{{--
    Komponen reusable "x-calendar.board" -- replika docs/mockups/
    mockup_UIUX_calendar.html (navbar Today/panah/judul+switch Day/Week/
    Month di atas 3 panel: mini-kalender+cari kiri, grid tengah, Today's
    Events+edit kanan), drag-drop event antar tanggal, event multi-hari
    melebar di baris all-day view Week. Tema warna ikut terang halaman
    pemanggil (mockup asli dark).

    Kontrak (sama pola components/gantt/board.blade.php & kanban/board.blade.php):
    HALAMAN resolve semua VM turunan (title/miniLabel/monthGrid/weekDays
    dengan events+spanStyle sudah tertanam/todayEvents+eventsOnCurrent
    dengan timeLabel sudah tertanam) krn component scope terisolasi, tak
    bisa panggil $this->calFormatTime()/calSpanStyle() milik Livewire induk
    -- komponen ini cuma urus mekanika UI (drag/drop, tombol) & panggil
    method Livewire lewat NAMA yang dioper prop.

    x-data HANYA menyimpan draggedId/hoverDate (state ephemeral drag UI,
    bukan data domain) -- semua isi dinamis (event, judul, dst) di-render
    server biasa (Blade @if/@foreach), BUKAN Alpine x-if/x-show, supaya
    selalu ikut segar tiap morph Livewire (lihat catatan panjang soal bug
    ini di kanban/board.blade.php & component-library.blade.php $mainTab).
--}}
@props([
    'view',
    'currentDate',
    'editingId',
    'searchQuery',
    'title',
    'miniLabel',
    'miniCalGrid',
    'monthGrid',
    'weekDays',
    'searchResults',
    'todayEvents',
    'selectedDateLabel',
    'eventsOnCurrent',
    'todayMethod' => 'calToday',
    'prevMethod' => 'calPrev',
    'nextMethod' => 'calNext',
    'setViewMethod' => 'calSetView',
    'createNewMethod' => 'calCreateNew',
    'miniPrevMethod' => 'calMiniPrevMonth',
    'miniNextMethod' => 'calMiniNextMonth',
    'selectDateMethod' => 'calSelectDate',
    'openEditMethod' => 'calOpenEdit',
    'deleteMethod' => 'calDeleteEvent',
    'closeEditMethod' => 'calCloseEdit',
    'saveEditMethod' => 'calSaveEdit',
    'moveEventMethod' => 'calMoveEvent',
    'searchQueryModel' => 'calSearchQuery',
    'editTitleModel' => 'calEditTitle',
    'editStartModel' => 'calEditStart',
    'editEndModel' => 'calEditEnd',
    'editDescModel' => 'calEditDesc',
])
@php
    $dotTone = ['green' => 'bg-[#16a34a]', 'amber' => 'bg-[#b45309]', 'red' => 'bg-[#c0392b]'];
    $chipTone = ['green' => 'bg-[#e9f9ef] text-[#16a34a]', 'amber' => 'bg-[#fef6e7] text-[#b45309]', 'red' => 'bg-[#fdecec] text-[#c0392b]'];
    $borderTone = ['green' => 'border-l-[#16a34a]', 'amber' => 'border-l-[#b45309]', 'red' => 'border-l-[#c0392b]'];
@endphp
<div class="bg-white border border-[#e2e8f0] rounded-xl overflow-hidden" x-data="{ draggedId: null, hoverDate: null }">

    {{-- TOP NAVBAR (span penuh 3 panel, persis .top-navbar mockup) --}}
    <div class="h-[56px] flex items-center gap-3 px-4 border-b border-[#e2e8f0] shrink-0">
        <span class="text-sm font-bold text-navy shrink-0">📅 Event Calendar</span>
        <button type="button" x-on:click="$wire.{{ $todayMethod }}()" class="px-3 py-1.5 rounded-md border border-[#d7dbe2] text-[11px] font-semibold cursor-pointer hover:bg-[#f4f6fa]">Today</button>
        <div class="flex gap-1">
            <button type="button" x-on:click="$wire.{{ $prevMethod }}()" class="w-8 h-8 rounded-md border border-[#d7dbe2] cursor-pointer hover:bg-[#f4f6fa] text-xs">❮</button>
            <button type="button" x-on:click="$wire.{{ $nextMethod }}()" class="w-8 h-8 rounded-md border border-[#d7dbe2] cursor-pointer hover:bg-[#f4f6fa] text-xs">❯</button>
        </div>
        <span class="text-base font-bold text-navy min-w-[150px]">{{ $title }}</span>
        <div class="ml-auto inline-flex items-center gap-1 bg-[#f1f3f7] border border-[#e2e8f0] rounded-full p-1">
            {{-- x-on:click="$wire..." (BUKAN wire:click polos) -- elemen ini
            disisipkan Alpine lewat template x-if bungkus tab Calendar, wire:click
            native kadang tak ter-bind andal thd konten yg disisipkan x-if
            (gotcha Livewire+Alpine); panggil $wire langsung dari Alpine SELALU
            jalan krn diproses lewat mesin directive Alpine sendiri, bukan scan
            terpisah Livewire. wire:key jaga identitas tiap tombol lintas-morph. --}}
            @foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $v => $vLabel)
                <button type="button" wire:key="cal-view-{{ $v }}" x-on:click="$wire.{{ $setViewMethod }}('{{ $v }}')"
                    class="px-3.5 py-1 rounded-full text-[11px] font-semibold cursor-pointer {{ $view === $v ? 'bg-navy text-white' : 'text-muted hover:text-navy' }}">{{ $vLabel }}</button>
            @endforeach
        </div>
    </div>

    <div class="flex h-[640px]">

        {{-- LEFT PANEL (280px di mockup, w-[250px] di sini) --}}
        <div class="w-[250px] shrink-0 border-r border-[#e2e8f0] p-5 flex flex-col gap-6 overflow-y-auto tm-scroll">
            <button type="button" x-on:click="$wire.{{ $createNewMethod }}()"
                class="flex items-center justify-center gap-2 bg-accent text-white rounded-lg py-2.5 text-[13px] font-bold cursor-pointer hover:opacity-90">➕ Create Event</button>

            <div>
                <div class="flex items-center justify-between mb-3 px-0.5">
                    <span class="text-[13px] font-bold text-navy">{{ $miniLabel }}</span>
                    <div class="flex gap-1">
                        <button type="button" x-on:click="$wire.{{ $miniPrevMethod }}()" class="w-6 h-6 rounded border border-[#e2e8f0] hover:bg-[#f4f6fa] cursor-pointer text-[11px]">‹</button>
                        <button type="button" x-on:click="$wire.{{ $miniNextMethod }}()" class="w-6 h-6 rounded border border-[#e2e8f0] hover:bg-[#f4f6fa] cursor-pointer text-[11px]">›</button>
                    </div>
                </div>
                {{-- [display:grid] BUKAN "grid" polos -- app.css .grid{grid-cols-4}
                legacy menang atas Tailwind grid-cols-7 kalau pakai class "grid"
                (feedback_legacy_grid_class_collision, lihat date-picker.blade.php). --}}
                <div class="[display:grid] grid-cols-7 gap-y-1.5 text-center">
                    @foreach (['M', 'S', 'S', 'R', 'K', 'J', 'S'] as $dn)
                        <div class="text-[10px] font-bold text-muted">{{ $dn }}</div>
                    @endforeach
                    @foreach ($miniCalGrid as $cell)
                        <button type="button" @if ($cell) x-on:click="$wire.{{ $selectDateMethod }}('{{ $cell['dateStr'] }}')" @endif
                            class="w-[26px] h-[26px] mx-auto text-[11px] rounded-full flex items-center justify-center
                                {{ ! $cell ? 'invisible' : ($cell['isToday'] ? 'bg-accent text-white font-bold' : ($cell['isSelected'] ? 'bg-[#eaf2ff] text-accent font-bold ring-1 ring-accent' : 'text-[#374151] hover:bg-[#f4f6fa] cursor-pointer')) }}">{{ $cell['day'] ?? '' }}</button>
                    @endforeach
                </div>
            </div>

            <div>
                <div class="relative">
                    <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted text-[11px]">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="{{ $searchQueryModel }}" placeholder="Search events..."
                        class="w-full pl-7 pr-2 py-2 border border-[#d7dbe2] rounded-lg text-[11.5px] focus:border-accent">
                </div>
                @if ($searchQuery !== '')
                    <div class="flex flex-col gap-1.5 mt-2.5">
                        @forelse ($searchResults as $ev)
                            <div x-on:click="$wire.{{ $openEditMethod }}({{ $ev['id'] }})"
                                class="bg-[#f9fafb] border border-[#eef1f5] rounded-lg p-2.5 cursor-pointer hover:border-accent">
                                <div class="flex items-center gap-1.5 text-[11.5px] font-bold text-[#374151]">
                                    <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $dotTone[$ev['colorClass']] ?? 'bg-muted' }}"></span>
                                    {{ $ev['title'] }}
                                </div>
                                <div class="text-[10px] text-muted mt-1">📅 {{ substr($ev['start'], 0, 10) }}</div>
                            </div>
                        @empty
                            <div class="text-[10.5px] text-muted text-center py-2">Tidak ada hasil.</div>
                        @endforelse
                    </div>
                @endif
            </div>
        </div>

        {{-- CENTER PANEL --}}
        <div class="flex-1 flex flex-col overflow-hidden bg-[#fdfdfe]">

            @if ($view === 'month')
                <div class="[display:grid] grid-cols-7 text-center text-[10.5px] font-bold text-muted uppercase border-b border-[#eef1f5] shrink-0 py-2">
                    @foreach (['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'] as $dn)
                        <div>{{ $dn }}</div>
                    @endforeach
                </div>
                <div class="[display:grid] grid-cols-7 flex-1 overflow-y-auto tm-scroll" style="grid-auto-rows: 1fr;">
                    @foreach ($monthGrid as $cell)
                        @if ($cell['type'] === 'adjacent')
                            <div class="border-r border-b border-[#eef1f5] p-1.5 min-h-[96px] bg-[#fafbfd]">
                                <span class="text-[11px] font-semibold text-[#c7cfda]">{{ $cell['day'] }}</span>
                            </div>
                        @else
                            <div class="border-r border-b border-[#eef1f5] p-1.5 min-h-[96px] flex flex-col gap-1 transition-colors {{ $cell['dateStr'] === $currentDate ? 'bg-[#eff6ff]' : '' }}"
                                x-on:dragover.prevent="hoverDate = '{{ $cell['dateStr'] }}'"
                                x-on:dragleave="hoverDate = (hoverDate === '{{ $cell['dateStr'] }}') ? null : hoverDate"
                                x-on:drop="$wire.{{ $moveEventMethod }}(draggedId, '{{ $cell['dateStr'] }}'); draggedId = null; hoverDate = null"
                                x-bind:class="hoverDate === '{{ $cell['dateStr'] }}' ? 'ring-2 ring-inset ring-accent bg-[#eff6ff]' : ''">
                                <button type="button" x-on:click="$wire.{{ $selectDateMethod }}('{{ $cell['dateStr'] }}')"
                                    class="w-6 h-6 rounded-full flex items-center justify-center text-[11.5px] font-semibold cursor-pointer
                                        {{ $cell['isToday'] ? 'bg-accent text-white' : 'text-[#374151] hover:bg-[#f4f6fa]' }}">{{ $cell['day'] }}</button>
                                @foreach ($cell['events'] as $ev)
                                    <div draggable="true" x-on:dragstart="draggedId = {{ $ev['id'] }}" x-on:dragend="draggedId = null; hoverDate = null"
                                        x-bind:class="draggedId === {{ $ev['id'] }} ? 'opacity-40 scale-95' : ''" x-on:click="$wire.{{ $openEditMethod }}({{ $ev['id'] }})"
                                        class="text-[10.5px] font-semibold px-1.5 py-1 rounded cursor-grab truncate transition-opacity {{ $chipTone[$ev['colorClass']] ?? 'bg-[#eef1f5] text-[#4b5568]' }}">{{ $ev['title'] }}</div>
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                </div>
            @else
                <div class="flex border-b border-[#e2e8f0] shrink-0 ml-[54px]">
                    @foreach ($weekDays as $d)
                        <div class="flex-1 text-center py-2 border-r border-[#eef1f5] last:border-r-0">
                            <div class="text-[10px] font-bold uppercase {{ $d['isToday'] ? 'text-accent' : 'text-muted' }}">{{ $d['dayLabel'] }}</div>
                            <button type="button" x-on:click="$wire.{{ $selectDateMethod }}('{{ $d['dateStr'] }}')"
                                class="w-9 h-9 mx-auto mt-1 rounded-full flex items-center justify-center text-lg font-medium cursor-pointer {{ $d['isToday'] ? 'bg-accent text-white font-bold' : 'text-[#374151] hover:bg-[#f4f6fa]' }}">{{ $d['dayNum'] }}</button>
                        </div>
                    @endforeach
                </div>
                <div class="flex border-b border-[#e2e8f0] min-h-[52px] shrink-0 relative">
                    <div class="w-[54px] shrink-0 flex items-start pt-2.5 pl-1"><span class="text-[9px] text-muted font-semibold">GMT+07</span></div>
                    @foreach ($weekDays as $d)
                        <div class="flex-1 border-l border-[#eef1f5] p-1 flex flex-col gap-1 relative"
                            x-on:dragover.prevent="hoverDate = 'ad-{{ $d['dateStr'] }}'"
                            x-on:dragleave="hoverDate = (hoverDate === 'ad-{{ $d['dateStr'] }}') ? null : hoverDate"
                            x-on:drop="$wire.{{ $moveEventMethod }}(draggedId, '{{ $d['dateStr'] }}'); draggedId = null; hoverDate = null"
                            x-bind:class="hoverDate === 'ad-{{ $d['dateStr'] }}' ? 'ring-2 ring-inset ring-accent bg-[#eff6ff]' : ''">
                            @foreach ($d['events'] as $ev)
                                <div draggable="true" x-on:dragstart="draggedId = {{ $ev['id'] }}" x-on:dragend="draggedId = null; hoverDate = null"
                                    x-bind:class="draggedId === {{ $ev['id'] }} ? 'opacity-40 scale-95' : ''" x-on:click="$wire.{{ $openEditMethod }}({{ $ev['id'] }})"
                                    style="{{ $ev['spanStyle'] }}"
                                    class="text-[10.5px] font-semibold px-1.5 py-1 rounded cursor-grab truncate transition-opacity {{ $chipTone[$ev['colorClass']] ?? 'bg-[#eef1f5] text-[#4b5568]' }}">{{ $ev['title'] }}</div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                <div class="flex-1 overflow-y-auto tm-scroll flex" x-init="$el.scrollTop = 8 * 48">
                    <div class="w-[54px] shrink-0">
                        @for ($h = 1; $h < 24; $h++)
                            <div class="h-[48px] text-right pr-2 text-[10px] text-muted -mt-2">{{ sprintf('%02d:00', $h) }}</div>
                        @endfor
                    </div>
                    @foreach ($weekDays as $d)
                        <div class="flex-1 border-l border-[#eef1f5] relative" style="background-image: linear-gradient(to bottom, transparent 47px, #f1f5f9 48px); background-size: 100% 48px;">
                            @for ($h = 0; $h < 24; $h++)<div class="h-[48px]"></div>@endfor
                            @if ($d['isToday'])
                                <div class="absolute left-0 right-0 h-[2px] bg-red-500 z-10" style="top: {{ (int) now()->format('H') * 48 + (int) now()->format('i') / 60 * 48 }}px">
                                    <span class="absolute -left-1 -top-[3px] w-2 h-2 rounded-full bg-red-500"></span>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- RIGHT PANEL (360px di mockup, w-[300px] di sini) --}}
        <div class="w-[300px] shrink-0 border-l border-[#e2e8f0] flex flex-col overflow-hidden">
            <div class="h-[40%] border-b border-[#e2e8f0] p-4 overflow-y-auto tm-scroll bg-[#fafbfd]">
                <div class="text-[10.5px] font-bold text-muted uppercase tracking-wide mb-3 flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-accent"></span> Today's Events
                </div>
                @forelse ($todayEvents as $ev)
                    <div class="bg-white border-l-[3px] rounded-md p-2.5 mb-2 shadow-sm {{ $borderTone[$ev['colorClass']] ?? 'border-l-[#d7dbe2]' }}">
                        <div class="text-[13px] font-semibold text-[#374151]">{{ $ev['title'] }}</div>
                        <div class="text-[10.5px] text-muted mt-1">🕒 {{ $ev['timeLabel'] }}</div>
                    </div>
                @empty
                    <div class="text-[10.5px] text-muted">Tidak ada event hari ini.</div>
                @endforelse
            </div>

            <div class="flex-1 p-4 overflow-y-auto tm-scroll">
                @if ($editingId === null)
                    <div class="text-base font-bold text-navy mb-0.5">{{ $selectedDateLabel }}</div>
                    <div class="text-[11px] text-muted mb-4">{{ count($eventsOnCurrent) }} event</div>
                    <div class="flex flex-col gap-2.5">
                        @forelse ($eventsOnCurrent as $ev)
                            <div x-on:click="$wire.{{ $openEditMethod }}({{ $ev['id'] }})" class="group relative bg-white border border-[#e2e8f0] rounded-lg p-3 cursor-pointer hover:border-accent transition-colors">
                                <button type="button" x-on:click.stop="$wire.{{ $deleteMethod }}({{ $ev['id'] }})"
                                    class="absolute top-2.5 right-2.5 text-red-500 opacity-0 group-hover:opacity-100 cursor-pointer text-xs transition-opacity">✕</button>
                                <div class="flex items-center gap-1.5 text-[13px] font-bold text-[#374151] pr-4">
                                    <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $dotTone[$ev['colorClass']] ?? 'bg-muted' }}"></span>
                                    {{ $ev['title'] }}
                                </div>
                                <div class="text-[10.5px] text-muted mt-1.5">🕒 {{ $ev['timeLabel'] }}</div>
                                <div class="text-[11px] text-[#4b5568] mt-1.5 leading-relaxed">{{ $ev['desc'] }}</div>
                            </div>
                        @empty
                            <div class="text-[11px] text-muted text-center py-8">No events scheduled for this day.</div>
                        @endforelse
                    </div>
                @else
                    <div class="text-sm font-bold text-navy mb-4">✏️ Edit Event</div>
                    <div class="flex flex-col gap-3">
                        <x-form.input wire:model="{{ $editTitleModel }}" placeholder="Event Title" />
                        <x-form.input type="datetime-local" label="Start Time" wire:model="{{ $editStartModel }}" />
                        <x-form.input type="datetime-local" label="End Time" wire:model="{{ $editEndModel }}" />
                        <x-form.textarea wire:model="{{ $editDescModel }}" placeholder="Description..." rows="3" />
                        <div class="flex justify-end gap-2 mt-1">
                            <button type="button" x-on:click="$wire.{{ $closeEditMethod }}()" title="Cancel" class="w-9 h-9 rounded-md bg-[#eef1f5] text-[#4b5568] cursor-pointer hover:bg-[#e2e8f0]">✕</button>
                            <button type="button" x-on:click="$wire.{{ $saveEditMethod }}()" title="Save" class="w-9 h-9 rounded-md bg-accent text-white cursor-pointer hover:opacity-90">💾</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
