<?php

namespace Devlib\Components;

// Lembar cetak label: isi slot = deretan x-dl::label. Tombol Print mencetak labelnya saja (bilah dan layar lain tidak
// ikut), satu label tidak terpotong antar halaman. $back = alamat tombol Back; $count = jumlah label (tampil di bilah).
// $size  = ukuran label di lembar ini (sm | md | lg) -- dipakai untuk ukuran halaman cetak.
// $paper = roll (printer label / thermal: satu label = satu halaman seukuran label, tanpa tepi)
//          | a4 (printer kantor: label rapat di kertas A4, tepi 5 mm, dipotong mengikuti garis label).
class LabelSheet extends DevlibComponent
{
    public const PAPERS = ['roll' => 'Label roll (label printer)', 'a4' => 'A4 sheet (office printer)'];

    protected const TEXT_PROPS = ['title'];

    public function __construct(
        public string $title = 'Labels',
        public ?string $back = null,
        public ?int $count = null,
        public string $size = 'md',
        public string $paper = 'a4',
    ) {
        $this->size = isset(Label::SPEC[$size]) ? $size : 'md';
        $this->paper = isset(self::PAPERS[$paper]) ? $paper : 'a4';
    }

    public function tag(): string
    {
        return 'x-dl::label-sheet paper='.$this->paper;
    }

    // Ukuran halaman cetak: gulungan = persis ukuran label; A4 = tepi 5 mm.
    public function pageRule(): string
    {
        $s = Label::SPEC[$this->size];

        return $this->paper === 'roll' ? "@page{size:{$s['w']}mm {$s['h']}mm;margin:0}" : '@page{size:A4 portrait;margin:5mm}';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-labelsheet', 'dl-labelsheet--'.$paper]) }} {!! $dl() !!}>
                <style>@media print{ {!! $pageRule() !!} }</style>
                <div class="dl-labelsheet__bar">
                    <b>{{ $title }}</b>@if ($count !== null)<span>{{ $count }} {{ \Devlib\Lang::text('labels') }} · {{ \Devlib\Components\Label::SIZES[$size] }} · {{ \Devlib\Lang::text(\Devlib\Components\LabelSheet::PAPERS[$paper]) }}</span>@endif
                    <span class="dl-labelsheet__spacer"></span>
                    {{ $tools ?? '' }}
                    @if ($back)<a class="dl-btn dl-btn--secondary dl-btn--md" href="{{ $back }}"><i class="fa-solid fa-arrow-left"></i> Back</a>@endif
                    <button type="button" class="dl-btn dl-btn--primary dl-btn--md" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
                </div>
                {{ $guide ?? '' }}
                <div class="dl-labelsheet__labels">{{ $slot }}</div>
            </div>
            BLADE;
    }
}
