<?php

namespace Devlib\Components;

// Kotak pesan sebaris. Kosong = tersembunyi (bisa diisi lewat JS).
class Alert extends DevlibComponent
{
    public function __construct(
        public string $tone = 'danger',
        public ?string $message = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::alert tone='.$this->tone;
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-alert', 'dl-alert--'.$tone, 'is-show' => $message]) }} {!! $dl() !!} data-dl-alert>{{ $message }}</div>
            BLADE;
    }
}
