<?php

namespace App\Support\Report;

// Resolusi token var(--sh-*) (dipakai di seluruh halaman Laporan Manajemen,
// mis. 'var(--sh-accent)') jadi hex literal -- dibutuhkan ApexCharts
// (svg-bars/svg-trend/svg-donut, keputusan user 2026-09-13 ganti dari SVG
// hand-rolled karena "jelek dan kaku") karena config JS-nya (gradient,
// tooltip, data label) butuh nilai warna sungguhan, bukan CSS custom
// property yang baru di-resolve browser saat render style biasa.
// Palet HARUS sinkron dgn resources/views/layouts/report.blade.php (.rs-app).
class ChartColor
{
    protected const MAP = [
        '--sh-accent' => '#2878f0',
        '--sh-ok' => '#11a879',
        '--sh-warn' => '#f09a22',
        '--sh-amber' => '#f09a22',
        '--sh-violet' => '#8358e8',
        '--sh-danger' => '#e64b52',
        '--sh-muted' => '#718096',
        '--sh-ink' => '#4a5578',
        '--sh-ink-strong' => '#14213a',
        '--sh-border' => '#e3e9f2',
    ];

    public static function resolve(?string $value): string
    {
        if (! $value) {
            return self::MAP['--sh-accent'];
        }

        if (preg_match('/var\((--sh-[a-z-]+)\)/', $value, $m)) {
            return self::MAP[$m[1]] ?? self::MAP['--sh-accent'];
        }

        return $value;
    }
}
