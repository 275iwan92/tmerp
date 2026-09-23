<?php

namespace Devlib\Components;

// Keterangan saat kursor diarahkan ke isi slot.
class Tooltip extends DevlibComponent
{
    public function __construct(
        public string $text = '',
        public string $position = 'top',
    ) {}

    public function tag(): string
    {
        return 'x-dl::tooltip';
    }

    public function render()
    {
        return <<<'BLADE'
            <span {{ $attributes->class(['dl-tooltip', 'dl-tooltip--'.$position]) }} {!! $dl() !!}>{{ $slot }}<span class="dl-tooltip__text">{{ $text }}</span></span>
            BLADE;
    }
}
