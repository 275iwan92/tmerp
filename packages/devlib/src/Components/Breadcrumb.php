<?php

namespace Devlib\Components;

// Jejak lokasi halaman: Modul › Grup › Layar.
class Breadcrumb extends DevlibComponent
{
    public function __construct(
        public array $items = [],
    ) {}

    public function tag(): string
    {
        return 'x-dl::breadcrumb';
    }

    public function render()
    {
        return <<<'BLADE'
            <nav {{ $attributes->class('dl-crumb') }} {!! $dl() !!} aria-label="breadcrumb">
                @foreach ($items as $label => $url)
                    @if (! $loop->first)<i class="fa-solid fa-chevron-right"></i>@endif
                    @if ($url && ! $loop->last)<a href="{{ $url }}">{{ is_int($label) ? $url : $label }}</a>@else<span>{{ is_int($label) ? $url : $label }}</span>@endif
                @endforeach
            </nav>
            BLADE;
    }
}
