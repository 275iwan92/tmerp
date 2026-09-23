<?php

namespace Devlib\Components;

// Garis pemisah dengan teks di tengah (mis. "atau").
class Divider extends DevlibComponent
{
    public function __construct(
        public string $label = '',
        public ?string $i18n = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::divider';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-divider') }} {!! $dl() !!}><span @if ($i18n) data-i18n="{{ $i18n }}" @endif>{{ $label }}</span></div>
            BLADE;
    }
}
