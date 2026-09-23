<?php

namespace Devlib\Components;

// Cuaca terkini dari internet (Open-Meteo, gratis tanpa kunci API), diambil oleh browser.
class Weather extends DevlibComponent
{
    public function __construct(
        public string $city = 'Surabaya',
        public float $lat = -7.2575,
        public float $lon = 112.7521,
    ) {}

    public function tag(): string
    {
        return 'x-dl::weather city='.$this->city;
    }

    public function source(): string
    {
        return 'internet: api.open-meteo.com ('.$this->lat.', '.$this->lon.')';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-weather') }} {!! $dl() !!} data-dl-weather data-lat="{{ $lat }}" data-lon="{{ $lon }}"><span class="dl-weather__icon" data-dl-weather-icon>⛅</span><div><div class="dl-weather__temp" data-dl-weather-temp>--°C</div><div class="dl-weather__desc"><span data-dl-weather-desc>memuat cuaca…</span> · {{ $city }}</div></div></div>
            BLADE;
    }
}
