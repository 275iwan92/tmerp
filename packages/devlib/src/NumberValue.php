<?php

namespace Devlib;

use Stringable;

// ANGKA (desimal & integer) = satu objek untuk input & tampilan (user 2026-10-01: "Object oriented adalah KEY"). Kembaran JS: DlNumber.
// Disimpan/dikirim: kanonik "1234.5" (DB DECIMAL mentah). Tampil & ketik ikut bahasa tampilan (id 1.234,5 · en 1,234.5).
final class NumberValue implements Stringable
{
    private function __construct(public readonly string $canonical) {}

    // Nilai dari DB / kiriman form DevLib: kanonik dipakai apa adanya; selain itu dibaca sebagai ketikan (parse).
    public static function of(mixed $v, ?string $lang = null): ?self
    {
        if ($v instanceof self) {
            return $v;
        }
        if (is_int($v) || is_float($v)) {
            return is_finite((float) $v) ? new self(self::canon(is_float($v) ? rtrim(rtrim(sprintf('%.10F', $v), '0'), '.') : (string) $v)) : null;
        }
        $s = trim((string) $v);

        return preg_match('/^-?\d+(\.\d+)?$/', $s) ? new self(self::canon($s)) : self::parse($s, $lang);
    }

    // Ketikan/tampilan manusia. Titik DAN koma -> yang terakhir desimal (bebas bahasa, mis. tempelan Excel).
    // Satu jenis -> aturan bahasa: pemisah desimal bahasa = desimal; pemisah ribuan berpola 1.234.567 = ribuan; satu pemisah lain = desimal.
    public static function parse(mixed $v, ?string $lang = null): ?self
    {
        if (is_int($v) || is_float($v) || $v instanceof self) {
            return self::of($v);
        }
        $s = preg_replace('/\s|Rp|IDR|USD|EUR|[$€¥%]/iu', '', (string) $v);
        $dots = substr_count($s, '.');
        $commas = substr_count($s, ',');
        if ($dots && $commas) {
            [$dec, $grp] = strrpos($s, '.') > strrpos($s, ',') ? ['.', ','] : [',', '.'];
            $s = str_replace([$grp, $dec], ['', '.'], $s);
        } elseif ($dots || $commas) {
            $ch = $dots ? '.' : ',';
            if ($ch === Locale::spec($lang)['dec']) {
                if (max($dots, $commas) > 1) {
                    return null;
                }
                $s = str_replace($ch, '.', $s);
            } elseif (preg_match('/^-?\d{1,3}('.preg_quote($ch, '/').'\d{3})+$/', $s)) {
                $s = str_replace($ch, '', $s);
            } elseif (max($dots, $commas) === 1) {
                $s = str_replace($ch, '.', $s);
            } else {
                return null;
            }
        }

        return preg_match('/^-?\d*\.?\d*$/', $s) && preg_match('/\d/', $s) ? new self(self::canon($s)) : null;
    }

    public function float(): float
    {
        return (float) $this->canonical;
    }

    // Tampil: tepat $decimals digit (1.234,50).
    public function display(int $decimals = 0, ?string $lang = null): string
    {
        $s = Locale::spec($lang);

        return number_format($this->float(), $decimals, $s['dec'], $s['grp']);
    }

    // Tampil dengan desimal secukupnya (maks $max, tanpa nol di belakang): 12,5 / 1.250.
    public function trimmed(int $max = 2, ?string $lang = null): string
    {
        $out = $this->display($max, $lang);
        $dec = Locale::spec($lang)['dec'];

        return str_contains($out, $dec) ? rtrim(rtrim($out, '0'), $dec) : $out;
    }

    // Isian yang sedang diedit (ala sel Excel): tanpa ribuan, tanpa nol di belakang, desimal ikut bahasa -- 1234,5.
    public function raw(?string $lang = null): string
    {
        $c = str_contains($this->canonical, '.') ? rtrim(rtrim($this->canonical, '0'), '.') : $this->canonical;

        return str_replace('.', Locale::spec($lang)['dec'], $c);
    }

    public function __toString(): string
    {
        return $this->canonical;
    }

    // "12." -> "12", ".5" -> "0.5", "007" -> "7", "-0" -> "0".
    private static function canon(string $s): string
    {
        $neg = str_starts_with($s, '-');
        [$int, $frac] = array_pad(explode('.', ltrim($s, '-'), 2), 2, '');
        $out = (ltrim($int, '0') ?: '0').($frac !== '' ? '.'.$frac : '');

        return ($neg && trim($out, '0.') !== '' ? '-' : '').$out;
    }
}
