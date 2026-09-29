<?php

namespace Devlib\Components;

// Tata letak dokumen: isi utama (kiri, lebar) + panel samping (kanan, sempit) mis. ringkasan & pajak.
// Slot default = isi utama, slot `side` = panel kanan; prop aside = lebar panel kanan. Layar sempit -> bertumpuk.
class Split extends DevlibComponent
{
    public function __construct(
        public string $aside = '320px',
    ) {}

    public function tag(): string
    {
        return 'x-dl::split';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-split') }} {!! $dl() !!} style="--dl-aside:{{ $aside }}">
                <div class="dl-split__main">{{ $slot }}</div>
                <aside class="dl-split__aside">{{ $side ?? '' }}</aside>
            </div>
            BLADE;
    }
}
