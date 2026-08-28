{{--
    Componentization Form (fase 2) -- 1 tombol di dalam <x-form.toggle-group>.
    wire:click/disabled/style diteruskan lewat $attributes, persis pola manual
    yang sudah ada (mis. wire:click="setProspect(true)" @if(...) disabled @endif).
--}}
@props(['active' => false, 'legacy' => false])
@php
    $class = $legacy
        ? 'btn xs '.($active ? 'p' : 'g')
        : ($active
            ? 'flex-1 px-3 py-1.5 rounded text-xs font-semibold cursor-pointer bg-[#2c3e69] text-white border border-[#2c3e69]'
            : 'flex-1 px-3 py-1.5 rounded text-xs font-semibold cursor-pointer bg-white text-[#556] border border-[#d7dbe2]');
@endphp
<button type="button" {{ $attributes->merge(['class' => $class]) }}>{{ $slot }}</button>
