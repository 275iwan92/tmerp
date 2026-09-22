<?php

namespace App\Support\Text;

// "Terbilang" (nominal dalam huruf) -- kolom wajib di nota/faktur Indonesia dan
// ada di CO legacy ScriptCase (sdi_co.terbilang) tapi hilang di V2. Ditulis
// sendiri, bukan lewat package: aturan bahasa Indonesia cuma butuh belasan
// baris dan "seratus/seribu" (bukan "satu ratus/satu ribu") adalah kekhasan
// yang tetap harus ditangani manual di library mana pun.
class Terbilang
{
    private const SATUAN = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    public static function rupiah(float $amount): string
    {
        $rupiah = (int) floor(abs($amount));
        $sen = (int) round((abs($amount) - $rupiah) * 100);

        $text = ($rupiah === 0 ? 'nol' : self::clean(self::convert($rupiah))).' rupiah';

        if ($sen > 0) {
            $text .= ' '.self::clean(self::convert($sen)).' sen';
        }

        if ($amount < 0) {
            $text = 'minus '.$text;
        }

        return ucfirst($text);
    }

    private static function clean(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    public static function convert(int $n): string
    {
        if ($n === 0) {
            return '';
        }

        if ($n < 12) {
            return ' '.self::SATUAN[$n];
        }

        if ($n < 20) {
            return self::convert($n - 10).' belas';
        }

        if ($n < 100) {
            return self::convert(intdiv($n, 10)).' puluh'.self::convert($n % 10);
        }

        if ($n < 200) {
            return ' seratus'.self::convert($n - 100);
        }

        if ($n < 1000) {
            return self::convert(intdiv($n, 100)).' ratus'.self::convert($n % 100);
        }

        if ($n < 2000) {
            return ' seribu'.self::convert($n - 1000);
        }

        if ($n < 1_000_000) {
            return self::convert(intdiv($n, 1000)).' ribu'.self::convert($n % 1000);
        }

        if ($n < 1_000_000_000) {
            return self::convert(intdiv($n, 1_000_000)).' juta'.self::convert($n % 1_000_000);
        }

        if ($n < 1_000_000_000_000) {
            return self::convert(intdiv($n, 1_000_000_000)).' miliar'.self::convert($n % 1_000_000_000);
        }

        return self::convert(intdiv($n, 1_000_000_000_000)).' triliun'.self::convert($n % 1_000_000_000_000);
    }
}
