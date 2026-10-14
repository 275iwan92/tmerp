<?php

/*
 * REMARK
 * Tujuan       : Teks petunjuk satu paragraf (penjelasan kecil di bawah judul / field).
 * Sumber hulu  : -
 * Input        : slot = teks (English, tulis lewat @t / t()); note = variasi rapat 11.5px tanpa margin atas (bekas .mp-hint); as = p (bawaan) | span; italic = huruf miring (contoh format).
 * Output       : <p class="dl-hint [dl-hint--note]"> teks </p>; gaya di devlib.css, warna token --dl-muted (gelap).
 * Dipakai oleh : screens/inquiry-*, master-product, master-card-collection, maintenance-profile, maintenance-documents.
 * Status       : Aktif.
 */

namespace Devlib\Components;

class Hint extends DevlibComponent
{
    public function __construct(public bool $note = false, public string $as = 'p', public bool $italic = false) {}

    public function tag(): string
    {
        return 'x-dl::hint';
    }

    public function render()
    {
        return <<<'BLADE'
            <{{ $as === 'span' ? 'span' : 'p' }} {{ $attributes->class(['dl-hint', 'dl-hint--note' => $note, 'dl-hint--italic' => $italic]) }} {!! $dl() !!}>{{ $slot }}</{{ $as === 'span' ? 'span' : 'p' }}>
            BLADE;
    }
}
