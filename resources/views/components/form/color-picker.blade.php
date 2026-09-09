{{--
    Component library (docs/mockups/UIUX_components_part1.html) -- native
    <input type=color> + display kode HEX di sebelahnya. $attributes
    (wire:model) ke input color langsung (native, Livewire sinkron otomatis).
--}}
@props(['label' => null, 'value' => '#2563eb'])
<div class="flex flex-col gap-1" x-data="{ hex: @js(strtoupper($value)) }">
    @if ($label)
        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ $label }}</label>
    @endif
    <div class="flex items-center gap-2 h-7">
        <input type="color" value="{{ $value }}" @input="hex = $event.target.value.toUpperCase()"
            {{ $attributes->merge(['class' => 'w-8 h-[26px] border border-[#c7cfda] rounded cursor-pointer p-0 bg-transparent']) }}>
        <span class="text-xs font-semibold font-mono text-[#374151]" x-text="hex"></span>
    </div>
</div>
