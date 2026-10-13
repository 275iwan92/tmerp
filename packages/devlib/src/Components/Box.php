<?php

/*
 * REMARK
 * Tujuan       : Wadah netral (div, p, span, article, ...) yang meneruskan semua atribut dan kelas; dipakai bila layar butuh pembungkus
 *                tanpa menulis tag HTML mentah (hook data-*, id cetak, kelas halaman).
 * Sumber hulu  : -
 * Input        : as (div bawaan | p | span | article | header | aside | section | small | b | strong | code); atribut / kelas / data-* diteruskan; slot = isi.
 * Output       : <as {atribut}> isi </as>; tidak menambah gaya sendiri (gaya datang dari kelas yang diteruskan).
 * Dipakai oleh : layar v2 (inquiry-new, inquiry-*, print-*, dictionary, dummy, flow, guide, home, help, partial material-alert).
 * Status       : Aktif.
 */

namespace Devlib\Components;

class Box extends DevlibComponent
{
    private const TAGS = ['div', 'p', 'span', 'article', 'header', 'aside', 'section', 'small', 'b', 'strong', 'code'];

    public function __construct(public string $as = 'div') {}

    public function tag(): string
    {
        return 'x-dl::box';
    }

    public function el(): string
    {
        return in_array($this->as, self::TAGS, true) ? $this->as : 'div';
    }

    public function render()
    {
        return <<<'BLADE'
            <{{ $el() }} {{ $attributes }}>{{ $slot }}</{{ $el() }}>
            BLADE;
    }
}
