{{--
    Grafik tren garis/area via ApexCharts (keputusan user 2026-09-13, ganti
    dari SVG hand-rolled: "graphics jelek dan kaku"). Beberapa series bisa
    dicampur line/area lewat flag 'area' per-series (ApexCharts mixed chart).

    $series: [['label'=>'2026','color'=>'var(--sh-accent)','data'=>[..12 angka..],'area'=>true], ...]
    $labels: label sumbu X, sejajar indeks $series[]['data']
--}}
@props([
    'series' => [],
    'labels' => [],
    'height' => 240,
    'formatter' => null, // tidak dipakai versi Apex, dibiarkan utk kompatibilitas prop lama
])
@php
    $options = [
        'chart' => ['type' => 'line', 'height' => (int) $height, 'toolbar' => ['show' => false], 'fontFamily' => 'inherit', 'foreColor' => '#718096', 'animations' => ['speed' => 350]],
        'series' => collect($series)->map(fn ($s) => [
            'name' => $s['label'] ?? '',
            'type' => ! empty($s['area']) ? 'area' : 'line',
            'data' => array_map(fn ($v) => (float) $v, $s['data']),
        ])->values()->all(),
        'colors' => collect($series)->map(fn ($s) => \App\Support\Report\ChartColor::resolve($s['color'] ?? null))->all(),
        'stroke' => ['curve' => 'smooth', 'width' => 2.5],
        'fill' => ['type' => 'gradient', 'gradient' => ['shadeIntensity' => 1, 'opacityFrom' => 0.35, 'opacityTo' => 0.02, 'stops' => [0, 90, 100]]],
        'markers' => ['size' => 3.5, 'strokeWidth' => 0, 'hover' => ['size' => 5]],
        'xaxis' => ['categories' => array_values($labels), 'axisBorder' => ['show' => false], 'axisTicks' => ['show' => false]],
        'yaxis' => ['labels' => ['style' => ['fontSize' => '10px']]],
        'grid' => ['borderColor' => '#e3e9f2'],
        'dataLabels' => ['enabled' => false],
        'legend' => ['show' => count($series) > 1, 'position' => 'top', 'horizontalAlign' => 'center', 'fontSize' => '11px'],
        'tooltip' => ['theme' => 'light', 'shared' => true],
    ];
@endphp

<div wire:ignore wire:key="rtrend-{{ md5(json_encode($options)) }}" x-data="reportChart(@js($options))" x-init="init($el)"></div>
