{{--
    Componentization Form (fase 2) -- label+textarea+error. Sengaja tidak
    interpolasi isi awal ke dalam tag (biarkan kosong) -- persis pola yang
    sudah dipakai di seluruh codebase sekarang (mis. customer.blade.php:1035),
    Livewire yang mengisi nilai awal via wire:model hydration.

    :legacy=true / :legacy="'fld'" / :col2 -- sama pola dgn input.blade.php,
    lihat catatan lengkap di sana.
--}}
@props(['label' => null, 'name' => null, 'hint' => null, 'error' => null, 'legacy' => false, 'col2' => false])
@php
    $isLegacy = (bool) $legacy;
    $legacyClass = is_string($legacy) ? $legacy : 'f';
    $errorMsg = $error ?? ($name ? $errors->first($name) : null);
    $wrapperClass = $isLegacy ? trim($legacyClass.' '.($col2 ? 'col2' : '')) : 'flex flex-col gap-1';
    $labelClass = $isLegacy ? '' : 'text-[10px] text-muted uppercase font-semibold block mb-1';
    $textareaClass = $isLegacy ? '' : 'w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f4f6fa] disabled:text-muted disabled:cursor-not-allowed';
@endphp
<div class="{{ $wrapperClass }}">
    @if ($label)
        <label class="{{ $labelClass }}">{{ $label }}</label>
    @endif
    <textarea {{ $attributes->merge(['class' => $textareaClass]) }}></textarea>
    @if ($hint)
        <div class="{{ $isLegacy ? 'hint' : 'text-[10.5px] text-muted' }}">{{ $hint }}</div>
    @endif
    @if ($errorMsg)
        <span class="{{ $isLegacy ? 'hint' : 'text-[10.5px] text-red-600' }}" @if ($isLegacy) style="color:#e55353" @endif>{{ $errorMsg }}</span>
    @endif
</div>
