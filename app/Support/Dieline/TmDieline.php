<?php

namespace App\Support\Dieline;

use Throwable;

// Port PHP 1:1 dari renderer JS resources/js/tmdieline.js (= packages/mockup-parts/resources/assets/tmdieline.js):
// DSL prod_type.dieline_spec -> SVG & bentangan, supaya SVG dieline bisa dibuat di server (impor master card massal,
// user 2026-10-01: "setiap inputan file master card HARUS MENAMBAH SVG DATA"). Ubah salah satu = ubah keduanya;
// TmDielineTest menjaga hasil angka tetap sama.
final class TmDieline
{
    public const STROKE = ['CUT' => '#ff3b3b', 'CREASE' => '#00e676', 'BLEED' => '#888', 'HOLE' => '#4da3ff'];

    private const DASH = ['BLEED' => '5 3'];

    private const ALIAS = ['A' => 'product_length', 'B' => 'product_width', 'C' => 'product_height', 'D' => 'diameter', 'LH' => 'lid_height',
        'G' => 'glue_flap', 'TT' => 'top_tuck_flap', 'DF' => 'dust_flap', 'BT' => 'bottom_tuck_flap', 'P' => 'overflow_margin'];

    private const PREC = ['+' => 1, '-' => 1, '*' => 2, '/' => 2, 'u-' => 3];

    private static array $cache = [];

    public static function flat(?array $spec, array $dims): array
    {
        if (! $spec || empty($spec['flat'])) {
            return ['w' => 0, 'h' => 0, 'area' => 0];
        }
        $s = self::scope($spec, $dims);
        $w = round(self::safe($spec['flat']['w'] ?? 0, $s), 2);
        $h = round(self::safe($spec['flat']['h'] ?? 0, $s), 2);

        return ['w' => $w, 'h' => $h, 'area' => round($w * $h, 2)];
    }

    public static function svg(?array $spec, array $dims): ?string
    {
        if (! $spec || empty($spec['elements'])) {
            return null;
        }
        $s = self::scope($spec, $dims);
        $W = self::safe($spec['canvas']['w'] ?? 'W', $s);
        $H = self::safe($spec['canvas']['h'] ?? 'H', $s);
        if (! ($W > 0) || ! ($H > 0)) {
            return null;
        }
        $s['__W'] = $W;
        $s['__H'] = $H;
        $body = '';
        foreach ($spec['elements'] as $e) {
            $fn = 'draw'.ucfirst((string) ($e['t'] ?? ''));
            if (method_exists(self::class, $fn)) {
                try {
                    $body .= self::$fn($e, $s);
                } catch (Throwable) {
                }
            }
        }
        $M = max(24, ($W + $H) / 16);

        return '<svg width="100%" height="100%" viewBox="'.self::nf(-$M).' '.self::nf(-$M).' '.self::nf($W + 2 * $M).' '.self::nf($H + 2 * $M).'" xmlns="http://www.w3.org/2000/svg">'.$body.'</svg>';
    }

    public static function scope(array $spec, array $dims): array
    {
        $s = [];
        foreach ($dims as $k => $v) {
            $s[$k] = is_numeric($v) ? (float) $v : 0.0;
        }
        foreach (self::ALIAS as $k => $key) {
            $s[$k] = $s[$key] ?? 0.0;
        }
        foreach ($spec['vars'] ?? [] as $k => $expr) {
            $s[$k] = self::safe($expr, $s);
        }

        return $s;
    }

    public static function eval(mixed $expr, array $scope): float
    {
        if (is_int($expr) || is_float($expr)) {
            return (float) $expr;
        }
        if ($expr === null || $expr === '') {
            return 0.0;
        }
        $key = (string) $expr;
        $rpn = self::$cache[$key] ??= self::toRpn(self::tokenize($key));
        $st = [];
        foreach ($rpn as $t) {
            if (isset($t['n'])) {
                $st[] = $t['n'];
            } elseif (isset($t['v'])) {
                $v = $t['v'] === 'PI' ? M_PI : ($scope[$t['v']] ?? 0);
                $st[] = is_float($v) || is_int($v) ? (is_finite((float) $v) ? (float) $v : 0.0) : 0.0;
            } elseif (isset($t['f'])) {
                $args = array_splice($st, max(0, count($st) - ($t['a'] ?? 1)));
                $st[] = match ($t['f']) {
                    'max' => max(...$args), 'min' => min(...$args), 'abs' => abs($args[0]), 'sqrt' => sqrt($args[0]),
                    'round' => self::jsRound($args[0]), 'floor' => floor($args[0]), 'ceil' => ceil($args[0]),
                    default => throw new \RuntimeException('fungsi tak dikenal: '.$t['f']),
                };
            } elseif ($t['o'] === 'u-') {
                $st[] = -array_pop($st);
            } else {
                $b = array_pop($st);
                $a = array_pop($st);
                $st[] = match ($t['o']) {
                    '+' => $a + $b, '-' => $a - $b, '*' => $a * $b, default => $b == 0 ? 0.0 : $a / $b
                };
            }
        }
        $r = array_pop($st);

        return is_numeric($r) && is_finite((float) $r) ? (float) $r : 0.0;
    }

    private static function safe(mixed $expr, array $s): float
    {
        try {
            return self::eval($expr, $s);
        } catch (Throwable) {
            return 0.0;
        }
    }

    private static function tokenize(string $s): array
    {
        preg_match_all('/\s*([A-Za-z_][A-Za-z_0-9]*|\d+(?:\.\d+)?|[-+*\/(),])/', $s, $m, PREG_OFFSET_CAPTURE);
        $out = [];
        $pos = 0;
        foreach ($m[0] as $i => [$whole, $off]) {
            if ($off !== $pos) {
                throw new \RuntimeException('token tak dikenal: '.substr($s, $pos));
            }
            $out[] = $m[1][$i][0];
            $pos = $off + strlen($whole);
        }
        if ($pos !== strlen($s) && trim(substr($s, $pos)) !== '') {
            throw new \RuntimeException('sisa tak terparse: '.substr($s, $pos));
        }

        return $out;
    }

    private static function toRpn(array $tokens): array
    {
        $out = $ops = $argc = [];
        $prev = null;
        foreach ($tokens as $i => $tk) {
            if (preg_match('/^\d/', $tk)) {
                $out[] = ['n' => (float) $tk];
            } elseif (preg_match('/^[A-Za-z_]/', $tk)) {
                if (($tokens[$i + 1] ?? null) === '(') {
                    $ops[] = ['f' => $tk];
                    $argc[] = 1;
                } else {
                    $out[] = ['v' => $tk];
                }
            } elseif ($tk === ',') {
                while ($ops && (end($ops)['o'] ?? null) !== '(') {
                    $out[] = array_pop($ops);
                }
                if ($argc) {
                    $argc[count($argc) - 1]++;
                }
            } elseif ($tk === '(') {
                $ops[] = ['o' => '('];
            } elseif ($tk === ')') {
                while ($ops && (end($ops)['o'] ?? null) !== '(') {
                    $out[] = array_pop($ops);
                }
                array_pop($ops);
                if ($ops && isset(end($ops)['f'])) {
                    $fo = array_pop($ops);
                    $fo['a'] = array_pop($argc);
                    $out[] = $fo;
                }
            } else {
                $op = ($tk === '-' && ($prev === null || $prev === '(' || $prev === ',' || preg_match('/^[-+*\/]$/', $prev))) ? 'u-' : $tk;
                while ($ops) {
                    $top = end($ops);
                    if (isset($top['o']) && $top['o'] !== '(' && self::PREC[$top['o']] >= self::PREC[$op]) {
                        $out[] = array_pop($ops);
                    } else {
                        break;
                    }
                }
                $ops[] = ['o' => $op];
            }
            $prev = $tk;
        }
        while ($ops) {
            $out[] = array_pop($ops);
        }

        return $out;
    }

    // Math.round JS: .5 dibulatkan ke atas (juga untuk negatif).
    private static function jsRound(float $v): float
    {
        return floor($v + 0.5);
    }

    // Angka seperti String(Math.round(v*100)/100) di JS: tanpa nol di belakang.
    private static function nf(float $v): string
    {
        $r = self::jsRound($v * 100) / 100;
        if ($r == 0) {
            return '0';
        }
        $str = rtrim(rtrim(sprintf('%.2f', $r), '0'), '.');

        return $str === '-0' ? '0' : $str;
    }

    private static function attr(?string $k): string
    {
        $c = self::STROKE[$k] ?? self::STROKE['CUT'];
        $d = self::DASH[$k] ?? null;

        return 'fill="none" stroke="'.$c.'" stroke-width="1"'.($d ? ' stroke-dasharray="'.$d.'"' : '');
    }

    private static function e(array $e, string $k, array $s): float
    {
        return self::eval($e[$k] ?? null, $s);
    }

    private static function drawLine(array $e, array $s): string
    {
        return '<line x1="'.self::nf(self::e($e, 'x1', $s)).'" y1="'.self::nf(self::e($e, 'y1', $s)).'" x2="'.self::nf(self::e($e, 'x2', $s)).'" y2="'.self::nf(self::e($e, 'y2', $s)).'" '.self::attr($e['k'] ?? null).'/>';
    }

    private static function drawRect(array $e, array $s): string
    {
        return '<rect x="'.self::nf(self::e($e, 'x', $s)).'" y="'.self::nf(self::e($e, 'y', $s)).'" width="'.self::nf(self::e($e, 'w', $s)).'" height="'.self::nf(self::e($e, 'h', $s)).'" rx="1" '.self::attr($e['k'] ?? null).'/>';
    }

    private static function drawCircle(array $e, array $s): string
    {
        return '<circle cx="'.self::nf(self::e($e, 'cx', $s)).'" cy="'.self::nf(self::e($e, 'cy', $s)).'" r="'.self::nf(self::e($e, 'r', $s)).'" '.self::attr($e['k'] ?? null).'/>';
    }

    private static function drawTuck(array $e, array $s): string
    {
        [$a, $b, $y, $h] = [self::e($e, 'a', $s), self::e($e, 'b', $s), self::e($e, 'y', $s), self::e($e, 'h', $s)];
        if ($h <= 0 || $b <= $a) {
            return '';
        }
        $up = ($e['dir'] ?? null) !== 'down';
        $yy = $up ? $y - $h : $y + $h;
        $r = min(8, ($b - $a) / 6);
        $sg = $up ? -1 : 1;
        $n = fn ($v) => self::nf($v);
        $d = 'M'.$n($a).','.$n($y).' L'.$n($a).','.$n($yy - $sg * $r).' Q'.$n($a).','.$n($yy).' '.$n($a + $r).','.$n($yy).' L'.$n($b - $r).','.$n($yy).' Q'.$n($b).','.$n($yy).' '.$n($b).','.$n($yy - $sg * $r).' L'.$n($b).','.$n($y);

        return '<path d="'.$d.'" '.self::attr($e['k'] ?? null).'/>';
    }

    private static function drawDust(array $e, array $s): string
    {
        [$a, $b, $y, $h] = [self::e($e, 'a', $s), self::e($e, 'b', $s), self::e($e, 'y', $s), self::e($e, 'h', $s)];
        if ($h <= 0 || $b <= $a) {
            return '';
        }
        $yy = ($e['dir'] ?? null) !== 'down' ? $y - $h : $y + $h;
        $i = min($h, ($b - $a) / 3);
        $n = fn ($v) => self::nf($v);

        return '<path d="M'.$n($a).','.$n($y).' L'.$n($a + $i).','.$n($yy).' L'.$n($b - $i).','.$n($yy).' L'.$n($b).','.$n($y).'" '.self::attr($e['k'] ?? null).'/>';
    }

    private static function drawGlue(array $e, array $s): string
    {
        [$x0, $x1, $yT, $yB, $g] = [self::e($e, 'x0', $s), self::e($e, 'x1', $s), self::e($e, 'yT', $s), self::e($e, 'yB', $s), self::e($e, 'g', $s)];
        if ($x1 <= $x0) {
            return '';
        }
        $n = fn ($v) => self::nf($v);

        return '<path d="M'.$n($x1).','.$n($yT).' L'.$n($x0).','.$n($yT + $g).' L'.$n($x0).','.$n($yB - $g).' L'.$n($x1).','.$n($yB).'" '.self::attr($e['k'] ?? null).'/>';
    }

    private static function drawBleed(array $e, array $s): string
    {
        $p = self::e($e, 'p', $s);
        if ($p <= 0) {
            return '';
        }

        return '<rect x="'.self::nf(-$p).'" y="'.self::nf(-$p).'" width="'.self::nf($s['__W'] + 2 * $p).'" height="'.self::nf($s['__H'] + 2 * $p).'" '.self::attr($e['k'] ?? 'BLEED').'/>';
    }

    private static function drawFoldlines(array $e, array $s): string
    {
        $n = (int) self::jsRound(self::e($e, 'n', $s));
        [$w, $h] = [self::e($e, 'w', $s), self::e($e, 'h', $s)];
        if ($n < 1 || $w <= 0) {
            return '';
        }
        $o = '';
        for ($i = 1; $i <= $n; $i++) {
            $x = $w * $i / ($n + 1);
            $o .= '<line x1="'.self::nf($x).'" y1="0" x2="'.self::nf($x).'" y2="'.self::nf($h).'" '.self::attr($e['k'] ?? 'CREASE').' stroke-dasharray="5 3"/>';
        }

        return $o;
    }

    private static function drawHoles(array $e, array $s): string
    {
        $n = (int) self::jsRound(self::e($e, 'n', $s));
        [$w, $y, $r] = [self::e($e, 'w', $s), self::e($e, 'y', $s), self::e($e, 'r', $s)];
        if ($n < 1 || $w <= 0) {
            return '';
        }
        $o = '';
        for ($i = 0; $i < $n; $i++) {
            $o .= '<circle cx="'.self::nf($w * ($i + 0.5) / $n).'" cy="'.self::nf($y).'" r="'.self::nf($r).'" '.self::attr($e['k'] ?? 'HOLE').'/>';
        }

        return $o;
    }

    private static function drawText(array $e, array $s): string
    {
        return '<text x="'.self::nf(self::e($e, 'x', $s)).'" y="'.self::nf(self::e($e, 'y', $s)).'" fill="#9aa" font-size="11" text-anchor="middle">'.htmlspecialchars((string) ($e['s'] ?? ''), ENT_COMPAT).'</text>';
    }
}
