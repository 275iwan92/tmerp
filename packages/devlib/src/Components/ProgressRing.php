<?php

namespace Devlib\Components;

// Cincin progres (persentase tercapai), warna otomatis dari ambang atau tone tetap.
class ProgressRing extends DevlibComponent
{
    public function __construct(
        public float $value = 0,
        public string $label = '',
        public ?string $tone = null,
        public int $size = 96,
        public ?string $caption = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::progress-ring';
    }

    public function dash(): string
    {
        $c = 2 * M_PI * 40;

        return round($c * min(100, max(0, $this->value)) / 100, 2).' '.round($c, 2);
    }

    public function autoTone(): string
    {
        return $this->tone ?? ($this->value >= 85 ? 'success' : ($this->value >= 60 ? 'warn' : 'danger'));
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-ring') }} {!! $dl() !!} style="--s:{{ $size }}px">
                <svg viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" class="dl-ring__track"/><circle cx="50" cy="50" r="40" class="dl-ring__bar dl-chart--{{ $autoTone() }}" stroke-dasharray="{{ $dash() }}" transform="rotate(-90 50 50)"/>
                    <text x="50" y="55" text-anchor="middle" class="dl-ring__val">{{ round($value) }}%</text></svg>
                @if ($label)<span class="dl-ring__label">{{ $label }}@if ($caption)<small>{{ $caption }}</small>@endif</span>@endif
            </div>
            BLADE;
    }
}
