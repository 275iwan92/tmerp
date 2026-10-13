<?php

/*
 * REMARK
 * Tujuan       : Baris chip / badge yang membungkus ke baris berikutnya (tag proses khusus, label jenis, status kecil).
 * Sumber hulu  : -
 * Input        : slot berisi x-dl::badge atau teks kecil; tag = div (bawaan) | span (inline di dalam kalimat).
 * Output       : <div|span class="dl-chips"> flex-wrap, jarak 6px, rata tengah </div>; gaya .dl-chips di devlib.css.
 * Dipakai oleh : screens/inquiry-recalc, inquiry-revise (badge jenis + proses khusus).
 * Status       : Aktif.
 */

namespace Devlib\Components;

class Chips extends DevlibComponent
{
    public function __construct(public string $as = 'div') {}

    public function tag(): string
    {
        return 'x-dl::chips';
    }

    public function render()
    {
        return <<<'BLADE'
            <{{ $as === 'span' ? 'span' : 'div' }} {{ $attributes->class('dl-chips') }} {!! $dl() !!}>{{ $slot }}</{{ $as === 'span' ? 'span' : 'div' }}>
            BLADE;
    }
}
