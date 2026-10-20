{{--
    Kartu KPI gaya eksekutif (ikon badge + angka besar + delta panah).
    Beda dari <x-grid.stat-box> (kotak pastel polos, dipakai grid Master) --
    ini khusus Laporan Manajemen, meniru pola mockup yang dibawa user.
    Semua warna via token .shell (--sh-*), BUKAN hex literal, supaya ikut
    12 kombinasi tema (lihat themestandard.md (diarsipkan)).
--}}
@props([
    'label',
    'value',
    'icon' => 'fa-solid fa-chart-simple',
    'tone' => 'blue', // blue|green|amber|violet|danger
    'delta' => null, // teks angka delta, mis. "+12,8%"
    'trend' => null, // 'up'|'down'|null (null = tak ada panah, mis. n/a)
    'note' => null, // teks pembanding, mis. "vs tahun lalu"
])
@php
    $toneVar = match ($tone) {
        'green' => '--sh-ok',
        'amber' => '--sh-warn',
        'violet' => '--sh-violet',
        'danger' => '--sh-danger',
        default => '--sh-accent',
    };
    $trendVar = $trend === 'up' ? '--sh-ok' : ($trend === 'down' ? '--sh-danger' : null);
    $arrow = $trend === 'up' ? '↑' : ($trend === 'down' ? '↓' : '');
@endphp
<div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
    <div class="flex items-center justify-between">
        <span class="text-[11px] font-bold uppercase tracking-wide" style="color:var(--sh-muted)">{{ $label }}</span>
        <span class="w-8 h-8 rounded-lg grid place-items-center text-[13px]"
              style="background:color-mix(in srgb, var({{ $toneVar }}) 14%, transparent); color:var({{ $toneVar }})">
            <i class="{{ $icon }}"></i>
        </span>
    </div>
    <strong class="block text-[22px] font-extrabold mt-2 -tracking-wide" style="color:var(--sh-ink-strong)">{{ $value }}</strong>
    @if ($delta !== null || $note !== null)
        <span class="block text-[11.5px] mt-1" style="color:var(--sh-muted)">
            @if ($delta !== null)
                <b style="{{ $trendVar ? 'color:var('.$trendVar.')' : '' }}">{{ $arrow }} {{ $delta }}</b>
            @endif
            {{ $note }}
        </span>
    @endif
</div>
