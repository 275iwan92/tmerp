<?php

namespace Devlib\Components;

// Bullet chart (Stephen Few): rentang kualitatif (buruk/cukup/baik), batang nilai, garis target.
class Bullet extends DevlibComponent
{
    public function __construct(
        public string $label = '',
        public float $value = 0,
        public float $target = 0,
        public array $ranges = [],
        public float $max = 0,
        public string $format = 'number',
        public ?string $sub = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::bullet';
    }

    public function top(): float
    {
        return $this->max ?: max([$this->value, $this->target, ...$this->ranges, 1]) * 1.05;
    }

    public function pct(float $v): float
    {
        return round(min(100, max(0, $v / $this->top() * 100)), 2);
    }

    public function show(float $v): string
    {
        $n = \Devlib\Components\Field\Slider::compact($v);

        return $this->format === 'money' ? 'Rp '.$n : ($this->format === 'percent' ? $n.'%' : $n);
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-bullet') }} {!! $dl() !!}>
                <div class="dl-bullet__label"><b>{{ $label }}</b>@if ($sub)<small>{{ $sub }}</small>@endif</div>
                <div class="dl-bullet__track" title="Nilai {{ $show($value) }} · target {{ $show($target) }}">
                    @foreach (array_reverse($ranges) as $i => $r)<span class="dl-bullet__range is-r{{ count($ranges) - $i }}" style="width:{{ $pct($r) }}%"></span>@endforeach
                    <span class="dl-bullet__bar {{ $value >= $target ? 'is-good' : 'is-bad' }}" style="width:{{ $pct($value) }}%"></span>
                    <span class="dl-bullet__target" style="left:{{ $pct($target) }}%"></span>
                </div>
                <div class="dl-bullet__nums"><b>{{ $show($value) }}</b><small>target {{ $show($target) }} · {{ $target ? \Devlib\Locale::number($value / $target * 100) : 0 }}%</small></div>
            </div>
            BLADE;
    }
}
