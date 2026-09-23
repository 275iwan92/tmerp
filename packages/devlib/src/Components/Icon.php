<?php

namespace Devlib\Components;

// Ikon baku: sebut ARTINYA (save, delete, print, ...), bukan nama Font Awesome.
class Icon extends DevlibComponent
{
    public function __construct(public string $name = 'info', public ?string $label = null) {}

    public function tag(): string
    {
        return 'x-dl::icon name='.$this->name;
    }

    public function fa(): string
    {
        return \Devlib\Icon::fa($this->name);
    }

    public function render()
    {
        return <<<'BLADE'
            <i {{ $attributes->class(['fa-solid', $fa(), 'dl-icon']) }} {!! $dl() !!} @if ($label) title="{{ $label }}" aria-label="{{ $label }}" @else aria-hidden="true" @endif></i>
            BLADE;
    }
}
