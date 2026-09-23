<?php

namespace Devlib\Components;

// Jam + tanggal berjalan (ikut bahasa aktif).
class Clock extends DevlibComponent
{
    public function tag(): string
    {
        return 'x-dl::clock';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-clock') }} {!! $dl() !!} data-dl-clock><div class="t" data-dl-clock-time>--:--:--</div><div class="d" data-dl-clock-date>—</div></div>
            BLADE;
    }
}
