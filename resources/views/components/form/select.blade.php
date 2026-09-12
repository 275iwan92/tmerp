{{--
    Componentization Form (fase 2) -- label+select+error. :options = array
    value=>label. :selected = nilai sekarang (dipakai buat @selected loop
    internal). $attributes (wire:model ATAU wire:change) diteruskan apa
    adanya ke <select> -- lihat catatan sama di input.blade.php soal kenapa
    component ini tidak perlu tahu mode binding.

    :legacy=true / :legacy="'fld'" / :col2 -- sama pola dgn input.blade.php,
    lihat catatan lengkap di sana (digeneralisasi 2026-08-27 supaya bisa
    dipakai lintas dialek CSS global, bukan cuma dialek "f").

    :liveSync (2026-09-13, audit componentization HRM) -- opt-in fix utk bug
    nyata [[feedback_livewire_select_morph_bug]]: <select wire:model.live="x">
    dgn >3 opsi bisa nyangkut nilai visual lama walau HTML server sudah benar
    (Livewire v4 full-page morph tidak selalu set ulang IDL .value). Dulu
    fix-nya manual inline x-data/x-effect per pemanggil (lihat
    inquiry/⚡form.blade.php field "Sumber") -- sekarang jadi 1 prop reusable,
    pasang x-data x-effect="$el.value = (string) @js($selected)" yang paksa
    sync ulang tiap re-render. HANYA pasang kalau select ini (atau sibling property
    `.live` lain) bisa memicu re-render select ini; default false (tanpa
    x-data sama sekali) supaya select statis yang sudah ada 0 perubahan
    perilaku. Untuk select KECIL (2-3 opsi) pola yang benar tetap
    <x-form.toggle-group>, BUKAN prop ini.

    :placeholder="false" (BUKAN null!) utk suppress opsi placeholder -- Blade
    @props() anonymous component MEMBUANG prop yang nilainya eksplisit null
    (fallback diam-diam ke default, gara-gara isset() dipakai di internal
    Illuminate\View\ComponentAttributeBag/Arr::get -- isset() balikin false
    utk null). :placeholder="null" KELIHATAN benar tapi diam-diam TIDAK
    pernah mensuppress opsi "— pilih —" -- insiden nyata ditemukan 2026-08-27,
    41 titik pemanggilan di 15 file salah semua sebelum fix ini. (Default
    prop sekarang null lagi sejak i18n Malam 1, tapi diresolve balik ke
    string ter-translate via null-coalescing ke Translator::t(...) di blok
    PHP bawah -- perilaku efektifnya sama, gotcha di atas tetap berlaku.
    (Catatan: JANGAN tulis literal "at-php" dlm komentar ini, lihat memory
    feedback_blade_inline_php_no_closing_tag_gotcha -- itu yang barusan
    merusak kompilasi file ini sebelum diperbaiki.))
--}}
@props(['label' => null, 'name' => null, 'options' => [], 'selected' => null, 'placeholder' => null, 'error' => null, 'legacy' => false, 'col2' => false, 'liveSync' => false])
@php
    $placeholder = $placeholder ?? \App\Support\Localization\Translator::t('shared_select_placeholder', '— pilih —');
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
    <select
        @if ($liveSync) x-data x-effect="$el.value = @js((string) $selected)" @endif
        {{ $attributes->merge(['class' => $selectClass]) }}>
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
