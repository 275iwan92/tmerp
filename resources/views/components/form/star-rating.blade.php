{{--
    Component library (docs/mockups/UIUX_components_part1.html) -- bintang
    1..max, klik pilih + hover preview. Hidden input (pola sama x-form.date-picker)
    utk wire:model.
--}}
@props(['label' => null, 'name' => null, 'max' => 5, 'value' => 0])
<div class="flex flex-col gap-1" x-data="tmStarRating({ max: {{ (int) $max }}, initial: {{ (int) $value }} })">
    @if ($label)
        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ $label }}</label>
    @endif
    <input type="hidden" x-ref="model" {{ $attributes }}>
    <div class="flex items-center gap-1 h-7">
        <template x-for="n in max" :key="n">
            <span class="text-lg cursor-pointer select-none"
                x-text="n <= (hover || value) ? '★' : '☆'"
                :class="n <= (hover || value) ? 'text-[#f59e0b]' : 'text-[#d1d5db]'"
                @click="pick(n)" @mouseenter="hover = n" @mouseleave="hover = 0"></span>
        </template>
        <span class="text-[11px] font-semibold text-muted ml-1" x-text="value + ' / ' + max"></span>
    </div>
</div>
