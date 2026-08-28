{{--
    Componentization Form (fase 2) -- label+input+error, dialek "transaction
    entry" dijadikan kanonik (paling banyak dipakai verbatim di codebase,
    lihat plan). Component TIDAK tahu mode binding: wire:model, wire:change,
    atau apa saja diteruskan apa adanya lewat $attributes ke <input> --
    pemanggil yang pilih, bukan component ini.

    :legacy=true / :legacy="'fld'" (§keputusan 3 fase Form, digeneralisasi
    2026-08-27 saat scope diperluas ke "semua form disamakan") -- emit markup
    dialek CSS GLOBAL (bukan Tailwind) apa adanya: label & input polos tanpa
    class di dalam wrapper <div class="{legacy}">, biar CSS descendant selector
    yang sudah ada di app.css (.f / .secpage) yang mendandani. :legacy=true
    (boolean) -> wrapper class "f" (dialek inquiry/form, recalc, revise).
    :legacy="'fld'" (string) -> wrapper class custom, mis. "fld" (dialek
    .secpage/security/index.blade.php). TIDAK migrasi CSS ke dialek Tailwind,
    cuma hilangkan pengulangan struktur <div class="..."><label>...<input>.
    :col2 menambahkan modifier "col2" ke wrapper (khusus dialek "f") -- sama
    pola dgn toggle-group.blade.php.
--}}
@props(['label' => null, 'name' => null, 'type' => 'text', 'hint' => null, 'error' => null, 'legacy' => false, 'col2' => false])
@php
    $isLegacy = (bool) $legacy;
    $legacyClass = is_string($legacy) ? $legacy : 'f';
    $errorMsg = $error ?? ($name ? $errors->first($name) : null);
    $wrapperClass = $isLegacy ? trim($legacyClass.' '.($col2 ? 'col2' : '')) : 'flex flex-col gap-1';
    $labelClass = $isLegacy ? '' : 'text-[10px] text-muted uppercase font-semibold block mb-1';
    $inputClass = $isLegacy ? '' : 'w-full px-2 py-1.5 border border-[#c7cfda] rounded text-xs focus:border-accent disabled:bg-[#f4f6fa] disabled:text-muted disabled:cursor-not-allowed read-only:bg-[#f4f6fa] read-only:text-muted';
@endphp
<div class="{{ $wrapperClass }}">
    @if ($label)
        <label class="{{ $labelClass }}">{{ $label }}</label>
    @endif
    <input type="{{ $type }}" {{ $attributes->merge(['class' => $inputClass]) }}>
    @if ($hint)
        <div class="{{ $isLegacy ? 'hint' : 'text-[10.5px] text-muted' }}">{{ $hint }}</div>
    @endif
    @if ($errorMsg)
        <span class="{{ $isLegacy ? 'hint' : 'text-[10.5px] text-red-600' }}" @if ($isLegacy) style="color:#e55353" @endif>{{ $errorMsg }}</span>
    @endif
</div>
