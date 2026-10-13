<?php

/*
 * REMARK
 * Tujuan       : Kotak berjudul (fieldset + legend) untuk mengelompokkan field yang bisa dikunci bersama (mis. blok revisi spesifikasi).
 * Sumber hulu  : -
 * Input        : legend (English, diterjemahkan); atribut disabled / data-* diteruskan; slot = field.
 * Output       : <fieldset class="dl-block"><legend>..</legend> slot </fieldset>; kelas is-on dipasang JS (revise-form.js) saat blok aktif.
 * Dipakai oleh : screens/inquiry-revise (blok JOIN / ARTWORK / DIMENSION / MATERIAL / COLOR / PROCESS).
 * Status       : Aktif.
 */

namespace Devlib\Components;

class Block extends DevlibComponent
{
    public function __construct(public string $legend = '') {}

    public function tag(): string
    {
        return 'x-dl::block';
    }

    public function render()
    {
        return <<<'BLADE'
            <fieldset {{ $attributes->class('dl-block') }} {!! $dl() !!}>
                <legend>{{ $legend }}</legend>
                {{ $slot }}
            </fieldset>
            BLADE;
    }
}
