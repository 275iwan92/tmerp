{{--
    Donut chart SVG (stroke-dasharray per segmen, teknik yg sama dgn mockup)
    + daftar rincian di sampingnya. Server-rendered, tanpa JS.

    $segments: [['label'=>'PT ABC','value'=>123.4,'color'=>'var(--sh-accent)'], ...]
--}}
@props([
    'segments' => [],
    'centerValue' => '',
    'centerLabel' => '',
    'formatter' => null,
])
@php
    $fmt = $formatter ?? fn ($v) => number_format((float) $v, 0, ',', '.');
    $total = array_sum(array_map(fn ($s) => (float) $s['value'], $segments));
    $r = 62;
    $circumference = 2 * M_PI * $r;
    $cumulative = 0;
@endphp

<div class="flex items-center gap-4 flex-wrap sm:flex-nowrap">
    <div class="w-[168px] min-w-[168px] relative">
        <svg viewBox="0 0 180 180" style="transform:rotate(-90deg)">
            <circle cx="90" cy="90" r="{{ $r }}" fill="none" style="stroke:var(--sh-border)" stroke-width="22" />
            @foreach ($segments as $seg)
                @php
                    $val = (float) $seg['value'];
                    $frac = $total > 0 ? $val / $total : 0;
                    $len = $frac * $circumference;
                    $offset = -$cumulative;
                    $cumulative += $len;
                @endphp
                @if ($val > 0)
                    <circle cx="90" cy="90" r="{{ $r }}" fill="none" style="stroke:{{ $seg['color'] }}" stroke-width="22"
                            stroke-dasharray="{{ round($len, 2) }} {{ round($circumference, 2) }}"
                            stroke-dashoffset="{{ round($offset, 2) }}">
                        <title>{{ $seg['label'] }}: {{ $fmt($val) }} ({{ number_format($frac * 100, 1) }}%)</title>
                    </circle>
                @endif
            @endforeach
        </svg>
        <div class="absolute inset-0 grid place-content-center text-center leading-tight">
            <b class="text-[17px]" style="color:var(--sh-ink-strong)">{{ $centerValue }}</b>
            <span class="text-[10px] mt-1" style="color:var(--sh-muted)">{{ $centerLabel }}</span>
        </div>
    </div>
    <div class="flex-1 min-w-[180px]">
        @foreach ($segments as $seg)
            @php $pct = $total > 0 ? (float) $seg['value'] / $total * 100 : 0; @endphp
            <div class="flex items-center justify-between text-[12px] py-1.5">
                <span class="flex items-center gap-2 truncate" style="color:var(--sh-ink)">
                    <span class="w-2.5 h-2.5 rounded-[3px] shrink-0" style="background:{{ $seg['color'] }}"></span>
                    <span class="truncate">{{ $seg['label'] }}</span>
                </span>
                <b class="shrink-0 ml-2" style="color:var(--sh-ink-strong)">{{ number_format($pct, 1) }}%</b>
            </div>
        @endforeach
    </div>
</div>
