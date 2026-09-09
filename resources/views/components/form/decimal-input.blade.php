{{--
    Component library (docs/mockups/UIUX_components_part1.html, item #14
    "Berat Badan") -- angka desimal, auto-format ke N desimal begitu blur
    (tooltip mockup: "Auto .00 on blur" -- source aslinya cuma nyebut di
    tooltip tanpa kode wiring, diimplementasikan sungguhan di sini) + unit
    suffix (kg, dst). $attributes (wire:model) ke <input> native langsung
    (bukan hidden-input trick -- @blur murni kosmetik format ulang,
    beda dari komponen lain yg REBUILD value dari nol tiap keystroke).
--}}
@props(['label' => null, 'name' => null, 'unit' => null, 'decimals' => 2, 'value' => '', 'hint' => null])
<div class="flex flex-col gap-1">
    @if ($label)
        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ $label }}</label>
    @endif
    <div class="relative flex items-center">
        <input type="text" inputmode="decimal" value="{{ $value }}" placeholder="0.{{ str_repeat('0', (int) $decimals) }}"
            @blur="$event.target.value = $event.target.value.trim() === '' ? '' : parseFloat($event.target.value.replace(',', '.') || 0).toFixed({{ (int) $decimals }})"
            {{ $attributes->merge(['class' => 'w-full px-2 py-1.5 border border-[#c7cfda] rounded text-xs focus:border-accent'.($unit ? ' pr-8' : '')]) }}>
        @if ($unit)<span class="absolute right-2.5 text-[11px] font-semibold text-muted pointer-events-none">{{ $unit }}</span>@endif
    </div>
    @if ($hint)<div class="text-[10.5px] text-muted">{{ $hint }}</div>@endif
</div>
