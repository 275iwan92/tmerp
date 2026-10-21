<?php

/*
 * REMARK
 * Tujuan       : Pembungkus isi satu halaman portal karir: lebar tengah baku (narrow 400, form 440, mid 760, apply 820, wide 1100) dan jarak atas-bawah.
 * Sumber hulu  : Token --dl-*.
 * Input        : size (narrow | form | mid | apply | wide); isi = slot.
 * Output       : <div class="mp-careers__page mp-careers__page--{size}">.
 * Dipakai oleh : resources/views/careers/*.blade.php.
 * Status       : Aktif (T10 tahap 6b, 2026-10-21).
 */

namespace MockupParts\Components\Careers;

use Devlib\Components\DevlibComponent;

class Page extends DevlibComponent
{
    protected const TEXT_PROPS = [];

    protected const TEXT_LISTS = [];

    public function __construct(public string $size = 'mid') {}

    public function tag(): string
    {
        return 'x-mp::careers.page size='.$this->size;
    }

    public function source(): string
    {
        return 'tata letak halaman portal karir';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['mp-careers__page', 'mp-careers__page--'.$size]) }} {!! $dl() !!}>{{ $slot }}</div>
            BLADE;
    }
}
