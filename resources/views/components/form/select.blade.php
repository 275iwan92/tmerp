{{--
    Componentization Form (fase 2) -- label+select+error. :options = array
    value=>label. :selected = nilai sekarang (dipakai buat @selected loop
    internal). $attributes (wire:model ATAU wire:change) diteruskan apa
    adanya ke <select> -- lihat catatan sama di input.blade.php soal kenapa
    component ini tidak perlu tahu mode binding.

    :legacy=true / :legacy="'fld'" / :col2 -- sama pola dgn input.blade.php,
    lihat catatan lengkap di sana (digeneralisasi 2026-08-27 supaya bisa
    dipakai lintas dialek CSS global, bukan cuma dialek "f").

    :placeholder="false" (BUKAN null!) utk suppress opsi placeholder -- Blade
    @props() anonymous component MEMBUANG prop yang nilainya eksplisit null
    (fallback diam-diam ke default, gara-gara isset() dipakai di internal
    Illuminate\View\ComponentAttributeBag/Arr::get -- isset() balikin false
    utk null). :placeholder="null" KELIHATAN benar tapi diam-diam TIDAK
    pernah mensuppress opsi "— pilih —" -- insiden nyata ditemukan 2026-08-27,
    41 titik pemanggilan di 15 file salah semua sebelum fix ini.
--}}
@props(['label' => null, 'name' => null, 'options' => [], 'selected' => null, 'placeholder' => '— pilih —', 'error' => null, 'legacy' => false, 'col2' => false])
@php
    $isLegacy = (bool) $legacy;
    $legacyClass = is_string($legacy) ? $legacy : 'f';
    $errorMsg = $error ?? ($name ? $errors->first($name) : null);
    $wrapperClass = $isLegacy ? trim($legacyClass.' '.($col2 ? 'col2' : '')) : 'flex flex-col gap-1';
    $labelClass = $isLegacy ? '' : 'text-[10px] text-muted uppercase font-semibold block mb-1';
    $selectClass = $isLegacy ? '' : 'w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f4f6fa] disabled:text-muted disabled:cursor-not-allowed';
@endphp
<div class="{{ $wrapperClass }}">
    @if ($label)
        <label class="{{ $labelClass }}">{{ $label }}</label>
    @endif
    <select {{ $attributes->merge(['class' => $selectClass]) }}>
        @if ($placeholder !== false && $placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $optLabel)
            <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $optLabel }}</option>
        @endforeach
    </select>
    @if ($errorMsg)
        <span class="{{ $isLegacy ? 'hint' : 'text-[10.5px] text-red-600' }}" @if ($isLegacy) style="color:#e55353" @endif>{{ $errorMsg }}</span>
    @endif
</div>
