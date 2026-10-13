<?php

/*
 * REMARK
 * Tujuan       : Wadah beberapa accordion; arah vertikal/horizontal, multiple, dan ingatan panel terbuka (remember).
 * Sumber hulu  : Komponen generik DevLib.
 * Input        : direction, multiple, height, remember.
 * Output       : Markup .dl-accgroup; kondisi terakhir disimpan di localStorage (acc-group.js, core.js).
 * Dipakai oleh : Form WO (production-wo), demo devlib.test.
 * Status       : Aktif (DevLib).
 */

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
        public ?string $remember = null,   // set = the open panels (by key) are remembered in the browser under this name
    ) {}

    public function tag(): string
    {
        return 'x-dl::accordion-group direction='.$this->direction;
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-accgroup', 'dl-accgroup--'.$direction]) }} {!! $dl() !!} data-dl-accgroup data-multiple="{{ $multiple ? 1 : 0 }}" @if ($remember) data-remember="{{ $remember }}" @endif style="--dl-acc-h:{{ $height }}">{{ $slot }}</div>
            BLADE;
    }
}
