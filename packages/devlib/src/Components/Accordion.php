<?php

namespace Devlib\Components;

// Bagian yang bisa dilipat/dibuka.
class Accordion extends DevlibComponent
{
    public function __construct(
        public string $title = '',
        public bool $open = false,
    ) {}

    public function tag(): string
    {
        return 'x-dl::accordion';
    }

    public function render()
    {
        return <<<'BLADE'
            <details {{ $attributes->class('dl-accordion') }} {!! $dl() !!} @if ($open) open @endif><summary class="dl-accordion__head">{{ $title }}<i class="fa-solid fa-chevron-down"></i></summary><div class="dl-accordion__body">{{ $slot }}</div></details>
            BLADE;
    }
}
