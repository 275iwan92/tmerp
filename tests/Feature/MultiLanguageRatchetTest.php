<?php

namespace Tests\Feature;

use App\Support\Localization\Dictionary;
use Devlib\Lang;
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
        'id_label' => 0,          // 2026-10-02: purchase-quotation (31 label terakhir) sudah English + kamus
        'number_format_id' => 0,
        'format_d_M_Y' => 0,
        'js_locale_id' => 0,
        'data_in_text_prop' => 0,  // 2026-10-02: dua terakhir (zona beku: prodev judul kartu ACC customer, inquiry-revise hint Data lama) dibungkus raw() atas izin user
        'js_id_text' => 43,     // sisa = separuh Indonesia tabel dua bahasa di core.js (LOGIN_T, AUTH_T, PRESET_T, kata bilangan) + 4 bukan teks layar (hash '#kode', atribut dev 'kolom', salah tangkap di grid.js). 42 -> 44 hanya karena daftar kata penjaga diperluas 2026-10-02; 44 -> 43: teks chat.js pindah ke T('ch_*'). Teks baru: T('kunci') + pasangan Devlib\Lang
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
            $c['data_in_text_prop'] += count(self::dataInTextProps($src));
        }
        foreach ($js as $f) {
            $src = preg_replace('#^\s*//.*$#m', '', $f->getContents());
            $c['js_locale_id'] += preg_match_all("/toLocale(?:String|DateString)\\('id-ID'/", $src);
            preg_match_all('/\'((?:[^\'\\\\\n]|\\\\.){3,})\'|`([^`\n]{3,})`/', $src, $m);
            foreach (array_merge($m[1], $m[2]) as $text) {
                $c['js_id_text'] += $text !== '' && Dictionary::looksIndonesian(preg_replace('/\$\{[^}]*\}|<[^>]*>/', ' ', $text)) ? 1 : 0;
            }
        }

        return $c;
    }

    // Prop teks x-dl::* yang MENYAMBUNG label English dengan nilai data tanpa raw(): 'Customer Order · '.$o->co_no. Komponen
    // menerjemahkan seluruh teks itu, jadi tiap dokumen yang dibuka mencatat satu baris kamus baru. Yang benar:
    // 'Customer Order · '.raw($o->co_no). Button / Badge tidak diterjemahkan; teks berbahasa Indonesia tidak dicatat kamus.
    /** @return list<string> */
    public static function dataInTextProps(string $src): array
    {
        $found = [];
        preg_match_all('/<x-dl::([\w.\-]+)((?:\s+(?:[^>"\']|"[^"]*"|\'[^\']*\')*?)?)\/?>/s', $src, $tags, PREG_SET_ORDER);
        foreach ($tags as [, $component, $attrs]) {
            if (in_array($component, ['button', 'badge'], true)) {
                continue;
            }
            preg_match_all('/\s:(?:label|title|subtitle|description|hint|placeholder|tooltip|message|text|caption|heading|add-label)="([^"]*)"/', $attrs, $m);
            foreach ($m[1] as $expr) {
                $literals = [];
                $code = preg_replace_callback("/'((?:[^'\\\\]|\\\\.)*)'/", function ($q) use (&$literals) {
                    $literals[] = $q[1];

                    return "''";
                }, $expr);
                if (str_contains($code, '$') && ! str_contains($code, 'raw(') && preg_match('/(?<!\d)\.(?!\d)/', $code) && ! Dictionary::looksIndonesian(implode(' ', $literals))) {
                    $found[] = $expr;
                }
            }
        }

        return $found;
    }

    // Setiap T('kunci') / Tf('kunci') di JS DevLib wajib punya pasangan [ID, EN] di Devlib\Lang (kalau tidak, kunci mentah tampil).
    public function test_every_js_translation_key_exists(): void
    {
        $keys = array_keys(Lang::all());
        $missing = [];
        foreach ((new Finder)->files()->in(base_path('packages/devlib/resources/assets/js'))->name('*.js') as $f) {
            preg_match_all("/\\bTf?\\('([a-z][\\w.]*)'/", $f->getContents(), $m);
            foreach (array_unique($m[1]) as $k) {
                if (! in_array($k, $keys, true)) {
                    $missing[] = $f->getFilename().': '.$k;
                }
            }
        }
        $this->assertSame([], $missing, 'Kunci T() tanpa pasangan di Devlib\Lang.');
    }

    public function test_multi_language_debt_never_grows(): void
    {
        $now = self::currentCounts();
        foreach (self::BASELINE as $key => $max) {
            $this->assertLessThanOrEqual($max, $now[$key],
                "'{$key}' naik ({$now[$key]} > {$max}). Tulis label English (x-dl::* otomatis diterjemahkan, teks lain t()/@t), angka & tanggal lewat Devlib\\Locale/NumberValue/DateValue, nilai data di prop teks dibungkus raw().");
            $this->assertSame($max, $now[$key], "'{$key}' turun ke {$now[$key]} (baseline {$max}) -- bagus. Turunkan BASELINE di MultiLanguageRatchetTest.");
        }
    }
}
