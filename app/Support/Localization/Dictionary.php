<?php

namespace App\Support\Localization;

use Devlib\Locale;
use Illuminate\Support\Facades\DB;

// Kamus penerjemah (LOCKED user 2026-10-01): database & formula English; teks lain HANYA saat tampil (layar/cetak).
// t('Net 30', 'tm2mst.fin_payment_term.term_label') -> "Bayar 30 Hari" bila bahasa tampilan id; tidak ada terjemahan
// -> English apa adanya. Sumber: tm2sys.sys_dictionary (context + source_en) + sys_dictionary_lang (per bahasa).
// Beda dari Translator (kamus kunci v1, sys_translations): di sini kuncinya = teks English itu sendiri.
final class Dictionary
{
    private static array $cache = [];

    public static function t(?string $en, string $context = 'ui', ?string $lang = null): string
    {
        $lang ??= Locale::lang();
        if ($en === null || $en === '' || $lang === 'en') {
            return (string) $en;
        }

        return self::context($context, $lang)[$en] ?? $en;
    }

    // source_en => teks, satu konteks satu bahasa (sekali query per permintaan).
    public static function context(string $context, string $lang): array
    {
        return self::$cache[$lang][$context] ??= self::load($context, $lang);
    }

    // Aturan tampil per bahasa untuk Devlib\Locale: sys_languages (pemisah angka, locale Intl) + bulan dari kamus.
    // Tabel belum ada (mis. DB lama) -> [] = Locale memakai bawaan id/en.
    public static function languages(): array
    {
        try {
            $rows = DB::connection('tm2sys')->table('sys_languages')->get();
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows->sortBy(fn ($r) => $r->sort_order ?? $r->id_language) as $r) {
            if (isset($r->activate) && ! $r->activate) {
                continue;
            }
            $mon = $r->code === 'en' ? [] : self::context('date.month_short', $r->code);
            $out[$r->code] = ['name' => $r->native_name ?? $r->name, 'intl' => $r->intl_locale ?? ($r->code === 'id' ? 'id-ID' : 'en-US'),
                'dec' => $r->decimal_separator ?: '.', 'grp' => $r->thousand_separator ?? ',',
                'months' => array_map(fn ($m) => $mon[$m] ?? $m, Locale::MONTHS_EN)];
        }

        return $out;
    }

    public static function flush(): void
    {
        self::$cache = [];
        Locale::languagesUsing(fn () => self::languages());
    }

    private static function load(string $context, string $lang): array
    {
        try {
            return DB::connection('tm2sys')->table('sys_dictionary as d')
                ->join('sys_dictionary_lang as l', 'l.id_dictionary', '=', 'd.id_dictionary')
                ->where('d.context', $context)->where('d.activate', 1)->where('l.lang_code', $lang)
                ->pluck('l.text', 'd.source_en')->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
