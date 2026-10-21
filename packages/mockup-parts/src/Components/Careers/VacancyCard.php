<?php

/*
 * REMARK
 * Tujuan       : Kartu lowongan yang seluruhnya tautan ke detail: kode lowongan, judul, dua baris uraian, ajakan "Lihat detail & lamar".
 * Sumber hulu  : tm2hrr.vac_vacancy (vacancy_code, title, description); token --dl-*.
 * Input        : code, title, text, href, cta.
 * Output       : <a class="mp-careers__vac"> dengan kode, judul, uraian terpotong 2 baris, ajakan.
 * Dipakai oleh : resources/views/careers/landing.blade.php.
 * Status       : Aktif (T10 tahap 6b, 2026-10-21).
 */

namespace MockupParts\Components\Careers;

use Devlib\Components\DevlibComponent;

class VacancyCard extends DevlibComponent
{
    protected const TEXT_PROPS = [];

    protected const TEXT_LISTS = [];

    public function __construct(
        public string $code = '',
        public string $title = '',
        public string $text = '',
        public string $href = '#',
        public string $cta = '',
    ) {}

    public function tag(): string
    {
        return 'x-mp::careers.vacancy-card';
    }

    public function source(): string
    {
        return 'tm2hrr.vac_vacancy (lowongan terbit)';
    }

    public function render()
    {
        return <<<'BLADE'
            <a href="{{ $href }}" {{ $attributes->class('mp-careers__vac') }} {!! $dl() !!}>
                <small>{{ $code }}</small>
                <h3>{{ $title }}</h3>
                <p>{{ $text }}</p>
                <b>{{ $cta }} &rarr;</b>
            </a>
            BLADE;
    }
}
