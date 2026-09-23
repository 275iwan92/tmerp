<?php

namespace Devlib\Components;

// Wadah beberapa x-dl::accordion. direction: vertical | horizontal (panel melebar ke samping;
// di layar HP otomatis jadi vertikal). multiple=false -> hanya satu panel terbuka.
// Horizontal selalu menyisakan satu panel terbuka.
class AccordionGroup extends DevlibComponent
{
    public function __construct(
        public string $direction = 'vertical',
        public bool $multiple = false,
        public string $height = '300px',
    ) {}

    public function tag(): string
    {
        return 'x-dl::accordion-group direction='.$this->direction;
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-accgroup', 'dl-accgroup--'.$direction]) }} {!! $dl() !!} data-dl-accgroup data-multiple="{{ $multiple ? 1 : 0 }}" style="--dl-acc-h:{{ $height }}">{{ $slot }}</div>
            BLADE;
    }
}
