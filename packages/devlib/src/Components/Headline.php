<?php

/*
 * REMARK
 * Tujuan       : Judul besar nama dokumen / produk di dalam isi form (22px tebal), dengan sub-teks kecil opsional.
 * Sumber hulu  : Nama produk / dokumen dari layar (mis. $doc['name']).
 * Input        : slot = nama (teks jadi; data dibungkus raw() oleh pemanggil bila lewat prop).
 * Output       : <h2 class="dl-headline"> nama </h2>; gaya di devlib.css.
 * Dipakai oleh : screens/inquiry-recalc, inquiry-revise.
 * Status       : Aktif.
 */

namespace Devlib\Components;

class Headline extends DevlibComponent
{
    public function tag(): string
    {
        return 'x-dl::headline';
    }

    public function render()
    {
        return <<<'BLADE'
            <h2 {{ $attributes->class('dl-headline') }} {!! $dl() !!}>{{ $slot }}</h2>
            BLADE;
    }
}
