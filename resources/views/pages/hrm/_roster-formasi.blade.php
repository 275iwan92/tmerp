{{-- Formasi Harian (desain disetujui user 2026-09-19, docs/mockups/mockup_roster_formasi_harian.jpg).
     Sengaja tema TERANG ala kertas formasi (tidak ikut mode gelap) supaya mudah dibaca.
     Di-include dari shift-roster.blade.php saat $view === 'formasi'. --}}
@php
    $fmRows = $this->formasiRows;
    $fmStats = $this->formasiStats;
    $fmDates = $this->weekDates;
    $fmFocus = $this->focusDate();
    $fmLocked = $this->isLocked();
    $fmDayLabels = \App\Models\Master\EmpShift::DAY_LABELS;
    $fmShiftTone = fn ($code) => match ($code) { 'SH1' => 'total', 'SH2' => 'warn', default => 'active' };
    $fmBoxes = 4 + count($fmStats['shifts']);
    $fmUnitNo = 0;
    $fmEditDays = $editOpen ? $this->editDays() : [];
@endphp

<div class="px-4 pb-7 pt-4 text-slate-800">

    {{-- (1) Kotak monitoring = filter --}}
    <div class="[display:grid] gap-2.5 mb-3" style="grid-template-columns: repeat({{ $fmBoxes }}, minmax(0, 1fr));">
        <x-grid.stat-box tone="total" label="{{ t('Scheduled') }} · {{ $fmDayLabels[\Carbon\Carbon::parse($fmFocus)->format('l')] }} {{ \Carbon\Carbon::parse($fmFocus)->format('d/m') }}"
            :value="$fmStats['today']" :subtitle="t('click = view per day')" action="$set('formasiMode', 'harian')" :selected="$formasiMode === 'harian'" />
        @foreach ($fmStats['shifts'] as $i => $s)
            <x-grid.stat-box :tone="$fmShiftTone($s['shift']->shift_code)" label="{{ preg_replace('/^TM Production /i', '', $s['shift']->shift_name) }}{{ str_contains($s['shift']->shift_name, ':') ? '' : ' · '.($s['shift']->dayPattern()[\Carbon\Carbon::parse($fmFocus)->dayOfWeekIso - 1]['in'] ?: t('off')) }}"
                :value="$s['count']" action="setFormasiFilter('shift:{{ $s['shift']->shift_id }}')" :selected="$formasiFilter === 'shift:'.$s['shift']->shift_id" />
        @endforeach
        <x-grid.stat-box tone="extra" :label="t('Piece-rate called')" :value="$fmStats['borongan']" :subtitle="t('that day')"
            action="setFormasiFilter('borongan')" :selected="$formasiFilter === 'borongan'" />
        <x-grid.stat-box tone="critical" :label="t('Empty units')" :value="count($fmStats['empty'])" :subtitle="\Illuminate\Support\Str::limit(implode(', ', $fmStats['empty']), 34) ?: t('all filled')"
            action="setFormasiFilter('kosong')" :selected="$formasiFilter === 'kosong'" />
        <x-grid.stat-box tone="active" :label="t('Weekly staff not scheduled')" :value="$fmStats['unscheduled']" :subtitle="t('this week')"
            action="setFormasiFilter('belum')" :selected="$formasiFilter === 'belum'" />
    </div>

    <div class="[display:grid] grid-cols-1 xl:grid-cols-[minmax(0,1fr)_320px] gap-3 items-start">

        <section class="bg-white border border-slate-200 rounded-lg overflow-hidden">
            {{-- (2) Toolbar --}}
            <div class="flex items-center gap-2 px-3 py-2.5 border-b border-slate-200 flex-wrap">
                <div class="flex bg-slate-100 rounded-md p-0.5">
                    <button type="button" wire:click="$set('formasiMode', 'ringkas')"
                        class="px-3 py-1 rounded text-[11px] cursor-pointer {{ $formasiMode === 'ringkas' ? 'bg-white shadow font-bold' : 'font-semibold text-slate-500' }}">{{ t('Week summary') }}</button>
                    <button type="button" wire:click="$set('formasiMode', 'harian')"
                        class="px-3 py-1 rounded text-[11px] cursor-pointer {{ $formasiMode === 'harian' ? 'bg-white shadow font-bold' : 'font-semibold text-slate-500' }}">{{ t('Per day') }}</button>
                </div>
                <div class="flex gap-1 flex-wrap">
                    @foreach ($fmDates as $d)
                        <button type="button" wire:key="fmday-{{ $d->toDateString() }}" wire:click="setFormasiDay('{{ $d->toDateString() }}')"
                            class="px-2 py-1 rounded border text-[11px] cursor-pointer {{ $d->toDateString() === $fmFocus ? 'bg-navy text-white border-navy' : 'border-slate-200 hover:bg-slate-50' }} {{ $d->isSunday() ? 'text-red-500' : '' }}">
                            {{ $fmDayLabels[$d->format('l')] }} {{ $d->format('d') }}{{ $d->isToday() ? ' · '.t('today') : '' }}
                        </button>
                    @endforeach
                </div>
                <div class="ml-auto flex items-center gap-2 flex-wrap">
                    <input type="text" wire:model.live.debounce.300ms="formasiSearch" placeholder="🔍 {{ t('Search name in formation…') }}"
                        class="w-48 px-2.5 py-1 border border-slate-300 rounded-md text-[11px] bg-white">
                    <label class="flex items-center gap-1 text-[11px] text-slate-500 cursor-pointer">
                        <input type="checkbox" wire:model.live="formasiAllUnits" class="cursor-pointer"> {{ t('All units') }}
                    </label>
                    @unless ($fmLocked)
                        <button type="button" wire:click="copyPrevWeek" wire:confirm="{{ t('Copy the whole formation from last week to this week?') }}"
                            class="px-2.5 py-1 rounded-md border border-slate-300 text-[11px] font-semibold cursor-pointer hover:bg-slate-50">⧉ {{ t('Copy last week') }}</button>
                    @endunless
                    <a href="{{ route('hrm.shift-roster.print', ['mode' => 'formasi', 'schedule' => $scheduleId, 'week' => $weekOffset]) }}" target="_blank"
                        class="px-2.5 py-1 rounded-md border border-slate-300 text-[11px] font-semibold no-underline text-slate-800 hover:bg-slate-50">🖨 {{ t('Print') }}</a>
                </div>
            </div>

            {{-- (3) Tabel ala kertas formasi --}}
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-[11.5px] {{ $formasiMode === 'harian' ? 'min-w-[1100px]' : '' }}">
                    <thead class="bg-slate-50 text-slate-500 uppercase text-[10px] tracking-wide">
                        <tr>
                            <th class="border border-slate-200 px-2 py-2 w-8">{{ t('No.') }}</th>
                            <th class="border border-slate-200 px-2 py-2 w-36 text-left">{{ t('Position') }}</th>
                            <th class="border border-slate-200 px-2 py-2 w-40 text-left">{{ t('Working hours') }}</th>
                            @if ($formasiMode === 'ringkas')
                                <th class="border border-slate-200 px-2 py-2 text-left">{{ t('On duty') }} ({{ $this->weekStart->format('d') }}–{{ $this->weekEnd->copy()->subDay()->format('d M') }})</th>
                            @else
                                @foreach ($fmDates as $d)
                                    <th class="border border-slate-200 px-2 py-2 {{ $d->toDateString() === $fmFocus ? 'bg-blue-50 text-navy' : '' }} {{ $d->isSunday() ? 'text-red-500' : '' }}">
                                        {{ $fmDayLabels[$d->format('l')] }}<br><span class="font-mono normal-case">{{ $d->format('d/m') }}</span>
                                    </th>
                                @endforeach
                            @endif
                            <th class="border border-slate-200 px-2 py-2 w-9"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($fmRows as $row)
                            <tr wire:key="fm-{{ $row['wc']->work_center_id }}-{{ $row['shift']?->shift_id ?? 0 }}"
                                class="{{ $row['empty'] ? 'bg-red-50/60' : ($row['summary']['handover'] ?? false ? 'bg-amber-50/70' : '') }} {{ $edit_wc === $row['wc']->work_center_id && $edit_shift === $row['shift']?->shift_id && $editOpen ? 'outline outline-2 outline-accent' : '' }}">
                                @if ($row['first'])
                                    <td rowspan="{{ $row['span'] }}" class="border border-slate-200 px-2 py-1.5 text-center text-slate-400 align-middle">{{ ++$fmUnitNo }}</td>
                                    <td rowspan="{{ $row['span'] }}" class="border border-slate-200 px-2 py-1.5 font-bold uppercase align-middle">{{ $row['wc']->work_center_name }}</td>
                                @endif
                                <td class="border border-slate-200 px-2 py-1.5 align-middle border-l-4
                                    {{ match ($row['shift']?->shift_code) { 'SH1' => 'border-l-blue-600', 'SH2' => 'border-l-amber-500', null => 'border-l-slate-300', default => 'border-l-slate-500' } }}">
                                    @if ($row['shift'])
                                        <b>{{ preg_replace('/^TM Production /i', '', $row['shift']->shift_name) }}</b>
                                        <span class="block text-[10px] font-mono text-slate-400">{{ implode(' · ', $row['shift']->summaryLines()) }}</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                @if ($formasiMode === 'ringkas')
                                    <td class="border border-slate-200 px-2 py-1.5 align-middle cursor-pointer hover:bg-blue-50/50"
                                        wire:click="openRow({{ $row['wc']->work_center_id }}, {{ $row['shift']?->shift_id ?? 'null' }})">
                                        @if ($row['empty'])
                                            <span class="text-red-600 font-semibold">{{ t('Empty this week') }}</span> <span class="text-slate-400">· {{ t('click to fill') }}</span>
                                        @else
                                            @foreach ($row['summary']['base'] as $id)
                                                @include('pages.hrm._roster-formasi-chip', ['p' => $row['people'][$id], 'day' => null])
                                            @endforeach
                                            @foreach ($row['summary']['groups'] as $g)
                                                @if ($row['summary']['handover'] && ! $loop->first)
                                                    <span class="mx-1 text-amber-600">→</span>
                                                @endif
                                                <span class="text-[10px] font-bold text-slate-500 {{ $row['summary']['base'] ? 'ml-2' : '' }}">{{ $row['summary']['base'] ? '+ ' : '' }}{{ $g['label'] }}:</span>
                                                @foreach ($g['ids'] as $id)
                                                    @include('pages.hrm._roster-formasi-chip', ['p' => $row['people'][$id], 'day' => null])
                                                @endforeach
                                            @endforeach
                                            @if ($row['summary']['handover'])
                                                <span class="ml-2 text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 font-bold">{{ t('CHANGES') }}</span>
                                            @endif
                                        @endif
                                    </td>
                                @else
                                    @foreach ($fmDates as $d)
                                        <td class="border border-slate-200 px-1.5 py-1 text-center align-middle cursor-pointer hover:bg-blue-50/50 {{ $d->toDateString() === $fmFocus ? 'bg-blue-50/40' : '' }}"
                                            wire:click="openRow({{ $row['wc']->work_center_id }}, {{ $row['shift']?->shift_id ?? 'null' }})">
                                            @forelse ($row['cells'][$d->toDateString()] ?? [] as $id)
                                                @include('pages.hrm._roster-formasi-chip', ['p' => $row['people'][$id], 'day' => $d->toDateString()])
                                            @empty
                                                <span class="text-slate-300">·</span>
                                            @endforelse
                                        </td>
                                    @endforeach
                                @endif

                                <td class="border border-slate-200 text-center align-middle">
                                    <button type="button" wire:click="openRow({{ $row['wc']->work_center_id }}, {{ $row['shift']?->shift_id ?? 'null' }})"
                                        title="{{ t('Edit this row') }}" class="cursor-pointer text-slate-400 hover:text-accent px-1">✎</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $formasiMode === 'ringkas' ? 5 : 11 }}" class="border border-slate-200 px-3 py-6 text-center text-slate-400">
                                    {{ $formasiFilter || $formasiSearch ? t('No rows match the filter.') : t('No matching rows.') }}
                                    @if (! $formasiFilter && ! $formasiSearch) {{ t('Use "Copy last week" or tick "All units" then click ✎ to fill.') }} @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-3 py-2 border-t border-slate-200 text-[10.5px] text-slate-500 flex gap-4 flex-wrap items-center">
                <span><span class="inline-block w-1 h-3 align-middle bg-blue-600"></span> {{ t('Shift') }} 1</span>
                <span><span class="inline-block w-1 h-3 align-middle bg-amber-500"></span> {{ t('Shift') }} 2</span>
                <span><span class="inline-block w-1 h-3 align-middle bg-slate-500"></span> {{ t('Regular') }}</span>
                <span><span class="px-1.5 rounded-full border border-violet-300 bg-violet-50 text-violet-700 font-semibold">{{ t('piece-rate') }}</span> {{ t('called when work is excessive') }}</span>
                <span><span class="px-1.5 rounded-full border border-orange-300 bg-orange-50 text-orange-700 font-semibold">⇄</span> {{ t('at 2 posts that day') }}</span>
                <span><span class="px-1.5 rounded-full border border-red-300 bg-red-50 text-red-700 font-semibold">⚠</span> {{ t('not yet certified for this unit') }}</span>
                <span class="ml-auto">{{ t('Name = nickname (hover for the full name)') }}</span>
            </div>
        </section>

        {{-- (6) Panel kanan: editor baris, atau daftar belum terjadwal --}}
        <aside class="bg-white border border-slate-200 rounded-lg overflow-hidden xl:sticky xl:top-3">
            @if ($editOpen)
                <div class="px-3 py-2.5 border-b border-slate-200 flex items-start gap-2">
                    <div>
                        <div class="font-bold text-[13px] uppercase">{{ \App\Models\Master\EmpWorkCenter::find($edit_wc)?->work_center_name }}</div>
                        <div class="text-[10.5px] text-slate-500">{{ t('Tick the working days of each person') }} · {{ t('Week') }} {{ $this->weekNumber() }}</div>
                    </div>
                    <button type="button" wire:click="closeRow" class="ml-auto text-lg leading-none cursor-pointer text-slate-400">×</button>
                </div>
                <div class="px-3 pt-2.5">
                    <x-form.select :label="t('Working hours')" wire:model.live="edit_shift" liveSync :selected="$edit_shift" :placeholder="t('— select shift —')"
                        :options="$this->shifts->mapWithKeys(fn ($s) => [$s->shift_id => $s->shift_name.' ('.implode(', ', $s->summaryLines()).')'])" />
                    @unless ($edit_new)
                        <p class="text-[10px] text-slate-400 mt-1 mb-0">{{ t('Change working hours = move this whole row to another shift.') }}</p>
                    @endunless
                </div>
                <table class="w-full text-[11px] mt-2">
                    <thead class="text-slate-500 text-[10px]">
                        <tr>
                            <th class="text-left px-3 py-1.5">{{ t('Name') }}</th>
                            @foreach ($fmEditDays as $d)
                                <th class="w-7 {{ $d['on'] ? '' : 'text-slate-300' }}">{{ $d['label'] }}</th>
                            @endforeach
                            <th class="w-6"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->editPeople as $id => $e)
                            <tr wire:key="edit-{{ $id }}" class="border-t border-slate-100">
                                <td class="px-3 py-1.5 font-semibold uppercase" title="{{ $e->full_name }} · {{ $e->employee_code }}">{{ \Illuminate\Support\Str::limit($e->nickname ?: $e->full_name, 16) }}</td>
                                @foreach ($fmEditDays as $d)
                                    <td class="text-center">
                                        <input type="checkbox" wire:click="toggleDay({{ $id }}, '{{ $d['date'] }}')" @checked(! empty($edit_grid[$id][$d['date']]))
                                            @disabled(! $d['on'] && empty($edit_grid[$id][$d['date']])) class="cursor-pointer">
                                    </td>
                                @endforeach
                                <td class="text-center"><button type="button" wire:click="removeFromRow({{ $id }})" title="{{ t('Take out') }}" class="text-red-500 cursor-pointer">✕</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-3 py-3 text-slate-400">{{ t('No people yet — add them below.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-3 border-t border-slate-200 mt-2 flex flex-col gap-2">
                    <input type="text" wire:model.live.debounce.250ms="edit_search" placeholder="+ {{ t('Add person (search name / nickname / code)…') }}"
                        class="w-full px-2.5 py-1.5 border border-slate-300 rounded-md text-[11px] bg-white">
                    <div class="text-[10px] font-bold uppercase text-slate-500">{{ $edit_search === '' ? t('Not yet scheduled in this section') : t('Search results') }}</div>
                    <div class="flex flex-wrap gap-1">
                        @forelse ($this->editCandidates as $e)
                            <button type="button" wire:key="cand-{{ $e->employee_id }}" wire:click="addToRow({{ $e->employee_id }})"
                                title="{{ $e->full_name }} · {{ $e->position?->position_name }}"
                                class="px-2 py-0.5 rounded-full border border-slate-300 text-[11px] font-semibold cursor-pointer hover:bg-blue-50">+ {{ $e->nickname ?: $e->full_name }}</button>
                        @empty
                            <span class="text-[11px] text-slate-400">—</span>
                        @endforelse
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="closeRow" class="px-3 py-1.5 rounded-md border border-slate-300 text-xs font-semibold cursor-pointer">{{ t('Cancel') }}</button>
                        @unless ($fmLocked)
                            <button type="button" wire:click="saveRow" class="px-3 py-1.5 rounded-md bg-accent text-white text-xs font-semibold cursor-pointer">{{ t('Save') }}</button>
                        @endunless
                    </div>
                </div>
            @else
                <div class="px-3 py-2.5 border-b border-slate-200">
                    <div class="font-bold text-[13px]">{{ t('Weekly staff not scheduled') }} ({{ $this->formasiUnscheduled->count() }})</div>
                    <div class="text-[10.5px] text-slate-500">{{ t('Click ✎ on a row to place') }} · {{ t('piece-rate not counted') }}</div>
                </div>
                <div class="max-h-[560px] overflow-auto">
                    @forelse ($this->formasiUnscheduled->groupBy(fn ($e) => $e->position?->department?->department_name ?? '—') as $dept => $list)
                        <div class="px-3 pt-2 text-[10px] font-bold uppercase text-slate-500">{{ $dept }} · {{ $list->count() }}</div>
                        <div class="px-3 pb-1.5 flex flex-wrap gap-1">
                            @foreach ($list as $e)
                                <span wire:key="un-{{ $e->employee_id }}" title="{{ $e->full_name }} · {{ $e->position?->position_name }}"
                                    class="px-2 py-0.5 rounded-full border border-slate-200 text-[11px]">{{ $e->nickname ?: $e->full_name }}</span>
                            @endforeach
                        </div>
                    @empty
                        <div class="px-3 py-4 text-[11px] text-slate-400">{{ t('All weekly employees are scheduled.') }}</div>
                    @endforelse
                </div>
            @endif
        </aside>
    </div>
</div>
