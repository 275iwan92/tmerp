<?php

namespace Devlib\Components;

// Gauge setengah lingkaran: zona warna dari bands [[batas atas, tone]] + jarum + nilai.
// Contoh OEE: bands [[60,'danger'],[85,'warn'],[100,'success']].
class Gauge extends DevlibComponent
{
    public function __construct(
        public float $value = 0,
        public float $min = 0,
        public float $max = 100,
        public array $bands = [[60, 'danger'], [85, 'warn'], [100, 'success']],
        public string $label = '',
        public string $unit = '%',
        public ?float $target = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::gauge';
    }

    public function point(float $v, float $r): string
    {
        $a = M_PI * (1 - (max($this->min, min($this->max, $v)) - $this->min) / max(1e-9, $this->max - $this->min));

        return round(100 + $r * cos($a), 2).','.round(100 - $r * sin($a), 2);
    }

    public function arc(float $from, float $to, float $r): string
    {
        return 'M'.$this->point($from, $r).' A'.$r.','.$r.' 0 0 1 '.$this->point($to, $r);
    }

    public function bandArcs(): array
    {
        $out = [];
        $from = $this->min;
        foreach ($this->bands as [$upto, $tone]) {
            $out[] = [$this->arc($from, min($upto, $this->max), 80), $tone];
            $from = $upto;
        }

        return $out;
    }

    public function tone(): string
    {
        foreach ($this->bands as [$upto, $tone]) {
            if ($this->value <= $upto) {
                return $tone;
            }
        }

        return end($this->bands)[1] ?? 'accent';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-gauge') }} {!! $dl() !!}>
                <svg viewBox="0 0 200 118" role="img" aria-label="{{ $label }} {{ $value }}{{ $unit }}">
                    <path d="{{ $arc($min, $max, 80) }}" class="dl-gauge__track"/>
                    @foreach ($bandArcs() as [$d, $t])<path d="{{ $d }}" class="dl-gauge__band dl-chart--{{ $t }}"/>@endforeach
                    @if ($target !== null)<line x1="{{ explode(',', $point($target, 66))[0] }}" y1="{{ explode(',', $point($target, 66))[1] }}" x2="{{ explode(',', $point($target, 94))[0] }}" y2="{{ explode(',', $point($target, 94))[1] }}" class="dl-gauge__target"/>@endif
                    <line x1="100" y1="100" x2="{{ explode(',', $point($value, 62))[0] }}" y2="{{ explode(',', $point($value, 62))[1] }}" class="dl-gauge__needle"/><circle cx="100" cy="100" r="6" class="dl-gauge__hub"/>
                    <text x="22" y="114" class="dl-gauge__mm" text-anchor="middle">{{ $min + 0 }}</text><text x="178" y="114" class="dl-gauge__mm" text-anchor="middle">{{ $max + 0 }}</text>
                </svg>
                <b class="dl-gauge__big is-{{ $tone() }}">{{ \Devlib\Locale::decimal($value, 1) }}{{ $unit }}</b>
                @if ($label)<span class="dl-gauge__label">{{ $label }}@if ($target !== null)<small> · target {{ $target + 0 }}{{ $unit }}</small>@endif</span>@endif
            </div>
            BLADE;
    }
}
