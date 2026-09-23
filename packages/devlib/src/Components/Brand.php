<?php

namespace Devlib\Components;

// Logo + nama + tagline perusahaan. tone: light (di atas gelap) | dark.
class Brand extends DevlibComponent
{
    public function __construct(
        public string $name = 'TELASIH MAKMUR',
        public string $tagline = 'packaging solution',
        public string $tone = 'light',
        public ?string $logo = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::brand tone='.$this->tone;
    }

    public function source(): string
    {
        return 'identitas perusahaan (tmerp: Settings > Identifikasi)';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-brand', 'dl-brand--'.$tone]) }} {!! $dl() !!}><span class="dl-logo-badge">@if ($logo)<img src="{{ $logo }}" alt="{{ $name }}">@endif</span><div><b>{{ $name }}</b><span>{{ $tagline }}</span></div></div>
            BLADE;
    }
}
