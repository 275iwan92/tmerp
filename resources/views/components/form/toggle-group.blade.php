{{--
    Componentization Form (fase 2) -- wrapper tombol dua/tiga-pilihan
    (workaround resmi utk bug <select> Livewire v4 full-page-morph, sudah
    didokumentasikan di inquiry/form.blade.php:283-285). Isi slot dengan
    <x-form.toggle-option> per pilihan. :legacy=true dipakai layar yang
    masih pakai dialek CSS global app.css (.f/.hint) -- lihat plan §keputusan 3,
    supaya tampilannya TETAP SAMA (transparan), bukan pindah ke dialek Tailwind.
--}}
@props(['label' => null, 'hint' => null, 'legacy' => false, 'col2' => false])
@php
    $isLegacy = (bool) $legacy;
    $legacyClass = is_string($legacy) ? $legacy : 'f';
@endphp
<div class="{{ $isLegacy ? trim($legacyClass.' '.($col2 ? 'col2' : '')) : 'flex flex-col gap-1' }}">
    @if ($label)
        <label class="{{ $isLegacy ? '' : 'text-[10px] text-muted uppercase font-semibold block mb-1' }}">{{ $label }}</label>
    @endif
    <div class="flex gap-1.5">
        {{ $slot }}
    </div>
    @if ($hint)
        <div class="{{ $isLegacy ? 'hint' : 'text-[10.5px] text-muted' }}">{{ $hint }}</div>
    @endif
</div>
