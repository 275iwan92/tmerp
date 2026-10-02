{{--
    Date/time picker kustom (docs/FORM_UI_STANDARD.md §3), pengganti standar
    utk semua field date/datetime -- native <input type="date"|"datetime-local">
    TIDAK dipakai lagi sejak keputusan user 2026-09-02. Logic Alpine ada di
    resources/js/date-picker.js (Alpine.data('tmDatePicker', ...)).

    wire:model/name diteruskan ke <input type="hidden" x-ref="model"> lewat
    $attributes -- BUKAN ke elemen ber-x-data ini sendiri (sama pola dgn
    grid/action-dropdown.blade.php). Value yg dikirim ke situ SELALU format ISO
    ('Y-m-d' utk mode=date, 'Y-m-d H:i:s' utk mode=datetime) supaya langsung
    cocok dgn cast Carbon/date di Model -- tampilan manusiawi ("2 Sep 2026")
    cuma di input kedua yang readonly & tidak punya wire:model.

    Props:
    - mode: 'date' (default) | 'datetime' -- datetime nambah pemilih Jam:Menit.
    - min/max: batas tanggal boleh dipilih, format 'Y-m-d' (opsional).
    - label/name/hint/error/legacy/col2: sama seperti x-form.input.
--}}
@props([
    'label' => null,
    'name' => null,
    'mode' => 'date',
    'hint' => null,
    'error' => null,
    'legacy' => false,
    'col2' => false,
    'min' => null,
    'max' => null,
])
@php
    $isLegacy = (bool) $legacy;
    $legacyClass = is_string($legacy) ? $legacy : 'f';
    $errorMsg = $error ?? ($name ? $errors->first($name) : null);
    $wrapperClass = $isLegacy ? trim($legacyClass.' '.($col2 ? 'col2' : '')) : 'flex flex-col gap-1';
    $labelClass = $isLegacy ? '' : 'text-[10px] text-muted uppercase font-semibold block mb-1';
    $baseTriggerClass = $isLegacy ? '' : 'w-full px-2 py-1.5 pr-8 border border-field-border rounded text-xs focus:border-field-focus disabled:bg-field-soft disabled:text-muted disabled:cursor-not-allowed cursor-pointer bg-white';
    $isDatetime = $mode === 'datetime';
    $modelAttributes = $attributes->except('class');
    $triggerClass = trim($baseTriggerClass.' '.$attributes->get('class', ''));
@endphp
<div class="{{ $wrapperClass }} relative"
    x-data="tmDatePicker({ mode: '{{ $mode }}', min: @js($min), max: @js($max) })"
    x-init="$nextTick(() => init($refs.model.value))"
    @click.outside="open = false">
    @if ($label)
        <label class="{{ $labelClass }}">{{ $label }}</label>
    @endif

    <input type="hidden" x-ref="model" {{ $modelAttributes }}>

    <div class="relative flex items-center">
        <input type="text" readonly x-bind:value="display" @click="toggle()"
            placeholder="{{ $isDatetime ? t('Pick date & time...') : t('Pick a date...') }}"
            @if ($attributes->get('disabled')) disabled @endif
            class="{{ $triggerClass }}">
        <span class="absolute right-2.5 text-[13px] text-[#697386] pointer-events-none">{{ $isDatetime ? '🕒' : '📅' }}</span>
    </div>

    <div x-show="open" x-cloak x-transition.origin.top.left @click.stop
        class="absolute z-30 top-full left-0 mt-1 w-[260px] bg-white border border-field-border rounded-lg shadow-lg overflow-hidden text-xs">
        <div class="px-3 py-2 bg-field-soft border-b border-field-border font-semibold text-[#374151]">
            <span x-text="mode === 'datetime' ? @js(t('Pick Date & Time')) : @js(t('Pick Date'))"></span>
        </div>

        <div class="p-2">
            <div class="flex items-center justify-between mb-1.5 gap-1">
                <button type="button" @click="prevMonth()"
                    class="w-6 h-6 rounded border border-field-border hover:bg-field-soft cursor-pointer text-[#374151]">&lt;</button>
                <div class="flex items-center gap-1">
                    <select x-model.number="viewMonth" @change="buildDays()"
                        class="border border-field-border rounded px-1 py-0.5 text-[11px]">
                        <template x-for="(m, i) in monthNames" :key="i">
                            <option :value="i" x-text="m"></option>
                        </template>
                    </select>
                    <select x-model.number="viewYear" @change="buildDays()"
                        class="border border-field-border rounded px-1 py-0.5 text-[11px]">
                        <template x-for="y in yearRange" :key="y">
                            <option :value="y" x-text="y"></option>
                        </template>
                    </select>
                </div>
                <button type="button" @click="nextMonth()"
                    class="w-6 h-6 rounded border border-field-border hover:bg-field-soft cursor-pointer text-[#374151]">&gt;</button>
            </div>

            <div class="[display:grid] grid-cols-7 gap-0.5 text-center mb-0.5">
                <template x-for="d in dayLabels" :key="d">
                    <div class="text-[10px] font-semibold text-[#697386]" x-text="d"></div>
                </template>
            </div>
            <div class="[display:grid] grid-cols-7 gap-0.5">
                <template x-for="(cell, idx) in days" :key="idx">
                    <button type="button"
                        x-text="cell ?? ''"
                        :disabled="cell === null || !isSelectable(cell)"
                        @click="cell !== null && selectDay(cell)"
                        :class="cell === null ? 'invisible' :
                            (cell === selDay && viewMonth === selMonth && viewYear === selYear
                                ? 'bg-accent text-white font-semibold'
                                : (!isSelectable(cell) ? 'text-[#c7cfda] cursor-not-allowed' : 'hover:bg-field-soft text-[#374151] cursor-pointer'))"
                        class="h-6 rounded text-[11px]"></button>
                </template>
            </div>
        </div>

        <template x-if="mode === 'datetime'">
            <div class="flex items-center gap-2 px-3 py-2 border-t border-field-border">
                <span class="text-[10px] font-semibold text-[#697386] uppercase">{{ t('Time') }}</span>
                <select x-model.number="selHour" @change="apply()" class="border border-field-border rounded px-1 py-0.5 text-[11px]">
                    <template x-for="h in 24" :key="h"><option :value="h - 1" x-text="String(h - 1).padStart(2, '0')"></option></template>
                </select>
                <span class="text-[#697386] font-semibold">:</span>
                <select x-model.number="selMinute" @change="apply()" class="border border-field-border rounded px-1 py-0.5 text-[11px]">
                    <template x-for="m in 12" :key="m"><option :value="(m - 1) * 5" x-text="String((m - 1) * 5).padStart(2, '0')"></option></template>
                </select>
            </div>
        </template>

        <div class="flex items-center justify-between gap-2 px-3 py-2 border-t border-field-border bg-[#f9fafb]">
            <button type="button" @click="clearValue()" class="text-[11px] font-semibold text-[#697386] hover:text-[#374151] cursor-pointer">{{ t('Clear') }}</button>
            <button type="button" @click="today()" class="px-3 py-1 rounded bg-accent text-white text-[11px] font-semibold cursor-pointer hover:bg-[#1d4ed8]">{{ t('Today') }}</button>
        </div>
    </div>

    @if ($hint)
        <div class="{{ $isLegacy ? 'hint' : 'text-[10.5px] text-muted' }}">{{ $hint }}</div>
    @endif
    @if ($errorMsg)
        <span class="{{ $isLegacy ? 'hint' : 'text-[10.5px] text-red-600' }}" @if ($isLegacy) style="color:#e55353" @endif>{{ $errorMsg }}</span>
    @endif
</div>
