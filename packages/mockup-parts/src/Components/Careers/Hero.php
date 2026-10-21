<?php

/*
 * REMARK
 * Tujuan       : Pita judul halaman depan portal karir: judul besar dan satu kalimat pengantar, rata tengah, lebar penuh.
 * Sumber hulu  : Token --dl-*.
 * Input        : title, sub.
 * Output       : <section class="mp-careers__hero"> dengan <h1> dan <p>.
 * Dipakai oleh : resources/views/careers/landing.blade.php.
 * Status       : Aktif (T10 tahap 6b, 2026-10-21).
 */

namespace MockupParts\Components\Careers;

use Devlib\Components\DevlibComponent;

class Hero extends DevlibComponent
{
    protected const TEXT_PROPS = [];

    protected const TEXT_LISTS = [];

    public function __construct(public string $title = '', public string $sub = '') {}

    public function tag(): string
    {
        return 'x-mp::careers.hero';
    }

    public function source(): string
    {
        return 'teks pengantar portal karir';
    }

    public function render()
    {
        return <<<'BLADE'
            <section {{ $attributes->class('mp-careers__hero') }} {!! $dl() !!}>
                <div class="mp-careers__hero-in"><h1>{{ $title }}</h1>@if ($sub !== '')<p>{{ $sub }}</p>@endif</div>
            </section>
            BLADE;
    }
}
