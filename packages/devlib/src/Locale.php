<?php

namespace Devlib;

use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;

// Format lokal baku: zona waktu WIB, tanggal, angka, uang. Semua layar memakai ini -- jangan number_format/date() sendiri-sendiri.
// LOCKED user 2026-10-01: database & formula = English + angka mentah; bahasa lain HANYA tampilan layar/cetak.
// Aturan per bahasa (pemisah angka, locale Intl, singkatan bulan) = data: aplikasi mengisi lewat languagesUsing()
// (tmerp: sys_languages + kamus sys_dictionary); tanpa itu dipakai bawaan id/en di bawah.
final class Locale
{
    public const MONTHS_EN = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    private const LANGS = [
        'en' => ['name' => 'English', 'intl' => 'en-US', 'dec' => '.', 'grp' => ',', 'months' => self::MONTHS_EN],
        'id' => ['name' => 'Bahasa Indonesia', 'intl' => 'id-ID', 'dec' => ',', 'grp' => '.', 'months' => ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']],
    ];

    private static ?Closure $resolver = null;

    private static ?array $langs = null;

    private static ?Closure $userLang = null;

    // $fn(): array<code, ['name','intl','dec','grp','months'?]> -- dipanggil sekali per proses (lalu disimpan).
    public static function languagesUsing(?Closure $fn): void
    {
        self::$resolver = $fn;
        self::$langs = null;
    }

    public static function languages(): array
    {
        if (self::$langs === null) {
            $list = self::$resolver ? (self::$resolver)() : [];
            self::$langs = $list ?: config('devlib.languages', self::LANGS);
        }

        return self::$langs;
    }

    public static function spec(?string $lang = null): array
    {
        $all = self::languages();
        $s = $all[$lang ?? self::lang()] ?? $all['en'] ?? self::LANGS['en'];

        return $s + ['months' => self::MONTHS_EN, 'dec' => '.', 'grp' => ',', 'intl' => 'en-US'];
    }

    // $fn(): ?string -- bahasa milik user yang sedang login (tmerp: sec_user.lang_code). Pilihan bahasa ikut AKUN, bukan
    // perangkat: tanpa ini user yang pindah komputer (atau dua user di satu komputer) mendapat bahasa cookie orang lain.
    public static function langUsing(?Closure $fn): void
    {
        self::$userLang = $fn;
    }

    // Bahasa tampilan aktif. ?print_lang=xx (menu Print "Bahasa cetak") berlaku HANYA untuk permintaan itu,
    // lalu bahasa akun user yang login (langUsing), lalu cookie perangkat (tamu / halaman login, x-dl::lang-switch),
    // lalu bawaan aplikasi.
    public static function lang(): string
    {
        $req = request();
        $user = self::$userLang ? (self::$userLang)() : null;
        foreach ([$req?->query('print_lang'), $user, $req?->cookie('dl_lang') ?? ($_COOKIE['dl_lang'] ?? null)] as $v) {
            if (is_string($v) && isset(self::languages()[$v])) {
                return $v;
            }
        }

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
        return CarbonImmutable::parse($v ?? 'now')->setTimezone(self::timezone())->locale(self::lang());
    }

    // short: 23-Sep-2026 / 23-Okt-2026 (baku seluruh aplikasi dd-Mmm-yyyy; bulan 3 huruf ikut bahasa tampilan, user 2026-10-01)
    // long: Rabu, 23 September 2026 · numeric: 23/09/2026
    public static function date(DateTimeInterface|string|null $v = null, string $style = 'short', ?string $lang = null): string
    {
        if ($style === 'short') {
            $dv = $v === null ? DateValue::today() : DateValue::of($v);  // satu objek tanggal (DateValue)

            return $dv?->display($lang) ?? (string) $v;
        }
        $d = self::at($v);

        return $style === 'long' ? $d->locale($lang ?? self::lang())->translatedFormat('l, j F Y') : $d->format('d/m/Y');
    }

    public static function time(DateTimeInterface|string|null $v = null, bool $seconds = false): string
    {
        return self::at($v)->format($seconds ? 'H:i:s' : 'H:i').' '.self::tzLabel();
    }

    // Null-safe untuk isian/tampilan: kosong -> null (dipakai dengan ?? '—'). d = dd-Mmm-yyyy, dt = dd-Mmm-yyyy HH:mm.
    public static function d(DateTimeInterface|string|null $v): ?string
    {
        return $v === null || $v === '' ? null : self::date($v);
    }

    public static function dt(DateTimeInterface|string|null $v): ?string
    {
        return $v === null || $v === '' ? null : self::stamp($v);
    }

    // dd-Mmm-yyyy HH:mm (tanpa label zona, sama dgn stempel form/grid).
    public static function stamp(DateTimeInterface|string|null $v, string $empty = '—'): string
    {
        return DateTimeValue::of($v)?->display() ?? ($v === null || $v === '' ? $empty : (string) $v);
    }

    public static function dateTime(DateTimeInterface|string|null $v = null): string
    {
        return self::date($v).' '.self::time($v);
    }

    // Angka = objek NumberValue (input & tampilan); fungsi di bawah hanya jalan pintas.
    // Tampil (id 1.234,56 · en 1,234.56). $n boleh angka kanonik atau string kiriman form.
    public static function number(float|int|string|null $n, int $decimals = 0, ?string $lang = null): string
    {
        return (NumberValue::of($n ?? 0, $lang) ?? NumberValue::of(0))->display($decimals, $lang);
    }

    // Desimal secukupnya (maks $max, tanpa nol di belakang): 12,5 / 1.250.
    public static function decimal(float|int|string|null $n, int $max = 2, ?string $lang = null): string
    {
        return (NumberValue::of($n ?? 0, $lang) ?? NumberValue::of(0))->trimmed($max, $lang);
    }

    // Isian yang sedang diedit (ala sel Excel): 1234,5 / 1234.5.
    public static function raw(float|int|string|null $n, ?string $lang = null): string
    {
        return NumberValue::of($n, $lang)?->raw($lang) ?? '';
    }

    public static function money(float|int|string|null $n, string $currency = 'IDR', int $decimals = 0, ?string $lang = null): string
    {
        // Awalan = kode mata uang (IDR, USD), sama dengan master Currency -- bukan simbol (user 2026-10-05).
        return $currency.' '.self::number($n, $decimals, $lang);
    }

    public static function percent(float|int|string|null $n, int $decimals = 1, ?string $lang = null): string
    {
        return self::number($n, $decimals, $lang).'%';
    }

    // Kiriman form DevLib -> kanonik "1234.5" (null = kosong/bukan angka). Lihat NumberValue::of.
    public static function input(mixed $v, ?string $lang = null): ?string
    {
        return NumberValue::of($v, $lang)?->canonical;
    }

    // Ketikan manusia -> kanonik (NumberValue::parse, sama persis dengan DlNumber di core.js).
    public static function parse(mixed $v, ?string $lang = null): ?string
    {
        return NumberValue::parse($v, $lang)?->canonical;
    }

    // Aturan angka + bulan bahasa aktif untuk browser (dipasang x-dl::theme-boot sebagai window.DL_NUM).
    public static function jsSpec(): array
    {
        $s = self::spec();

        return ['lang' => self::lang(), 'intl' => $s['intl'], 'dec' => $s['dec'], 'grp' => $s['grp'], 'months' => array_values($s['months'])];
    }
}
