<?php

namespace Devlib\Components;

use Devlib\Components\Field\Slider;
use Devlib\Locale;

// Kartu KPI dashboard: label, nilai, perubahan vs periode lalu (▲▼, warna sesuai arah yang BAIK),
// sparkline, dan progres ke target. goodWhen: up (naik = baik, mis. penjualan) | down (turun = baik, mis. reject rate).
class Kpi extends DevlibComponent
{
    public function __construct(
        public string $label = '',
        public float|int|string $value = 0,
        public string $format = 'number',
        public ?float $previous = null,
        public string $goodWhen = 'up',
        public array $spark = [],
        public ?float $target = null,
        public ?string $hint = null,
        public string $icon = 'fa-chart-line',
        public string $tone = 'accent',
        // Baris kecil tambahan di bawah target / hint (mis. rincian "Confirmed .. · Draft .."); tidak diterjemahkan.
        public ?string $note = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::kpi';
    }

    public function show(float|int|string $v): string
    {
        if (! is_numeric($v)) {
            return (string) $v;
        }
        $n = Slider::compact((float) $v);

        return match ($this->format) {
            'money' => 'IDR '.$n, 'percent' => Locale::decimal((float) $v, 1).'%', default => $n
        };
    }

    public function delta(): ?array
    {
        if ($this->previous === null || ! is_numeric($this->value) || (float) $this->previous == 0.0) {
            return null;
        }
        $pct = ((float) $this->value - $this->previous) / abs($this->previous) * 100;
        $good = $this->goodWhen === 'down' ? $pct <= 0 : $pct >= 0;

        return ['pct' => $pct, 'up' => $pct >= 0, 'good' => $good];
    }

    public function targetPct(): float
    {
        return $this->target ? min(100, max(0, (float) $this->value / $this->target * 100)) : 0;
    }

    public function sparkPath(): string
    {
        $d = array_values(array_map('floatval', $this->spark));
        if (count($d) < 2) {
            return '';
        }
        [$mn, $mx] = [min($d), max($d)];
        $pts = array_map(fn ($v, $i) => round($i / (count($d) - 1) * 120, 1).','.round(34 - ($mx > $mn ? ($v - $mn) / ($mx - $mn) : .5) * 30, 1), $d, array_keys($d));

        return implode(' ', $pts);
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-kpi', 'dl-kpi--'.$tone]) }} {!! $dl() !!}>
                <div class="dl-kpi__top"><span class="dl-kpi__icon"><i class="fa-solid {{ \Devlib\Icon::fa($icon) }}"></i></span><span class="dl-kpi__label">{{ $label }}</span>
                    @if ($delta())<span class="dl-kpi__delta {{ $delta()['good'] ? 'is-good' : 'is-bad' }}" title="{{ \Devlib\Lang::text('vs last period') }}: {{ $show($previous) }}">{{ $delta()['up'] ? '▲' : '▼' }} {{ \Devlib\Locale::number(abs($delta()['pct']), 1) }}%</span>@endif</div>
                <div class="dl-kpi__row"><b class="dl-kpi__value">{{ $show($value) }}</b>
                    @if ($sparkPath())<svg class="dl-kpi__spark {{ $delta() && ! $delta()['good'] ? 'is-bad' : '' }}" viewBox="0 0 120 36" preserveAspectRatio="none"><polyline points="{{ $sparkPath() }}"/></svg>@endif</div>
                @if ($target)
                    <div class="dl-kpi__target"><span style="width:{{ $targetPct() }}%"></span></div><small class="dl-kpi__hint">{{ \Devlib\Locale::number($targetPct()) }}% {{ \Devlib\Lang::text('of target') }} {{ $show($target) }}</small>
                @elseif ($hint)
                    <small class="dl-kpi__hint">{{ $hint }}</small>
                @endif
                @if ($note)<small class="dl-kpi__hint dl-kpi__note">{{ $note }}</small>@endif
            </div>
            BLADE;
    }
}
