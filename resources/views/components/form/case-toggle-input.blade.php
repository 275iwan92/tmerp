{{--
    Component library (docs/mockups/UIUX_components_part1.html, item #1
    "Nama Lengkap") -- textarea auto-height (rows=1, tumbuh ikut konten) +
    tombol badge case-cycle (aa -> Aa -> AA) yang transform teks live saat
    diketik. Hidden input (pola sama x-form.date-picker) utk wire:model,
    krn textarea tampilan TIDAK langsung wire:model (nilai final selalu
    hasil transform, bukan raw keystroke).
--}}
@props(['label' => null, 'name' => null, 'placeholder' => null, 'hint' => null, 'error' => null, 'value' => ''])
@php $errorMsg = $error ?? ($name ? $errors->first($name) : null); @endphp
<div class="flex flex-col gap-1" x-data="tmCaseToggleInput({ initial: @js($value) })">
    @if ($label)
        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ $label }}</label>
    @endif
    <input type="hidden" x-ref="model" {{ $attributes }}>
    <div class="relative flex items-start">
        <textarea x-ref="ta" rows="1" @input="onInput($event)" placeholder="{{ $placeholder }}"
            class="w-full px-2 py-1.5 pr-9 border border-field-border rounded text-xs focus:border-field-focus resize-none overflow-hidden">{{ $value }}</textarea>
        <button type="button" @click="cycle()" tabindex="-1" title="Ganti mode huruf (kecil/Kapital/BESAR)"
            class="absolute right-1.5 top-1.5 text-[10px] font-bold bg-field-badge border border-field-border rounded px-1.5 py-0.5 cursor-pointer text-[#4b5568]"
            x-text="labels[modes[idx]]"></button>
    </div>
    @if ($hint)<div class="text-[10.5px] text-muted">{{ $hint }}</div>@endif
    @if ($errorMsg)<span class="text-[10.5px] text-red-600">{{ $errorMsg }}</span>@endif
</div>
