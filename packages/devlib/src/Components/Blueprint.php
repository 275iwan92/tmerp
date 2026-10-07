<?php

namespace Devlib\Components;

// Kanvas CAD / blueprint: grid minor+mayor, zoom-pan (viewBox), crosshair, HUD koordinat, garis dimensi.
// Satuan = satuan dunia (mm). $dims = [[x1, y1, x2, y2, 'label?']]; slot = gambar (class dl-bp__cut / crease / perf).
// Panduan: skill devlib-svg-cad.
class Blueprint extends DevlibComponent
{
    public string $uid;

    public function __construct(
        public float $width = 400,
        public float $height = 300,
        public float $minor = 10,
        public float $major = 50,
        public string $unit = 'mm',
        public array $dims = [],
        public string $heightCss = '420px',
    ) {
        $this->uid = 'bp'.substr(md5(uniqid('', true)), 0, 6);
    }

    public function tag(): string
    {
        return 'x-dl::blueprint';
    }

    public function jsClass(): ?string
    {
        return 'Blueprint';
    }

    // Teks ukuran dihitung dari koordinat (bukan diketik).
    public function dimLabel(array $d): string
    {
        return $d[4] ?? \Devlib\Locale::decimal(hypot($d[2] - $d[0], $d[3] - $d[1]), 1).' '.$this->unit;
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-bp') }} {!! $dl() !!} data-dl-blueprint data-w="{{ $width }}" data-h="{{ $height }}" data-unit="{{ $unit }}" style="height:{{ $heightCss }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" viewBox="-20 -20 {{ $width + 40 }} {{ $height + 40 }}" preserveAspectRatio="xMidYMid meet" data-dl-bp-svg>
                    <defs>
                        <pattern id="{{ $uid }}m" width="{{ $minor }}" height="{{ $minor }}" patternUnits="userSpaceOnUse"><path d="M {{ $minor }} 0 L 0 0 0 {{ $minor }}" class="dl-bp__minor" vector-effect="non-scaling-stroke"/></pattern>
                        <pattern id="{{ $uid }}j" width="{{ $major }}" height="{{ $major }}" patternUnits="userSpaceOnUse"><path d="M {{ $major }} 0 L 0 0 0 {{ $major }}" class="dl-bp__major" vector-effect="non-scaling-stroke"/></pattern>
                        <marker id="{{ $uid }}a" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" class="dl-bp__arrow"/></marker>
                    </defs>
                    <g data-layer="grid"><rect x="-2000" y="-2000" width="4000" height="4000" fill="url(#{{ $uid }}m)"/><rect x="-2000" y="-2000" width="4000" height="4000" fill="url(#{{ $uid }}j)"/></g>
                    <g data-layer="cut">{{ $slot }}</g>
                    <g data-layer="dim">
                        @foreach ($dims as $d)
                            <line x1="{{ $d[0] }}" y1="{{ $d[1] }}" x2="{{ $d[2] }}" y2="{{ $d[3] }}" class="dl-bp__dim" vector-effect="non-scaling-stroke" marker-start="url(#{{ $uid }}a)" marker-end="url(#{{ $uid }}a)"/>
                            <text x="{{ ($d[0] + $d[2]) / 2 }}" y="{{ ($d[1] + $d[3]) / 2 - 2 }}" class="dl-bp__label" text-anchor="middle">{{ $dimLabel($d) }}</text>
                        @endforeach
                    </g>
                    <g data-dl-bp-cross class="dl-bp__cross" hidden><line data-h x1="-2000" x2="2000" vector-effect="non-scaling-stroke"/><line data-v y1="-2000" y2="2000" vector-effect="non-scaling-stroke"/></g>
                </svg>
                <div class="dl-bp__hud" data-dl-bp-hud>X 0.0 Y 0.0 · 100%</div>
                <div class="dl-bp__tools"><button type="button" data-dl-bp-zin>+</button><button type="button" data-dl-bp-zout>-</button><button type="button" data-dl-bp-fit>Fit</button></div>
            </div>
            BLADE;
    }
}