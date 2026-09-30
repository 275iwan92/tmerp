<?php

namespace Tests\Feature;

use App\Support\Localization\Dictionary;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

// Penjaga multi-bahasa (LOCKED user 2026-10-01: DB & formula English, teks lain lewat kamus sys_dictionary).
// Layar v2 + objek DevLib TIDAK BOLEH menambah: label Indonesia mentah, format angka/tanggal buatan sendiri.
// Tulis label ENGLISH (komponen x-dl::* menerjemahkan otomatis; teks lain pakai t() / @t()), angka/tanggal lewat
// Devlib\Locale / NumberValue / DateValue (JS: Devlib.h.fmt / DlNumber / DlDate).
// Angka TURUN (hasil migrasi label ke English) -> turunkan BASELINE ke angka baru; jangan pernah menaikkannya.
class MultiLanguageRatchetTest extends TestCase
{
    private const BASELINE = [
        'id_label' => 687,        // label Indonesia mentah lama (2026-10-01) -- dipindah ke English bertahap (backlog09 §13)
        'number_format_id' => 0,
        'format_d_M_Y' => 0,
        'js_locale_id' => 0,
    ];

    /** @return array<string, int> */
    public static function currentCounts(): array
    {
        $php = (new Finder)->files()->in([resource_path('views/v2'), app_path('Support/V2'), base_path('packages/devlib/src'), base_path('packages/devlib/resources/views')])
            ->name(['*.php']);
        $js = (new Finder)->files()->in(base_path('packages/devlib/resources/assets/js'))->name('*.js');
        $c = array_fill_keys(array_keys(self::BASELINE), 0);

        foreach ($php as $f) {
            $src = $f->getContents();
            $c['number_format_id'] += preg_match_all("/number_format\\([^;]*?,\\s*'[,.]',\\s*'[.,]'\\s*\\)/", $src);
            $c['format_d_M_Y'] += preg_match_all("/->format\\('d-M-Y/", $src);
            preg_match_all("/(?:\\b(?:label|title|placeholder|hint|description|message|text|search)=\"([^\"{}\$]+)\"|'(?:label|title|placeholder|hint|description|message|search)'\\s*=>\\s*'([^'{}]+)')/u", $src, $m);
            foreach (array_merge($m[1], $m[2]) as $text) {
                $c['id_label'] += $text !== '' && Dictionary::looksIndonesian($text) ? 1 : 0;
            }
        }
        foreach ($js as $f) {
            $c['js_locale_id'] += preg_match_all("/toLocale(?:String|DateString)\\('id-ID'/", $f->getContents());
        }

        return $c;
    }

    public function test_multi_language_debt_never_grows(): void
    {
        $now = self::currentCounts();
        foreach (self::BASELINE as $key => $max) {
            $this->assertLessThanOrEqual($max, $now[$key],
                "'{$key}' naik ({$now[$key]} > {$max}). Tulis label English (x-dl::* otomatis diterjemahkan, teks lain t()/@t), angka & tanggal lewat Devlib\\Locale/NumberValue/DateValue.");
            $this->assertSame($max, $now[$key], "'{$key}' turun ke {$now[$key]} (baseline {$max}) -- bagus. Turunkan BASELINE di MultiLanguageRatchetTest.");
        }
    }
}
