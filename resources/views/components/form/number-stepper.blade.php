{{--
    Component library (docs/mockups/UIUX_components_part1.html) -- input
    integer + tombol spin atas/bawah. Pola hidden-input+dispatch event sama
    dgn x-form.date-picker (lihat docblock di sana) supaya wire:model apapun
    path-nya ikut sinkron -- Alpine murni UX, tak tahu-menahu soal Livewire.
--}}
@props(['label' => null, 'name' => null, 'min' => null, 'max' => null, 'value' => 0])
<div class="flex flex-col gap-1" x-data="tmNumberStepper({ initial: {{ (int) $value }}, min: @js($min), max: @js($max) })">
    @if ($label)
        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ $label }}</label>
    @endif
    <input type="hidden" x-ref="model" {{ $attributes }}>
    <div class="relative flex items-center">
        <input type="text" inputmode="numeric" x-bind:value="value" @input="onType($event.target.value)"
            class="w-full px-2 py-1.5 pr-6 border border-field-border rounded text-xs focus:border-field-focus">
        <div class="absolute right-1 flex flex-col h-[calc(100%-6px)] w-4">
            <button type="button" @click="step(1)" tabindex="-1"
                class="flex-1 bg-field-soft border border-field-border text-[8px] text-[#374151] cursor-pointer leading-none">▲</button>
            <button type="button" @click="step(-1)" tabindex="-1"
                class="flex-1 bg-field-soft border border-field-border border-t-0 text-[8px] text-[#374151] cursor-pointer leading-none">▼</button>
        </div>
    </div>
</div>
