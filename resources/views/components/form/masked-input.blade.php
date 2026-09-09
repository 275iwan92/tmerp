{{--
    Component library (docs/mockups/UIUX_components_part1.html) -- input
    angka dikelompokkan segmen+separator generik (NIK 6.6.4, dst). Hidden
    input (pola sama x-form.date-picker) menampung digit MENTAH (tanpa
    separator) utk wire:model -- tampilan berformat cuma di input teks.
--}}
@props(['label' => null, 'name' => null, 'segments' => [6, 6, 4], 'separator' => '.', 'hint' => null, 'error' => null, 'value' => ''])
@php $errorMsg = $error ?? ($name ? $errors->first($name) : null); @endphp
<div class="flex flex-col gap-1" x-data="tmMaskedInput({ segments: @js($segments), separator: @js($separator), initial: @js($value) })">
    @if ($label)
        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ $label }}</label>
    @endif
    <input type="hidden" x-ref="model" {{ $attributes }}>
    <input type="text" inputmode="numeric" x-bind:value="display" @input="onInput($event)"
        class="w-full px-2 py-1.5 border border-[#c7cfda] rounded text-xs focus:border-accent">
    @if ($hint)<div class="text-[10.5px] text-muted">{{ $hint }}</div>@endif
    @if ($errorMsg)<span class="text-[10.5px] text-red-600">{{ $errorMsg }}</span>@endif
</div>
