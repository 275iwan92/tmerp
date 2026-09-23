<?php

namespace Devlib\Components;

// Kotak statistik grid; filter=true -> bisa diklik sebagai penyaring data (klik lagi = lepas).
class StatBox extends DevlibComponent
{
    public function __construct(
        public string $label = '',
        public string $value = '0',
        public string $tone = 'accent',
        public ?string $sub = null,
        public bool $filter = false,
        public bool $active = false,
    ) {}

    public function tag(): string
    {
        return 'x-dl::stat-box tone='.$this->tone;
    }

    public function render()
    {
        return <<<'BLADE'
            <{{ $filter ? 'button type=button' : 'div' }} {{ $attributes->class(['dl-stat', 'dl-stat--'.$tone, 'is-filter' => $filter, 'is-active' => $active]) }} {!! $dl() !!} @if ($filter) data-dl-stat @endif>
                <span class="dl-stat__label">{{ $label }}</span><span class="dl-stat__value">{{ $value }}</span>@if ($sub)<span class="dl-stat__sub">{{ $sub }}</span>@endif
            </{{ $filter ? 'button' : 'div' }}>
            BLADE;
    }
}
