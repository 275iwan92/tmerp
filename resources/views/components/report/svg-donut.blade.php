{{--
    Donut chart via ApexCharts (keputusan user 2026-09-13, ganti dari SVG
    hand-rolled stroke-dasharray: "graphics jelek dan kaku") + daftar
    rincian di sampingnya (tetap HTML, sudah jelas & ringan).

    $segments: [['label'=>'PT ABC','value'=>123.4,'color'=>'var(--sh-accent)'], ...]
--}}
@props([
    'segments' => [],
    'centerValue' => '',
    'centerLabel' => '',
    'formatter' => null, // tidak dipakai versi Apex, dibiarkan utk kompatibilitas prop lama
])
@php
    $total = array_sum(array_map(fn ($s) => (float) $s['value'], $segments));
    $options = [
        'chart' => ['type' => 'donut', 'height' => 210, 'fontFamily' => 'inherit', 'foreColor' => '#718096'],
        'series' => array_map(fn ($s) => (float) $s['value'], $segments),
        'labels' => array_map(fn ($s) => $s['label'], $segments),
        'colors' => array_map(fn ($s) => \App\Support\Report\ChartColor::resolve($s['color'] ?? null), $segments),
        'stroke' => ['width' => 2, 'colors' => ['var(--sh-surface, #fff)']],
        'dataLabels' => ['enabled' => false],
        'legend' => ['show' => false],
        'tooltip' => ['theme' => 'light'],
        'plotOptions' => ['pie' => ['donut' => ['size' => '72%']]],
        'custom' => ['centerLabel' => $centerLabel, 'centerValue' => $centerValue],
    ];
@endphp

<div class="flex items-center gap-4 flex-wrap sm:flex-nowrap">
    <div class="w-[168px] min-w-[168px]">
        <div wire:ignore wire:key="rdonut-{{ md5(json_encode($options)) }}" x-data="reportChart(@js($options))" x-init="init($el)"></div>
    </div>
    <div class="flex-1 min-w-[180px]">
        @foreach ($segments as $seg)
            @php $pct = $total > 0 ? (float) $seg['value'] / $total * 100 : 0; @endphp
            <div class="flex items-center justify-between text-[12px] py-1.5">
                <span class="flex items-center gap-2 truncate" style="color:var(--sh-ink)">
                    <span class="w-2.5 h-2.5 rounded-[3px] shrink-0" style="background:{{ \App\Support\Report\ChartColor::resolve($seg['color'] ?? null) }}"></span>
                    <span class="truncate">{{ $seg['label'] }}</span>
                </span>
                <b class="shrink-0 ml-2" style="color:var(--sh-ink-strong)">{{ number_format($pct, 1) }}%</b>
            </div>
        @endforeach
    </div>
</div>
