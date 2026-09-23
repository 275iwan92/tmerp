<?php

namespace Devlib;

use Carbon\CarbonImmutable;
use DateTimeInterface;

// Format lokal baku (Indonesia, Surabaya): zona waktu WIB, tanggal, angka, Rupiah.
// Semua layar memakai ini -- jangan number_format/date() sendiri-sendiri.
final class Locale
{
    // Bahasa antarmuka aktif: id | en (tmerp: sec_user.lang_code).
    public static function lang(): string
    {
        return config('devlib.lang', str_starts_with(app()->getLocale(), 'en') ? 'en' : 'id');
    }

    public static function timezone(): string
    {
        return config('devlib.timezone', 'Asia/Jakarta');
    }

    public static function tzLabel(): string
    {
        return config('devlib.timezone_label', 'WIB');
    }

    public static function at(DateTimeInterface|string|null $v = null): CarbonImmutable
    {
        return CarbonImmutable::parse($v ?? 'now')->setTimezone(self::timezone())->locale('id');
    }

    // long: Rabu, 23 September 2026 · short: 23 Sep 2026 · numeric: 23/09/2026
    public static function date(DateTimeInterface|string|null $v = null, string $style = 'short'): string
    {
        return self::at($v)->translatedFormat(match ($style) {
            'long' => 'l, j F Y',
            'numeric' => 'd/m/Y',
            default => 'j M Y',
        });
    }

    public static function time(DateTimeInterface|string|null $v = null, bool $seconds = false): string
    {
        return self::at($v)->format($seconds ? 'H:i:s' : 'H:i').' '.self::tzLabel();
    }

    public static function dateTime(DateTimeInterface|string|null $v = null): string
    {
        return self::date($v).', '.self::time($v);
    }

    public static function number(float|int|string|null $n, int $decimals = 0): string
    {
        return number_format((float) $n, $decimals, ',', '.');
    }

    public static function money(float|int|string|null $n, string $currency = 'IDR', int $decimals = 0): string
    {
        $symbol = ['IDR' => 'Rp', 'USD' => '$', 'EUR' => '€', 'JPY' => '¥'][$currency] ?? $currency;

        return $symbol.' '.self::number($n, $decimals);
    }

    public static function percent(float|int|string|null $n, int $decimals = 1): string
    {
        return self::number($n, $decimals).'%';
    }
}
