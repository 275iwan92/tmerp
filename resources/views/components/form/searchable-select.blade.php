{{--
    Component library (docs/mockups/UIUX_components_part1.html) -- dropdown
    1 pilihan dgn kotak pencarian di dalam panel (mis. Lokasi Domisili yang
    daftarnya panjang). :options = array string polos (label = value). Hidden
    input (pola sama x-form.date-picker) utk wire:model.
--}}
@props(['label' => null, 'name' => null, 'options' => [], 'placeholder' => null, 'value' => ''])
@php
    $placeholder = $placeholder ?? t('— select or type —');
@endphp
<div class="flex flex-col gap-1 relative" x-data="tmSearchableSelect({ options: @js($options), initialLabel: @js($value) })" @click.outside="open = false">
    @if ($label)
        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ $label }}</label>
    @endif
    <input type="hidden" x-ref="model" {{ $attributes }}>
    <button type="button" @click="toggle()"
        class="w-full px-2 py-1.5 border border-field-border rounded text-xs bg-white flex items-center justify-between cursor-pointer text-left">
        <span x-text="selectedLabel || @js($placeholder)" :class="selectedLabel ? 'text-[#374151]' : 'text-muted'"></span>
        <span class="text-muted">▼</span>
    </button>
    <div x-show="open" x-cloak x-transition.origin.top
        class="absolute z-30 top-full left-0 mt-1 w-full bg-white border border-field-border rounded-lg shadow-lg overflow-hidden text-xs">
        <div class="p-1.5 bg-field-soft border-b border-field-border">
            <input type="text" x-ref="search" x-model="query" placeholder="{{ t('Type to search...') }}"
                class="w-full px-2 py-1 border border-field-border rounded text-[11px]">
        </div>
        <div class="max-h-[160px] overflow-y-auto">
            <template x-for="opt in filtered" :key="opt">
                <div @click="pick(opt)" x-text="opt" class="px-2.5 py-1.5 hover:bg-field-soft cursor-pointer text-[#374151]"></div>
            </template>
            <div x-show="filtered.length === 0" class="px-2.5 py-3 text-center text-muted">{{ t('No results.') }}</div>
        </div>
    </div>
</div>
