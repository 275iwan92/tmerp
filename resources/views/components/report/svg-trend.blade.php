{{--
    Grafik tren garis/area SVG murni -- server-rendered, TANPA library JS.
    Dipilih menggantikan ApexCharts (keputusan user 2026-09-13, "boleh bangun
    sendiri pakai SVG"): karena murni markup hasil render PHP, Livewire bisa
    memperbaruinya lewat morph BIASA tiap filter berubah -- tidak perlu
    wire:ignore/wire:key trik apa pun yang dibutuhkan ApexCharts.

    $series: [['label'=>'2026','color'=>'var(--sh-accent)','data'=>[..12 angka..],'area'=>true], ...]
    $labels: label sumbu X, sejajar indeks $series[]['data']
--}}
@props([
    'series' => [],
    'labels' => [],
    'height' => 240,
    'formatter' => null,
])
@php
    $w = 720;
    $h = (int) $height;
    $padL = 46; $padR = 12; $padT = 12; $padB = 22;
    $plotW = $w - $padL - $padR;
    $plotH = $h - $padT - $padB;

    $all = [];
    foreach ($series as $s) {
        foreach ($s['data'] as $v) {
            $all[] = (float) $v;
        }
    }
    $max = $all ? max($all) : 0;
    $max = $max > 0 ? $max : 1;
    $magnitude = 10 ** max(0, strlen((string) (int) $max) - 2);
    $niceMax = ceil($max / $magnitude) * $magnitude;
    $niceMax = $niceMax > 0 ? $niceMax : 1;

    $n = max(count($labels), 1);
    $xAt = fn ($i) => $padL + ($n > 1 ? $i * ($plotW / ($n - 1)) : $plotW / 2);
    $yAt = fn ($v) => $padT + $plotH - ((float) $v / $niceMax * $plotH);

    $fmt = $formatter ?? function ($v) {
        $v = (float) $v;
        if (abs($v) >= 1_000_000_000) {
            return number_format($v / 1_000_000_000, 1, ',', '.') . ' M';
        }
        if (abs($v) >= 1_000_000) {
            return number_format($v / 1_000_000, 0, ',', '.') . ' jt';
        }

        return number_format($v, 0, ',', '.');
    };
    $gridLines = 4;
@endphp

<div class="w-full overflow-x-auto">
    <svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-full" style="min-width:520px" role="img" aria-label="Grafik tren">
        @for ($g = 0; $g <= $gridLines; $g++)
            @php $val = $niceMax / $gridLines * $g; $y = $yAt($val); @endphp
            <line x1="{{ $padL }}" y1="{{ $y }}" x2="{{ $w - $padR }}" y2="{{ $y }}"
                  style="stroke:var(--sh-border)" stroke-width="1" />
            <text x="{{ $padL - 8 }}" y="{{ $y + 3.5 }}" text-anchor="end"
                  font-size="10" style="fill:var(--sh-muted)">{{ $fmt($val) }}</text>
        @endfor

        @foreach ($labels as $i => $lab)
            <text x="{{ $xAt($i) }}" y="{{ $h - 6 }}" text-anchor="middle"
                  font-size="10" style="fill:var(--sh-muted)">{{ $lab }}</text>
        @endforeach

        @foreach ($series as $s)
            @php
                $pts = [];
                foreach ($s['data'] as $i => $v) { $pts[] = $xAt($i) . ',' . round($yAt($v), 1); }
                $color = $s['color'] ?? 'var(--sh-accent)';
            @endphp
            @if (! empty($s['area']))
                <path d="M{{ $pts[0] }} L{{ implode(' L', $pts) }} L{{ $xAt($n - 1) }},{{ $padT + $plotH }} L{{ $padL }},{{ $padT + $plotH }} Z"
                      style="fill:{{ $color }}" fill-opacity="0.12" stroke="none" />
            @endif
            <polyline points="{{ implode(' ', $pts) }}" fill="none" style="stroke:{{ $color }}"
                      stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />
            @foreach ($s['data'] as $i => $v)
                <circle cx="{{ $xAt($i) }}" cy="{{ round($yAt($v), 1) }}" r="3.2" style="fill:{{ $color }}">
                    <title>{{ ($s['label'] ?? '') }} {{ $labels[$i] ?? '' }}: {{ $fmt($v) }}</title>
                </circle>
            @endforeach
        @endforeach
    </svg>
</div>

@if (count($series) > 1)
    <div class="flex flex-wrap gap-4 justify-center mt-1">
        @foreach ($series as $s)
            <span class="inline-flex items-center gap-1.5 text-[11px]" style="color:var(--sh-muted)">
                <span class="inline-block w-2.5 h-2.5 rounded-full" style="background:{{ $s['color'] ?? 'var(--sh-accent)' }}"></span>
                {{ $s['label'] ?? '' }}
            </span>
        @endforeach
    </div>
@endif
