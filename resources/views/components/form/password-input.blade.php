{{--
    Component library (docs/mockups/UIUX_components_part1.html) -- password
    field + tombol mata show/hide. $attributes (wire:model dst.) diteruskan
    ke <input>, sama kontrak dgn x-form.input.
--}}
@props(['label' => null, 'name' => null, 'hint' => null, 'error' => null])
@php
    $errorMsg = $error ?? ($name ? $errors->first($name) : null);
@endphp
<div class="flex flex-col gap-1" x-data="{ show: false }">
    @if ($label)
        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ $label }}</label>
    @endif
    <div class="relative flex items-center">
        <input :type="show ? 'text' : 'password'"
            {{ $attributes->merge(['class' => 'w-full px-2 py-1.5 pr-8 border border-field-border rounded text-xs focus:border-field-focus']) }}>
        <button type="button" @click="show = !show" tabindex="-1"
            class="absolute right-2 text-xs text-muted cursor-pointer select-none" x-text="show ? '🙈' : '👁️'"></button>
    </div>
    @if ($hint)<div class="text-[10.5px] text-muted">{{ $hint }}</div>@endif
    @if ($errorMsg)<span class="text-[10.5px] text-red-600">{{ $errorMsg }}</span>@endif
</div>
