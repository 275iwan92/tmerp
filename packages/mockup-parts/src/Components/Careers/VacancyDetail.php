<?php

/*
 * REMARK
 * Tujuan       : Isi satu lowongan di halaman detail: kode, judul besar, uraian lengkap (baris baru dipertahankan), dan baris tombol di bawahnya (slot).
 * Sumber hulu  : tm2hrr.vac_vacancy (vacancy_code, title, description); token --dl-*.
 * Input        : code, title, text; slot = tombol / tautan aksi.
 * Output       : <article class="mp-careers__detail"> dengan kode, <h1>, uraian, dan baris aksi.
 * Dipakai oleh : resources/views/careers/vacancy.blade.php.
 * Status       : Aktif (T10 tahap 6b, 2026-10-21).
 */

namespace MockupParts\Components\Careers;

use Devlib\Components\DevlibComponent;

class VacancyDetail extends DevlibComponent
{
    protected const TEXT_PROPS = [];

    protected const TEXT_LISTS = [];

    public function __construct(public string $code = '', public string $title = '', public string $text = '') {}

    public function tag(): string
    {
        return 'x-mp::careers.vacancy-detail';
    }

    public function source(): string
    {
        return 'tm2hrr.vac_vacancy (lowongan terbit)';
    }

    public function render()
    {
        return <<<'BLADE'
            <article {{ $attributes->class('mp-careers__detail') }} {!! $dl() !!}>
                <small>{{ $code }}</small>
                <h1>{{ $title }}</h1>
                <div class="mp-careers__text">{{ $text }}</div>
                <div class="mp-careers__actions">{{ $slot }}</div>
            </article>
            BLADE;
    }
}
