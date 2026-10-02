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
    // Layar v1 (user 2026-10-02: v1 ikut dikonversi): teks Indonesia mentah per modul. Konversi satu modul ke English + t()
    // (terjemahan Indonesianya diisi lewat migration Dictionary::seed) -> angka modul itu turun -> turunkan di sini.
    private const BASELINE_V1 = [
        // 2026-10-02 awal (total 5067).
        'app' => 2,
        'components' => 74,
        'layouts' => 4,
        'livewire' => 2,
        'pages' => 0,
        'pages/auth' => 2,
        'pages/careers' => 3,
        'pages/co' => 83,
        'pages/dispatch' => 69,
        'pages/finance' => 98,
        'pages/help' => 2,
        'pages/hrm' => 566,
        'pages/inquiry' => 644,
        'pages/inventory' => 0,
        'pages/lookup' => 0,
        'pages/master' => 691,
        'pages/notification' => 0,
        'pages/production' => 983,
        'pages/pur' => 2,
        'pages/quotation' => 93,
        'pages/recruitment' => 13,
        'pages/report' => 216,
        'pages/sales' => 9,
        'pages/security' => 158,
        'pages/self-service' => 226,
        'pages/setup' => 187,
    ];

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

    // Grup = pages/<modul> | components | livewire | layouts | app (app/Livewire + app/View).
    /** @return array<string, int> */
    public static function v1Counts(): array
    {
        $out = array_fill_keys(array_keys(self::BASELINE_V1), 0);
        $views = resource_path('views').'/';
        $files = (new Finder)->files()->in([$views.'pages', $views.'components', $views.'livewire', $views.'layouts', app_path('Livewire'), app_path('View')])->name('*.php');
        foreach ($files as $f) {
            $path = str_replace('\\', '/', $f->getPathname());
            if (str_starts_with($path, str_replace('\\', '/', $views))) {
                $rel = explode('/', substr($path, strlen($views)));
                $group = $rel[0] === 'pages' && count($rel) > 2 ? 'pages/'.$rel[1] : $rel[0];
            } else {
                $group = 'app';
            }
            $out[$group] = ($out[$group] ?? 0) + count(self::v1Texts($f->getContents()));
        }

        return $out;
    }

    // Teks tampil berbahasa Indonesia di sumber v1: isi tag, literal '...' / "..." (komentar diabaikan). Heuristik =
    // Dictionary::looksIndonesian (jangan ubah daftar katanya tanpa menghitung ulang BASELINE_V1).
    /** @return list<string> */
    public static function v1Texts(string $src): array
    {
        $src = preg_replace(['/\{\{--.*?--\}\}/s', '/<!--.*?-->/s', '#/\*.*?\*/#s', '#(?<![:"\'\w])//[^\n]*#'], ' ', $src);
        $out = [];
        $check = function (string $s) use (&$out) {
            $s = trim(html_entity_decode(preg_replace('/\{\{.*?\}\}|\{!!.*?!!\}/s', ' ', $s)));
            if ($s !== '' && Dictionary::looksIndonesian($s)) {
                $out[] = $s;
            }
        };
        preg_match_all('/>([^<>]*\p{L}[^<>]*)</u', $src, $m);
        foreach ($m[1] as $s) {
            if (! preg_match('/^\s*@(php|if|foreach|elseif|else|endif|endforeach|props|include)/', $s)) {
                $check($s);
            }
        }
        preg_match_all("/'((?:[^'\\\\\\n]|\\\\.)*\\p{L}(?:[^'\\\\\\n]|\\\\.)*)'/u", $src, $m);
        foreach ($m[1] as $s) {
            $check($s);
        }
        preg_match_all('/"([^"\n]*\p{L}[^"\n]*)"/u', $src, $m);
        foreach ($m[1] as $s) {
            if (! str_contains($s, '{{') && ! str_contains($s, '$') && ! str_contains($s, "'")) {
                $check($s);
            }
        }

        return $out;
    }

    public function test_v1_indonesian_text_never_grows(): void
    {
        $now = self::v1Counts();
        foreach ($now as $group => $n) {
            $max = self::BASELINE_V1[$group] ?? 0;
            $this->assertLessThanOrEqual($max, $n, "v1 '{$group}': teks Indonesia naik ({$n} > {$max}). Tulis English + t()/@t(), terjemahan lewat Dictionary::seed di migration.");
            $this->assertSame($max, $n, "v1 '{$group}' turun ke {$n} (baseline {$max}) -- bagus. Turunkan BASELINE_V1 di MultiLanguageRatchetTest.");
        }
    }

    // Kamus kunci lama dipensiunkan 2026-10-02: Translator::t('kunci') tidak boleh kembali.
    public function test_no_legacy_translation_keys(): void
    {
        $hits = [];
        foreach ((new Finder)->files()->in([resource_path('views'), app_path()])->name('*.php') as $f) {
            if (preg_match('/Translator::t\(|SysTranslation::forLang\(/', $f->getContents())) {
                $hits[] = $f->getRelativePathname();
            }
        }
        $this->assertSame([], $hits, 'Pakai t(\'English\') + kamus sys_dictionary, bukan Translator::t(\'kunci\').');
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
