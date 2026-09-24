<?php

namespace Devlib\Components;

// Kotak pesan sebaris. Kosong = tersembunyi (bisa diisi lewat JS). Slot = isi kaya (teks tebal + tombol aksi).
class Alert extends DevlibComponent
{
    public function __construct(
        public string $tone = 'danger', // danger | info | warn | success
        public ?string $message = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::alert tone='.$this->tone;
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-alert', 'dl-alert--'.$tone, 'is-show' => $message || trim($slot) !== '']) }} {!! $dl() !!} data-dl-alert>{{ $message }}{{ $slot }}</div>
            BLADE;
    }
}
