<?php

namespace Devlib\Components;

// Baris tombol di kaki form/kartu. align: end (default) | start | between.
class ActionBar extends DevlibComponent
{
    public function __construct(
        public string $align = 'end',
    ) {}

    public function tag(): string
    {
        return 'x-dl::action-bar';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-actionbar', 'dl-actionbar--'.$align]) }} {!! $dl() !!}>{{ $slot }}</div>
            BLADE;
    }
}
