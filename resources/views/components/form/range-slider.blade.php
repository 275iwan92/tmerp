{{--
    Component library (docs/mockups/UIUX_components_part1.html) -- range
    slider + display nilai live. :format="'currency'" pakai format Rupiah
    id-ID, selain itu tampil angka polos + :unit opsional (mis. "%").
    $attributes (wire:model) ke <input type=range> native.
--}}
@props(['label' => null, 'min' => 0, 'max' => 100, 'step' => 1, 'value' => 0, 'format' => null, 'unit' => ''])
<div class="flex flex-col gap-1" x-data="{ val: {{ (int) $value }} }">
    @if ($label)
        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ $label }}</label>
    @endif
    <input type="range" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" value="{{ $value }}"
        @input="val = parseInt($event.target.value, 10)"
        {{ $attributes->merge(['class' => 'w-full accent-accent cursor-pointer']) }}>
    <span class="text-[11px] font-semibold text-accent"
        x-text="{{ $format === 'currency' ? "'Rp ' + val.toLocaleString('id-ID')" : "val.toLocaleString('id-ID') + ' ' + @js($unit)" }}"></span>
</div>
