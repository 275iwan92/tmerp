<?php

namespace App\Services\MasterCard;

use App\Support\Dieline\TmDieline;
use App\Support\V2\Data\Sales;

// Dari data kartu -> Tipe Produk (saran), parameter dieline yang dicocokkan ke ukuran pisau, cek bentangan, dan SVG.
// "Engine suggests, officer confirms": tipe & parameter hanya saran, user bisa menggantinya di form.
// Pisau di kartu bisa memuat beberapa potong (N UP / 1 SET = A+B), jadi ukurannya dibagi dulu per potong: susunan
// grid dipilih yang paling dekat dengan bentangan default tipe itu, lalu parameter bebas (lidah lem, flap, tuck, lidah
// kunci) dihitung supaya bentangan = ukuran per potong. Rumus diturunkan dari 6 kartu nyata (KS Power, Osima, YAMA,
// Outer Alsamsi, Alsamsi Top, Yahya).
final class MasterCardShape
{
    public const TOLERANCE = 3.0; // mm

    public static function parts(array $c): array
    {
        if (! empty($c['part_label'])) {
            return [$c['part_label']];
        }

        return self::doubleJoin($c) ? ['A', 'B'] : [''];
    }

    public static function doubleJoin(array $c): bool
    {
        return (bool) preg_match('/double/i', (string) ($c['join_text'] ?? ''));
    }

    public static function suggestType(array $c): string
    {
        $name = strtoupper((string) ($c['product_name'] ?? '').' '.($c['knife_code'] ?? ''));
        if (self::doubleJoin($c)) {
            return str_contains($name, 'LOCK') ? 'LOCK_BOTTOM_2PC' : 'RSC_2PC';
        }
        if (($c['dim_h'] ?? 999) <= 60 && preg_match('/\b(TOP|TUTUP|LID)\b/', $name)) {
            return 'TRAY_LID_DW';
        }

        return 'RTE_BOX';
    }

    // Jumlah potong di pisau: "1/2 UP" & "1 UP" pada kartu 2 part = pisau per part; "1 SET" = semua part; "8 UP" = 8.
    public static function pieces(array $c): int
    {
        $up = strtoupper((string) ($c['up_text'] ?? ''));
        $parts = count(self::parts($c));
        if (str_contains($up, 'SET')) {
            return max(1, $parts);
        }
        if (preg_match('/(\d+)\s*\/\s*(\d+)/', $up) || ! preg_match('/(\d+)/', $up, $m)) {
            return 1;
        }

        return max(1, (int) $m[1]);
    }

    // Ukuran pisau per potong [w, h], orientasi & susunan grid terdekat dengan bentangan acuan.
    public static function knifePiece(array $c, array $ref): ?array
    {
        if (! ($c['knife_w'] ?? null) || ! ($c['knife_h'] ?? null)) {
            return null;
        }
        $n = self::pieces($c);
        $best = null;
        for ($cols = 1; $cols <= $n; $cols++) {
            if ($n % $cols) {
                continue;
            }
            foreach ([[$c['knife_w'] / $cols, $c['knife_h'] / ($n / $cols)]] as [$a, $b]) {
                foreach ([[$a, $b], [$b, $a]] as [$w, $h]) {
                    $d = abs($w - $ref['w']) + abs($h - $ref['h']);
                    if (! $best || $d < $best[2]) {
                        $best = [round($w, 2), round($h, 2), $d];
                    }
                }
            }
        }

        return [$best[0], $best[1]];
    }

    public static function defaults(string $type): array
    {
        return array_map(fn ($p) => $p['default'], Sales::dielineTypes()[$type]['params'] ?? []);
    }

    // Parameter dieline lengkap untuk kartu ini (dims_json).
    public static function fit(array $c, string $type): array
    {
        $d = array_merge(self::defaults($type), array_filter(['product_length' => $c['dim_l'] ?? null, 'product_width' => $c['dim_w'] ?? null, 'product_height' => $c['dim_h'] ?? null]));
        if (array_key_exists('overflow_margin', $d)) {
            $d['overflow_margin'] = 0; // ukuran pisau di kartu = tanpa bleed
        }
        $spec = Sales::dielineTypes()[$type]['spec'] ?? null;
        $piece = $spec ? self::knifePiece($c, TmDieline::flat($spec, $d)) : null;
        if (! $piece) {
            return $d;
        }
        [$w, $h] = $piece;
        [$L, $W, $H] = [$d['product_length'], $d['product_width'], $d['product_height']];
        $clamp = fn ($v, $lo, $hi) => round(max($lo, min($hi, $v)), 2);
        switch ($type) {
            case 'RSC_2PC':
                $d['glue_flap'] = $clamp($w - $L - $W, 15, 60);
                $d['flap_extra'] = $clamp(($h - $H - $W) / 2, 0, 40);
                break;
            case 'LOCK_BOTTOM_2PC':
                $d['glue_flap'] = $clamp($w - $L - $W, 15, 60);
                $d['lock_depth'] = $clamp($h - $H - $W / 2 - ($d['flap_extra'] ?? 0), 20, $W * 1.2);
                break;
            case 'TRAY_LID_DW':
                $d['lock_tab'] = $clamp(($w - $L - 4 * $H) / 2, 5, 40);
                break;
            case 'RTE_BOX': case 'STE_BOX': case 'CRASH_LOCK':
                $d['glue_flap'] = $clamp($w - 2 * $L - 2 * $W, 8, 30);
                if ($type !== 'CRASH_LOCK') {
                    $d['top_tuck_flap'] = $d['bottom_tuck_flap'] = $clamp(($h - $H) / 2, 8, 80);
                    $d['dust_flap'] = $clamp(min($d['dust_flap'] ?? 12, $d['top_tuck_flap'] - 5), 5, 80);
                }
                break;
        }

        return $d;
    }

    // [flat per part, ukuran pisau per potong, ok?] — ok bila selisih <= TOLERANCE di kedua sisi.
    public static function check(array $c, string $type, array $dims): array
    {
        $spec = Sales::dielineTypes()[$type]['spec'] ?? null;
        $flat = $spec ? TmDieline::flat($spec, $dims) : ['w' => 0, 'h' => 0, 'area' => 0];
        $piece = $spec ? self::knifePiece($c, $flat) : null;
        $ok = $piece && abs($flat['w'] - $piece[0]) <= self::TOLERANCE && abs($flat['h'] - $piece[1]) <= self::TOLERANCE;

        return ['flat' => $flat, 'piece' => $piece, 'ok' => $ok];
    }

    public static function svg(string $type, array $dims): ?string
    {
        return TmDieline::svg(Sales::dielineTypes()[$type]['spec'] ?? null, $dims);
    }
}
