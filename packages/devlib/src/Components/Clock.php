<?php

namespace Devlib\Components;

// Jam + tanggal berjalan menurut zona waktu SISTEM (default Asia/Jakarta = WIB), ikut bahasa aktif. mode: clock (jam+tanggal) | date (tanggal saja).
class Clock extends DevlibComponent
{
    public string $timezone;

    public string $tzLabel;

    public function __construct(public string $mode = 'clock', ?string $timezone = null, ?string $tzLabel = null)
    {
        $this->timezone = $timezone ?? config('devlib.timezone', 'Asia/Jakarta');
        $this->tzLabel = $tzLabel ?? config('devlib.timezone_label', 'WIB');
    }

    public function tag(): string
    {
        return 'x-dl::clock mode='.$this->mode;
    }

    public function source(): string
    {
        return 'jam sistem, zona '.$this->timezone.' ('.$this->tzLabel.')';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-clock', 'dl-clock--'.$mode]) }} {!! $dl() !!} data-dl-clock data-tz="{{ $timezone }}">@if ($mode === 'clock')<div class="t"><span data-dl-clock-time>--:--:--</span> <small class="dl-clock__tz">{{ $tzLabel }}</small></div>@endif<div class="d" data-dl-clock-date>—</div></div>
            BLADE;
    }
}
