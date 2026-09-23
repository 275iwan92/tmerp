<?php

namespace Devlib\Components;

// Pengumuman bergantian (dari file teks lewat x-dl::frame announce="...").
class Announce extends DevlibComponent
{
    public function __construct(public array $lines = [], public int $interval = 6) {}

    public function tag(): string
    {
        return 'x-dl::announce';
    }

    public function source(): string
    {
        return 'media/text/pengumuman.txt';
    }

    public function render()
    {
        return <<<'BLADE'
            @if ($lines)
                <div {{ $attributes->class('dl-announce') }} {!! $dl() !!} data-dl-announce data-lines="{{ json_encode(array_column($lines, 't')) }}" data-interval="{{ $interval }}"><i class="fa-solid fa-bullhorn"></i><span data-dl-announce-text>{{ $lines[0]['t'] }}</span></div>
            @endif
            BLADE;
    }
}
