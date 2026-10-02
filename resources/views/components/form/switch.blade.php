{{--
    Component library (docs/mockups/UIUX_components_part1.html) -- switch
    toggle utk KONTEKS FORM (label status live berubah teks, bukan aksi
    langsung spt grid/toggle-switch.blade.php yang wire:click seketika +
    wire:confirm). $attributes (wire:model/name) ke <input type=checkbox>.
--}}
@props(['label' => null, 'onLabel' => t('Active'), 'offLabel' => t('Inactive'), 'checked' => false])
<div class="flex flex-col gap-1" x-data="{ on: {{ $checked ? 'true' : 'false' }} }">
    @if ($label)
        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ $label }}</label>
    @endif
    <div class="flex items-center gap-2 h-7">
        <label class="relative inline-block w-9 h-[18px] cursor-pointer">
            <input type="checkbox" @if ($checked) checked @endif @change="on = $event.target.checked"
                {{ $attributes->merge(['class' => 'peer sr-only']) }}>
            <span class="absolute inset-0 rounded-full bg-[#d1d5db] peer-checked:bg-accent transition-colors"></span>
            <span class="absolute left-[3px] top-[3px] w-3 h-3 bg-white rounded-full transition-transform peer-checked:translate-x-[18px]"></span>
        </label>
        <span class="text-xs font-semibold text-[#374151]" x-text="on ? @js($onLabel) : @js($offLabel)"></span>
    </div>
</div>
