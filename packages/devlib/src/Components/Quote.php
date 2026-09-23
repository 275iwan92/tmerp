<?php

namespace Devlib\Components;

// Kata motivasi dari daftar (biasanya dari file teks lewat x-dl::frame quotes="...").
// order: random | sequence (urut, lanjut tiap kali halaman dibuka); interval: detik ganti (0 = tetap).
class Quote extends DevlibComponent
{
    public function __construct(
        public array $quotes = [],
        public string $order = 'random',
        public int $interval = 0,
    ) {}

    public function tag(): string
    {
        return 'x-dl::quote order='.$this->order;
    }

    public function source(): string
    {
        return 'media/text/*.txt (satu baris = "teks | penulis")';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-quote') }} {!! $dl() !!} data-dl-quote data-quotes="{{ json_encode($quotes) }}" data-order="{{ $order }}" data-interval="{{ $interval }}"><div class="qi">&#8220;</div><div class="q" data-dl-quote-text>—</div><div class="a" data-dl-quote-author>—</div></div>
            BLADE;
    }
}
