{{--
    Grafik batang -- vertikal berkelompok via ApexCharts (keputusan user
    2026-09-13, ganti dari SVG hand-rolled: "graphics jelek dan kaku").
    Varian horizontal (:horizontal="true", ranking teks panjang spt nama
    customer/supplier) TETAP progress-bar HTML polos -- itu bukan "grafik"
    dalam arti chart, sudah cukup jelas & tidak butuh library.
--}}
@props([
    'series' => [], // [['label'=>,'color'=>,'data'=>[...]], ...]
    'labels' => [],
    'height' => 220,
    'horizontal' => false,
    'formatter' => null, // sudah tidak dipakai versi Apex (formatter angka digeneralisir di report-charts.js), dibiarkan utk kompatibilitas prop lama
])
@php
    $max = 0;
    foreach ($series as $s) {
        foreach ($s['data'] as $v) {
            $max = max($max, (float) $v);
        }
    }
@endphp

@if ($horizontal)
    <div class="space-y-2">
        @foreach ($labels as $i => $lab)
            @php $val = (float) ($series[0]['data'][$i] ?? 0); $pct = $max > 0 ? $val / $max * 100 : 0; @endphp
            <div>
                <div class="flex items-baseline justify-between text-[11.5px] mb-1">
                    <span class="truncate font-medium" style="color:var(--sh-ink)">{{ $lab }}</span>
                    <span class="shrink-0 ml-2 tabular-nums" style="color:var(--sh-muted)">{{ number_format($val, 2, ',', '.') }}</span>
                </div>
                <div class="h-2 rounded-full overflow-hidden" style="background:var(--sh-border)">
                    <div class="h-full rounded-full" style="width:{{ number_format($pct, 2, '.', '') }}%;background:{{ \App\Support\Report\ChartColor::resolve($series[0]['color'] ?? null) }}"></div>
                </div>
            </div>
        @endforeach
    </div>
@else
    @php
        $options = [
            'chart' => ['type' => 'bar', 'height' => (int) $height, 'toolbar' => ['show' => false], 'fontFamily' => 'inherit', 'foreColor' => '#718096', 'animations' => ['speed' => 350]],
            'plotOptions' => ['bar' => ['borderRadius' => 5, 'borderRadiusApplication' => 'end', 'columnWidth' => count($series) > 1 ? '65%' : '45%']],
            'series' => collect($series)->map(fn ($s) => ['name' => $s['label'] ?? '', 'data' => array_map(fn ($v) => (float) $v, $s['data'])])->values()->all(),
            'colors' => collect($series)->map(fn ($s) => \App\Support\Report\ChartColor::resolve($s['color'] ?? null))->all(),
            'xaxis' => ['categories' => array_values($labels), 'axisBorder' => ['show' => false], 'axisTicks' => ['show' => false]],
            'yaxis' => ['labels' => ['style' => ['fontSize' => '10px']]],
            'grid' => ['borderColor' => '#e3e9f2', 'strokeDashArray' => 0],
            'dataLabels' => ['enabled' => false],
            'legend' => ['show' => count($series) > 1, 'position' => 'top', 'horizontalAlign' => 'center', 'fontSize' => '11px'],
            'tooltip' => ['theme' => 'light'],
        ];
    @endphp
    <div wire:ignore wire:key="rbars-{{ md5(json_encode($options)) }}" x-data="reportChart(@js($options))" x-init="init($el)"></div>
@endif
