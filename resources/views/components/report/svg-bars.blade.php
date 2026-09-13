{{--
    Grafik batang SVG -- vertikal berkelompok (default) ATAU horizontal
    (:horizontal="true", cocok utk label teks panjang spt nama customer).
    Server-rendered, tanpa JS.
--}}
@props([
    'series' => [], // [['label'=>,'color'=>,'data'=>[...]], ...]
    'labels' => [],
    'height' => 220,
    'horizontal' => false,
    'formatter' => null,
])
@php
    $fmt = $formatter ?? fn ($v) => number_format((float) $v / 1_000_000, 0, ',', '.') . ' jt';
    $all = [];
    foreach ($series as $s) {
        foreach ($s['data'] as $v) {
            $all[] = (float) $v;
        }
    }
    $max = $all ? max($all) : 0;
    $max = $max > 0 ? $max : 1;
@endphp

@if ($horizontal)
    {{-- 1 label = 1 baris, dipakai utk peringkat (top customer/supplier). --}}
    <div class="space-y-2">
        @foreach ($labels as $i => $lab)
            @php $val = (float) ($series[0]['data'][$i] ?? 0); $pct = $val / $max * 100; @endphp
            <div>
                <div class="flex items-baseline justify-between text-[11.5px] mb-1">
                    <span class="truncate font-medium" style="color:var(--sh-ink)">{{ $lab }}</span>
                    <span class="shrink-0 ml-2 tabular-nums" style="color:var(--sh-muted)">{{ $fmt($val) }}</span>
                </div>
                <div class="h-2 rounded-full overflow-hidden" style="background:var(--sh-border)">
                    <div class="h-full rounded-full" style="width:{{ number_format($pct, 2, '.', '') }}%;background:{{ $series[0]['color'] ?? 'var(--sh-accent)' }}"></div>
                </div>
            </div>
        @endforeach
    </div>
@else
    @php
        $w = 600; $h = (int) $height;
        $padL = 8; $padR = 8; $padT = 8; $padB = 22;
        $plotW = $w - $padL - $padR; $plotH = $h - $padT - $padB;
        $n = max(count($labels), 1);
        $slot = $plotW / $n;
        $seriesCount = max(count($series), 1);
        $barW = min(28, ($slot * 0.62) / $seriesCount);
        $groupW = $barW * $seriesCount;
    @endphp
    <div class="w-full overflow-x-auto">
        <svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-full" style="min-width:420px" role="img" aria-label="Grafik batang">
            @for ($g = 0; $g <= 3; $g++)
                @php $y = $padT + $plotH - ($plotH / 3 * $g); @endphp
                <line x1="{{ $padL }}" y1="{{ $y }}" x2="{{ $w - $padR }}" y2="{{ $y }}" style="stroke:var(--sh-border)" stroke-width="1" />
            @endfor

            @foreach ($labels as $i => $lab)
                @php $slotX = $padL + $i * $slot; $groupX = $slotX + ($slot - $groupW) / 2; @endphp
                @foreach ($series as $si => $s)
                    @php
                        $v = (float) ($s['data'][$i] ?? 0);
                        $barH = $max > 0 ? ($v / $max) * $plotH : 0;
                        $x = $groupX + $si * $barW;
                        $y = $padT + $plotH - $barH;
                    @endphp
                    <rect x="{{ round($x, 1) }}" y="{{ round($y, 1) }}" width="{{ round($barW - 3, 1) }}" height="{{ round($barH, 1) }}"
                          rx="3" style="fill:{{ $s['color'] ?? 'var(--sh-accent)' }}">
                        <title>{{ ($s['label'] ?? '') }} {{ $lab }}: {{ $fmt($v) }}</title>
                    </rect>
                @endforeach
                <text x="{{ $slotX + $slot / 2 }}" y="{{ $h - 6 }}" text-anchor="middle" font-size="10" style="fill:var(--sh-muted)">{{ $lab }}</text>
            @endforeach
        </svg>
    </div>
    @if (count($series) > 1)
        <div class="flex flex-wrap gap-4 justify-center mt-1">
            @foreach ($series as $s)
                <span class="inline-flex items-center gap-1.5 text-[11px]" style="color:var(--sh-muted)">
                    <span class="inline-block w-2.5 h-2.5 rounded-[3px]" style="background:{{ $s['color'] ?? 'var(--sh-accent)' }}"></span>
                    {{ $s['label'] ?? '' }}
                </span>
            @endforeach
        </div>
    @endif
@endif
