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
@props(['label' => null, 'name' => null, 'hint' => null, 'error' => null, 'legacy' => false, 'col2' => false, 'col3' => false, 'col4' => false, 'autogrow' => false, 'caseToggle' => true, 'caseMode' => 'none'])
@php
    $isLegacy = (bool) $legacy;
    $legacyClass = is_string($legacy) ? $legacy : 'f';
    $errorMsg = $error ?? ($name ? $errors->first($name) : null);
    $colClass = $col4 ? 'col4' : ($col3 ? 'col3' : ($col2 ? 'col2' : ''));
    $wrapperClass = $isLegacy ? trim($legacyClass.' '.$colClass) : 'flex flex-col gap-1';
    $labelClass = $isLegacy ? '' : 'text-[10px] text-muted uppercase font-semibold block mb-1';
    // Case toggle & token warna: alasan lengkap di input.blade.php (keputusan
    // user 2026-09-21). Di textarea badge ditaruh di pojok kanan ATAS, bukan
    // tengah, supaya tidak menutupi teks saat kotaknya memanjang.
    // ->get() bukan ->has(), alasan lengkap di input.blade.php.
    $locked = (bool) $attributes->get('readonly', false) || (bool) $attributes->get('disabled', false);
    $withCase = $caseToggle && ! $locked;
    $textareaClass = ($isLegacy ? '' : 'w-full px-2 py-1.5 border border-field-border rounded text-xs focus:border-field-focus disabled:bg-field-soft disabled:text-muted disabled:cursor-not-allowed').($withCase ? ' pr-8' : '');
@endphp
<div class="{{ $wrapperClass }}">
    @if ($label)
        <label class="{{ $labelClass }}">{{ $label }}</label>
    @endif
    {{-- 1 x-on:input saja: transform huruf + auto-height digabung dalam satu
         handler (dua attribute x-on:input di elemen yang sama, yang kedua
         diabaikan browser). --}}
    @php
        $grow = "\$el.style.height = 'auto'; \$el.style.height = \$el.scrollHeight + 'px'";
        $onInput = trim(($withCase ? 'onInput($event); ' : '').($autogrow ? $grow : ''));
    @endphp
    <div @class(['relative' => $withCase]) x-data="{{ $withCase ? 'tmCaseInput({ mode: '.\Illuminate\Support\Js::from($caseMode).' })' : '' }}">
    @if ($autogrow)
        <textarea rows="1" style="resize:none;overflow:hidden;min-height:34px"
            @if ($withCase) x-ref="el" @endif x-init="{{ $grow }}" x-on:input="{{ $onInput }}"
            {{ $attributes->merge(['class' => $textareaClass]) }}></textarea>
    @else
        <textarea @if ($withCase) x-ref="el" x-on:input="{{ $onInput }}" @endif {{ $attributes->merge(['class' => $textareaClass]) }}></textarea>
    @endif
    @if ($withCase)
        <button type="button" x-on:click="cycle()" tabindex="-1" title="Ganti mode huruf (kecil / Kapital Tiap Kata / BESAR)"
            class="absolute right-1.5 top-1.5 text-[10px] font-bold bg-field-badge border border-field-border rounded px-1.5 py-0.5 cursor-pointer text-navy leading-none"
            x-text="labels[modes[idx]]"></button>
    @endif
    </div>
    @if ($hint)
        <div class="{{ $isLegacy ? 'hint' : 'text-[10.5px] text-muted' }}">{{ $hint }}</div>
    @endif
    @if ($errorMsg)
        <span class="{{ $isLegacy ? 'hint' : 'text-[10.5px] text-red-600' }}" @if ($isLegacy) style="color:#e55353" @endif>{{ $errorMsg }}</span>
    @endif
</div>
