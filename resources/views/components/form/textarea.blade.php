{{--
    Componentization Form (fase 2) -- label+textarea+error. Sengaja tidak
    interpolasi isi awal ke dalam tag (biarkan kosong) -- persis pola yang
    sudah dipakai di seluruh codebase sekarang (mis. customer.blade.php:1035),
    Livewire yang mengisi nilai awal via wire:model hydration.

    :legacy=true / :legacy="'fld'" / :col2 -- sama pola dgn input.blade.php,
    lihat catatan lengkap di sana.

    :autogrow -- mulai 1 baris (rows=1), tinggi nambah otomatis mengikuti isi
    (Alpine murni, tanpa library) alih-alih box tetap 2+ baris + resize handle
    manual (permintaan user 2026-09-04: "cukup 1 baris... yg diketik melebihi
    kotak maka box inputan akan melebar kebawah"). resize:none + overflow:hidden
    supaya tidak ada scrollbar/drag-handle yang bentrok sama auto-resize.
--}}
@props(['label' => null, 'name' => null, 'hint' => null, 'error' => null, 'legacy' => false, 'col2' => false, 'col3' => false, 'col4' => false, 'autogrow' => false])
@php
    $isLegacy = (bool) $legacy;
    $legacyClass = is_string($legacy) ? $legacy : 'f';
    $errorMsg = $error ?? ($name ? $errors->first($name) : null);
    $colClass = $col4 ? 'col4' : ($col3 ? 'col3' : ($col2 ? 'col2' : ''));
    $wrapperClass = $isLegacy ? trim($legacyClass.' '.$colClass) : 'flex flex-col gap-1';
    $labelClass = $isLegacy ? '' : 'text-[10px] text-muted uppercase font-semibold block mb-1';
    $textareaClass = $isLegacy ? '' : 'w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f4f6fa] disabled:text-muted disabled:cursor-not-allowed';
@endphp
<div class="{{ $wrapperClass }}">
    @if ($label)
        <label class="{{ $labelClass }}">{{ $label }}</label>
    @endif
    @if ($autogrow)
        <textarea rows="1" style="resize:none;overflow:hidden;min-height:34px"
            x-data x-init="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
            x-on:input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
            {{ $attributes->merge(['class' => $textareaClass]) }}></textarea>
    @else
        <textarea {{ $attributes->merge(['class' => $textareaClass]) }}></textarea>
    @endif
    @if ($hint)
        <div class="{{ $isLegacy ? 'hint' : 'text-[10.5px] text-muted' }}">{{ $hint }}</div>
    @endif
    @if ($errorMsg)
        <span class="{{ $isLegacy ? 'hint' : 'text-[10.5px] text-red-600' }}" @if ($isLegacy) style="color:#e55353" @endif>{{ $errorMsg }}</span>
    @endif
</div>
