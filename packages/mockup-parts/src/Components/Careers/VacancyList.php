<?php

/*
 * REMARK
 * Tujuan       : Daftar lowongan terbuka: judul "Lowongan Terbuka (N)" lalu kartu x-mp::careers.vacancy-card dalam dua kolom; kosong = kotak pesan x-dl::empty-state.
 * Sumber hulu  : x-mp::careers.vacancy-card, x-dl::empty-state; token --dl-*.
 * Input        : title, count, empty (teks bila tidak ada lowongan); isi = kartu.
 * Output       : <section class="mp-careers__list"> dengan judul dan grid kartu.
 * Dipakai oleh : resources/views/careers/landing.blade.php.
 * Status       : Aktif (T10 tahap 6b, 2026-10-21).
 */

namespace MockupParts\Components\Careers;

use Devlib\Components\DevlibComponent;

class VacancyList extends DevlibComponent
{
    protected const TEXT_PROPS = [];

    protected const TEXT_LISTS = [];

    public function __construct(public string $title = '', public int $count = 0, public string $empty = '') {}

    public function tag(): string
    {
        return 'x-mp::careers.vacancy-list';
    }

    public function source(): string
    {
        return 'tm2hrr.vac_vacancy (lowongan terbit, belum ditutup)';
    }

    public function render()
    {
        return <<<'BLADE'
            <section {{ $attributes->class('mp-careers__list') }} {!! $dl() !!}>
                <h2 class="mp-careers__lead">{{ $title }} ({{ $count }})</h2>
                @if ($count === 0)
                    <x-dl::empty-state :title="$empty" icon="file" />
                @else
                    <div class="mp-careers__vacs">{{ $slot }}</div>
                @endif
            </section>
            BLADE;
    }
}
