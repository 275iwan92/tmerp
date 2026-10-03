<?php

namespace Devlib\Components;

// Tata letak dokumen: isi utama (lebar) + panel samping (sempit) mis. ringkasan & pajak, atau pohon kelompok di kiri
// (Translation Dictionary). Slot default = isi utama, slot `side` = panel samping; prop aside = lebar panel samping;
// side = right (bawaan) | left. Layar sempit -> bertumpuk.
class Split extends DevlibComponent
{
    public function __construct(
        public string $aside = '320px',
        public string $side = 'right',
    ) {}

    public function tag(): string
    {
        return 'x-dl::split'.($this->side === 'left' ? ' side=left' : '');
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-split', 'dl-split--left' => $side === 'left']) }} {!! $dl() !!} style="--dl-aside:{{ $aside }}">
                <div class="dl-split__main">{{ $slot }}</div>
                <aside class="dl-split__aside">{{ $side ?? '' }}</aside>
            </div>
            BLADE;
    }
}
