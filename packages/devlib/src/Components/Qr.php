<?php

namespace Devlib\Components;

// Kode QR (qrcodejs, dimuat sekali dari cdnjs saat dibutuhkan). Isi = teks/URL/ID.
class Qr extends DevlibComponent
{
    public function __construct(
        public string $text = '',
        public int $size = 120,
        public ?string $caption = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::qr';
    }

    public function render()
    {
        return <<<'BLADE'
            <figure {{ $attributes->class('dl-qrbox') }} {!! $dl() !!} style="--q:{{ $size }}px"><span data-dl-qrcode data-text="{{ $text }}" data-size="{{ $size }}" title="{{ $text }}"></span>@if ($caption)<figcaption>{{ $caption }}</figcaption>@endif</figure>
            BLADE;
    }
}
