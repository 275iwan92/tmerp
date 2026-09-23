<?php

namespace Devlib;

// Terbilang (angka -> kata), ID & EN, dengan nama mata uang. Satu kamus untuk PHP (cetak nota)
// dan JS (Devlib.words, saat mengetik) -- JS menerima CURRENCIES lewat atribut data.
//   Terbilang::words(1200)               -> "Seribu dua ratus"
//   Terbilang::words(1200, 'IDR')        -> "Seribu dua ratus rupiah"
//   Terbilang::words('12.50', 'USD')     -> "Dua belas dolar Amerika Serikat dan lima puluh sen"
//   Terbilang::words(12.5)               -> "Dua belas koma lima"
final class Terbilang
{
    // kode => [simbol, nama ID, nama EN, sub-satuan ID, sub-satuan EN, jumlah desimal]
    public const CURRENCIES = [
        'IDR' => ['Rp', 'rupiah', 'rupiah', 'sen', 'sen', 0],
        'USD' => ['$', 'dolar Amerika Serikat', 'US dollars', 'sen', 'cents', 2],
        'EUR' => ['€', 'euro', 'euros', 'sen', 'cents', 2],
        'SGD' => ['S$', 'dolar Singapura', 'Singapore dollars', 'sen', 'cents', 2],
        'MYR' => ['RM', 'ringgit Malaysia', 'Malaysian ringgit', 'sen', 'sen', 2],
        'AUD' => ['A$', 'dolar Australia', 'Australian dollars', 'sen', 'cents', 2],
        'JPY' => ['¥', 'yen Jepang', 'Japanese yen', '', '', 0],
        'CNY' => ['CN¥', 'yuan Tiongkok', 'Chinese yuan', 'fen', 'fen', 2],
        'GBP' => ['£', 'pound sterling', 'pounds sterling', 'pence', 'pence', 2],
    ];

    private const ID = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
    private const EN = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
    private const EN_TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

    public static function words(int|float|string $n, ?string $currency = null, ?string $lang = null): string
    {
        $lang = $lang ?? Locale::lang();
        $s = is_string($n) ? trim($n) : (is_float($n) ? rtrim(rtrim(number_format($n, 6, '.', ''), '0'), '.') : (string) $n);
        $neg = str_starts_with($s, '-');
        [$int, $frac] = array_pad(explode('.', ltrim($s, '-+')), 2, '');
        $int = ltrim(preg_replace('/\D/', '', $int), '0');
        $frac = preg_replace('/\D/', '', $frac);
        $c = $currency ? (self::CURRENCIES[strtoupper($currency)] ?? null) : null;
        $say = fn (int $x) => $lang === 'en' ? self::en($x) : self::id($x);

        $out = $int === '' ? ($lang === 'en' ? 'zero' : 'nol') : $say((int) $int);
        if ($c) {
            $out .= ' '.($lang === 'en' ? $c[2] : $c[1]);
            $sub = $c[5] > 0 ? (int) substr(str_pad($frac, $c[5], '0'), 0, $c[5]) : 0;
            if ($sub > 0) {
                $out .= ($lang === 'en' ? ' and ' : ' dan ').$say($sub).' '.($lang === 'en' ? $c[4] : $c[3]);
            }
        } elseif (rtrim($frac, '0') !== '') {
            $digits = array_map(fn ($d) => $d === '0' ? ($lang === 'en' ? 'zero' : 'nol') : $say((int) $d), str_split(rtrim($frac, '0')));
            $out .= ($lang === 'en' ? ' point ' : ' koma ').implode(' ', $digits);
        }

        return ucfirst(($neg ? 'minus ' : '').$out);
    }

    private static function id(int $n): string
    {
        return match (true) {
            $n < 12 => self::ID[$n],
            $n < 20 => self::ID[$n - 10].' belas',
            $n < 100 => trim(self::ID[intdiv($n, 10)].' puluh '.self::ID[$n % 10]),
            $n < 200 => trim('seratus '.self::id($n - 100)),
            $n < 1000 => trim(self::ID[intdiv($n, 100)].' ratus '.self::id($n % 100)),
            $n < 2000 => trim('seribu '.self::id($n - 1000)),
            default => self::scale($n, [[10 ** 15, 'kuadriliun'], [10 ** 12, 'triliun'], [10 ** 9, 'miliar'], [10 ** 6, 'juta'], [1000, 'ribu']], [self::class, 'id']),
        };
    }

    private static function en(int $n): string
    {
        return match (true) {
            $n < 20 => self::EN[$n],
            $n < 100 => self::EN_TENS[intdiv($n, 10)].($n % 10 ? '-'.self::EN[$n % 10] : ''),
            $n < 1000 => trim(self::EN[intdiv($n, 100)].' hundred '.self::en($n % 100)),
            default => self::scale($n, [[10 ** 15, 'quadrillion'], [10 ** 12, 'trillion'], [10 ** 9, 'billion'], [10 ** 6, 'million'], [1000, 'thousand']], [self::class, 'en']),
        };
    }

    private static function scale(int $n, array $scales, callable $say): string
    {
        foreach ($scales as [$d, $word]) {
            if ($n >= $d) {
                return trim($say(intdiv($n, $d)).' '.$word.' '.$say($n % $d));
            }
        }

        return '';
    }
}
