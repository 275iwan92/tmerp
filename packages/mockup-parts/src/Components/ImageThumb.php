<?php

namespace MockupParts\Components;

use Devlib\Components\DevlibComponent;

// Gambar kecil berlabel (artwork lama/baru, foto part). Tanpa file = kotak berisi teks kosong.
class ImageThumb extends DevlibComponent
{
    public function __construct(
        public string $label = 'Artwork',
        public ?string $src = null,
        public string $empty = 'No image',
        public bool $highlight = false,
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-mp::image-thumb';
    }

    public function source(): string
    {
        return $this->source ?? \MockupParts\Src::of('tm2sdi.iqp_part.artwork_path');
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['mp-thumb', 'is-new' => $highlight]) }} {!! $dl() !!}>
                <small>{{ $label }}</small>
                <div class="mp-thumb__box">@if ($src)<img src="{{ $src }}" alt="{{ $label }}">@else{{ $empty }}@endif</div>
            </div>
            BLADE;
    }
}
