<?php

namespace Devlib\Components;

// Grid Summary -> Detail (drill-down): slot "summary" = grid ringkasan, slot bawaan = grid detail.
// Klik 2x / Buka baris ringkasan -> grid detail menampilkan baris yang kolom `link`-nya = kunci baris itu,
// dengan breadcrumb untuk kembali. Dua grid tetap x-dl::grid biasa (semua fitur grid ikut).
class Drilldown extends DevlibComponent
{
    public function __construct(
        public string $link = '',
        public string $labelKey = 'name',
        public string $title = 'Summary',
    ) {}

    public function tag(): string
    {
        return 'x-dl::drilldown';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-drill') }} {!! $dl() !!} data-dl-drill data-link="{{ $link }}" data-label="{{ $labelKey }}" data-title="{{ $title }}">
                <nav class="dl-drill__crumb" data-dd-crumb></nav>
                <div data-dd-summary>{{ $summary ?? '' }}</div>
                <div data-dd-detail hidden>{{ $slot }}</div>
            </div>
            BLADE;
    }
}
