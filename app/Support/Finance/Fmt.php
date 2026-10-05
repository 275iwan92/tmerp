<?php

namespace App\Support\Finance;

use Carbon\Carbon;

// Format angka & tanggal persis gaya mockup Accounting blueprint:
// kartu = ringkas ("IDR 8.42B", "IDR 486M"), tabel = penuh ("IDR 185,250,000"),
// negatif dalam kurung ("(IDR 942,000,000)"), tanggal "12 Sep 2026".
class Fmt
{
    public static function rp(float|string|null $v, bool $dashZero = false): string
    {
        $v = round((float) $v, 2);
        if ($dashZero && abs($v) < 0.005) {
            return '—';
        }
        $s = 'IDR '.number_format(abs($v), 0, '.', ',');

        return $v < 0 ? "({$s})" : $s;
    }

    public static function short(float|string|null $v): string
    {
        $v = (float) $v;
        $a = abs($v);
        $s = match (true) {
            $a >= 1e12 => number_format($a / 1e12, 2).'T',
            $a >= 1e9 => number_format($a / 1e9, 2).'B',
            $a >= 1e6 => number_format($a / 1e6, $a >= 1e8 ? 0 : 1).'M',
            $a >= 1e3 => number_format($a / 1e3, 0).'K',
            default => number_format($a, 0),
        };

        return $v < 0 ? "(IDR {$s})" : "IDR {$s}";
    }

    public static function date($d): string
    {
        return $d ? Carbon::parse($d)->format('d M Y') : '—';
    }

    public static function pct(float $v, int $dec = 1): string
    {
        return ($v > 0 ? '↑ ' : ($v < 0 ? '↓ ' : '')).number_format(abs($v), $dec).'%';
    }
}
