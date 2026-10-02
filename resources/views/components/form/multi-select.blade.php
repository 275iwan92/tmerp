{{--
    Component library (docs/mockups/UIUX_components_part1.html) -- dropdown
    tick-mark multi-pilihan (mis. Kemampuan Bahasa). :options = [{value,label}].
    Hidden input (pola sama x-form.date-picker) menampung JSON array string
    terpilih utk wire:model (decode di sisi Livewire pakai json_decode).
--}}
@props(['label' => null, 'name' => null, 'options' => [], 'selected' => [], 'placeholder' => null, 'value' => ''])
@php
    $placeholder = $placeholder ?? t('Select...');
@endphp
<div class="flex flex-col gap-1 relative" x-data="tmMultiSelect({ options: @js($options), initial: @js($selected), placeholder: @js($placeholder) })" @click.outside="open = false">
    @if ($label)
        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ $label }}</label>
    @endif
    <input type="hidden" x-ref="model" {{ $attributes }}>
    <button type="button" @click="open = !open"
        class="w-full px-2 py-1.5 border border-field-border rounded text-xs bg-white flex items-center justify-between cursor-pointer text-left gap-2">
        <span x-text="summary" class="truncate text-[#374151]"></span>
        <span class="text-muted shrink-0">▼</span>
    </button>
    <div x-show="open" x-cloak x-transition.origin.top
        class="absolute z-30 top-full left-0 mt-1 w-full bg-white border border-field-border rounded-lg shadow-lg overflow-hidden text-xs max-h-[180px] overflow-y-auto">
        <template x-for="opt in options" :key="opt.value">
            <label class="flex items-center justify-between px-2.5 py-1.5 hover:bg-field-soft cursor-pointer text-[#374151]">
                <span x-text="opt.label"></span>
                <input type="checkbox" class="accent-accent cursor-pointer" :checked="isChecked(opt.value)" @change="toggleOption(opt.value)">
            </label>
        </template>
    </div>
</div>
