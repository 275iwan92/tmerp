<?php

namespace Devlib\Components;

// Struktur organisasi (Organization-Structure-Mockup + org chart tmerp): pohon bergaris, kartu jabatan
// berisi satu/lebih pemegang (kosong = "Lowong"). Drag pegangan kartu ke kartu lain = pindah atasan
// (beserta bawahannya; tidak boleh ke bawahannya sendiri), Undo, Simpan (dl:orgchart-save), Unduh HTML,
// cari pegawai (sorot + buka cabang + gulir), lipat/buka cabang, zoom.
// nodes: [['id', 'parent' => id|null, 'title' => jabatan, 'dept', 'people' => [nama | ['name','photo']]]]
class OrgChart extends DevlibComponent
{
    public function __construct(
        public array $nodes = [],
        public bool $editable = true,
        public string $title = 'Organization Structure',
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::org-chart';
    }

    public function source(): string
    {
        return $this->source ?? 'struktur jabatan';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-org') }} {!! $dl() !!} data-dl-org data-nodes="{{ json_encode($nodes) }}" data-editable="{{ $editable ? 1 : 0 }}" data-title="{{ $title }}">
                <div class="dl-org__bar">
                    @if ($editable)
                        <x-dl::button variant="secondary" size="sm" icon="undo" label="Undo" data-o-undo />
                        <x-dl::button preset="save" size="sm" data-o-save />
                    @endif
                    <x-dl::button variant="secondary" size="sm" icon="download" label="Download HTML" data-o-html />
                    <span class="dl-org__zoom"><x-dl::icon-button icon="fa-magnifying-glass-minus" title="Zoom out" data-o-zoom="-1" /><b data-o-zoomval>100%</b><x-dl::icon-button icon="fa-magnifying-glass-plus" title="Zoom in" data-o-zoom="1" /><x-dl::button variant="ghost" size="sm" label="Fit" data-o-zoom="0" /></span>
                    @if ($editable)<span class="dl-org__tip"><i class="fa-solid fa-circle-info"></i> {{ \Devlib\Lang::text('Drag the dotted icon on the left of a card onto another superior card to move its subordinates.') }}</span>@endif
                    <span class="dl-org__spacer"></span>
                    <div class="dl-grid__search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="{{ \Devlib\Lang::text('Search employee / position...') }}" data-o-search aria-label="{{ \Devlib\Lang::text('Search employee') }}"></div>
                </div>
                <div class="dl-org__canvas" data-o-canvas><div class="dl-org__tree" data-o-tree></div></div>
            </div>
            BLADE;
    }
}
