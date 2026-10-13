<?php

/*
 * REMARK
 * Tujuan       : Dua (atau lebih) blok berdampingan yang otomatis bertumpuk di layar sempit (grid auto-fit 320px, jarak 12px).
 * Sumber hulu  : -
 * Input        : slot berisi blok-blok (kartu, gambar, field).
 * Output       : <div class="dl-cols"> ... </div>; gaya .dl-cols di devlib.css.
 * Dipakai oleh : screens/inquiry-recalc, inquiry-revise, customer-order, master-card-collection.
 * Status       : Aktif.
 */

namespace Devlib\Components;

class Cols extends DevlibComponent
{
    public function tag(): string
    {
        return 'x-dl::cols';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-cols') }} {!! $dl() !!}>{{ $slot }}</div>
            BLADE;
    }
}
