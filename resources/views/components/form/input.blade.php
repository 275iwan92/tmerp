{{--
    Componentization Form (fase 2) -- label+input+error, dialek "transaction
    entry" dijadikan kanonik (paling banyak dipakai verbatim di codebase,
    lihat plan). Component TIDAK tahu mode binding: wire:model, wire:change,
    atau apa saja diteruskan apa adanya lewat $attributes ke <input> --
    pemanggil yang pilih, bukan component ini.

    CASE TOGGLE (aa -> Aa -> AA), keputusan user 2026-09-21 -- WAJIB, bukan
    opsional: setiap input teks string di seluruh aplikasi memakai badge ini
    supaya user yang mengetik lewat HP/tablet tidak perlu menekan Shift tiap
    kata. Otomatis nyala utk type=text dan mati sendiri utk type lain
    (email/number/password/date/dst), readonly, dan disabled. :case-toggle=false
    hanya utk kasus yang sungguh-sungguh tak boleh diubah hurufnya -- sebutkan
    alasannya di tempat pemanggilan. Logikanya di resources/js/case-input.js
    (Alpine tmCaseInput), sengaja memakai elemen input ASLI supaya wire:model
    dan handler Alpine milik pemanggil tetap menempel di elemen yang benar.

    WARNA lewat token --color-field-* (app.css @theme, dicampur dari
    --color-accent) supaya ikut corak tema yang dipilih user di sys_theme --
    JANGAN tulis hex abu-abu literal lagi di sini.

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
@props(['label' => null, 'name' => null, 'type' => 'text', 'hint' => null, 'error' => null, 'legacy' => false, 'col2' => false, 'col3' => false, 'col4' => false, 'caseToggle' => true, 'caseMode' => 'none'])
@php
    $isLegacy = (bool) $legacy;
    $legacyClass = is_string($legacy) ? $legacy : 'f';
    $errorMsg = $error ?? ($name ? $errors->first($name) : null);
    $colClass = $col4 ? 'col4' : ($col3 ? 'col3' : ($col2 ? 'col2' : ''));
    $wrapperClass = $isLegacy ? trim($legacyClass.' '.$colClass) : 'flex flex-col gap-1';
    $labelClass = $isLegacy ? '' : 'text-[10px] text-muted uppercase font-semibold block mb-1';
    // Nilai yang tidak bisa diketik user tidak perlu (dan tidak boleh) punya
    // tombol ganti huruf. WAJIB ->get() (nilai), BUKAN ->has() (keberadaan
    // key): pemanggil yang menulis :disabled="$readOnly" selalu mengirim key
    // "disabled" walau isinya false, jadi ->has() bikin badge mati di SEMUA
    // field yang pakai pola itu (bug sama spt [[feedback_datepicker_disabled_attribute_bug]],
    // ditemukan lagi 2026-09-22 saat rollout Inquiry).
    $locked = (bool) $attributes->get('readonly', false) || (bool) $attributes->get('disabled', false);
    $withCase = $caseToggle && $type === 'text' && ! $locked;
    $inputClass = $isLegacy
        ? ($withCase ? 'pr-8' : '')
        : 'w-full px-2 py-1.5 border border-field-border rounded text-xs focus:border-field-focus disabled:bg-field-soft disabled:text-muted disabled:cursor-not-allowed read-only:bg-field-soft read-only:text-muted'.($withCase ? ' pr-8' : '');
@endphp
<div class="{{ $wrapperClass }}">
    @if ($label)
        <label class="{{ $labelClass }}">{{ $label }}</label>
    @endif
    @if ($withCase)
        <div class="relative flex items-center" x-data="tmCaseInput({ mode: @js($caseMode) })">
            <input type="{{ $type }}" x-ref="el" x-on:input="onInput($event)" {{ $attributes->merge(['class' => $inputClass]) }}>
            <button type="button" x-on:click="cycle()" tabindex="-1" title="Ganti mode huruf (kecil / Kapital Tiap Kata / BESAR)"
                class="absolute right-1.5 text-[10px] font-bold bg-field-badge border border-field-border rounded px-1.5 py-0.5 cursor-pointer text-navy leading-none"
                x-text="labels[modes[idx]]"></button>
        </div>
    @else
        <input type="{{ $type }}" {{ $attributes->merge(['class' => $inputClass]) }}>
    @endif
    @if ($hint)
        <div class="{{ $isLegacy ? 'hint' : 'text-[10.5px] text-muted' }}">{{ $hint }}</div>
    @endif
    @if ($errorMsg)
        <span class="{{ $isLegacy ? 'hint' : 'text-[10.5px] text-red-600' }}" @if ($isLegacy) style="color:#e55353" @endif>{{ $errorMsg }}</span>
    @endif
</div>
