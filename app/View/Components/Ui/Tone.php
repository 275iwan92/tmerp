<?php

namespace App\View\Components\Ui;

// Palet semantik tunggal. Semua primitive yang punya "warna arti" (badge,
// progress, metric, chip) membaca dari sini -- tambah/ubah tone cukup di
// satu tempat. Class dieja utuh (bukan dirangkai "bg-{$tone}") supaya
// Tailwind bisa memindainya saat build.
final class Tone
{
    public const SOFT = [
        'neutral' => 'bg-line-soft text-ink-soft',
        'muted' => 'bg-line-soft text-neutral',
        'code' => 'bg-line-soft text-navy',
        'accent' => 'bg-accent-soft text-accent',
        'info' => 'bg-info-soft text-info-ink',
        'success' => 'bg-success-soft text-success-ink',
        'warn' => 'bg-warn-soft text-warn-ink',
        'danger' => 'bg-danger-soft text-danger-ink',
        'highlight' => 'bg-highlight-soft text-highlight-ink',
        'extra' => 'bg-extra-soft text-extra-ink',
    ];

    public const SOLID = [
        'neutral' => 'bg-neutral',
        'muted' => 'bg-neutral',
        'code' => 'bg-navy',
        'accent' => 'bg-accent',
        'info' => 'bg-info',
        'success' => 'bg-success',
        'warn' => 'bg-warn',
        'danger' => 'bg-danger',
        'highlight' => 'bg-highlight',
        'extra' => 'bg-extra',
    ];

    public const TEXT = [
        'neutral' => 'text-ink-soft',
        'muted' => 'text-muted',
        'code' => 'text-navy',
        'accent' => 'text-accent',
        'info' => 'text-info-ink',
        'success' => 'text-success-ink',
        'warn' => 'text-warn-ink',
        'danger' => 'text-danger-ink',
        'highlight' => 'text-highlight-ink',
        'extra' => 'text-extra-ink',
    ];

    public static function soft(string $tone): string
    {
        return self::SOFT[$tone] ?? self::SOFT['neutral'];
    }

    public static function solid(string $tone): string
    {
        return self::SOLID[$tone] ?? self::SOLID['neutral'];
    }

    public static function text(string $tone): string
    {
        return self::TEXT[$tone] ?? self::TEXT['neutral'];
    }

    // Ambang baku "makin tinggi makin baik" (kesiapan data, OEE, dll).
    public static function forScore(?float $value, float $good = 80, float $fair = 50): string
    {
        return match (true) {
            $value === null => 'muted',
            $value >= $good => 'success',
            $value >= $fair => 'warn',
            default => 'danger',
        };
    }
}
