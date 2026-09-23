<?php

namespace Devlib\Components;

// Salam sesuai waktu: Selamat pagi / siang / sore / malam (ikut bahasa aktif).
class Greeting extends DevlibComponent
{
    public string $timezone;

    public function __construct(public string $name = '', ?string $timezone = null)
    {
        $this->timezone = $timezone ?? config('devlib.timezone', 'Asia/Jakarta');
    }

    public function tag(): string
    {
        return 'x-dl::greeting';
    }

    public function source(): string
    {
        return 'jam sistem, zona '.$this->timezone;
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-greeting') }} {!! $dl() !!} data-dl-greeting data-name="{{ $name }}" data-tz="{{ $timezone }}">—</div>
            BLADE;
    }
}
