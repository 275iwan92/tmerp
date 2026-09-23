<?php

namespace Devlib\Components;

// Grafik SVG tanpa library luar. Satu format data untuk semua tipe:
//   type: bar | hbar | stacked | line | area | combo | pie | donut | funnel | heatmap | radar
//   labels: ['Jan', 'Feb', ...]
//   series: [['name' => 'Penjualan', 'data' => [..], 'type' => bar|line (combo), 'tone' => accent|success|...]]
//   format: number | money | percent (angka ringkas: 1,2 jt / 3,4 M)
// Tooltip saat hover, klik legenda = sembunyikan/tampilkan seri, warna ikut tema (token --dl-ch-*).
class Chart extends DevlibComponent
{
    public function __construct(
        public string $type = 'bar',
        public array $labels = [],
        public array $series = [],
        public ?string $title = null,
        public ?string $subtitle = null,
        public string $format = 'number',
        public string $height = '280px',
        public bool $legend = true,
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::chart type='.$this->type;
    }

    public function source(): string
    {
        return $this->source ?? 'data laporan';
    }

    public function render()
    {
        return <<<'BLADE'
            <figure {{ $attributes->class('dl-chart') }} {!! $dl() !!} data-dl-chart data-type="{{ $type }}" data-format="{{ $format }}" data-labels="{{ json_encode($labels) }}" data-series="{{ json_encode($series) }}" style="--dl-ch-h:{{ $height }}">
                @if ($title)<figcaption class="dl-chart__head"><b>{{ $title }}</b>@if ($subtitle)<small>{{ $subtitle }}</small>@endif</figcaption>@endif
                <div class="dl-chart__plot" data-ch-plot></div>
                @if ($legend)<div class="dl-chart__legend" data-ch-legend></div>@endif
                <div class="dl-chart__tip" data-ch-tip hidden></div>
            </figure>
            BLADE;
    }
}
