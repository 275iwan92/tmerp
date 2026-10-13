<?php

/*
 * REMARK
 * Tujuan       : Penjaga ratchet "layar v2 = objek DevLib": tes GAGAL bila layar baru / yang diubah memakai markup mentah, warna literal,
 *                inline style, atau kelas lokal di luar objek DevLib, atau bila CSS lokal menambah warna literal di luar token.
 * Sumber hulu  : berkas Blade di resources/views/v2, berkas CSS di folder packages dan resources/css, komponen paket DevLib / mockup-parts.
 * Input        : -
 * Output       : Lulus bila tiap hitungan sama dengan BASELINE; naik = gagal (pakai / extends objek DevLib), turun = gagal sampai BASELINE diturunkan.
 * Dipakai oleh : php artisan test (CI lokal); aturan "CHECK DevLib dulu" di CLAUDE.md.
 * Status       : Aktif.
 */

namespace Tests\Feature;

use Devlib\Components\DevlibComponent;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

// Layar v2 (mockup.test dan tmerp.test, kode yang sama) = turunan objek DevLib (user 2026-10-02): isi layar ditulis dengan
// x-dl::* (dan x-mp::*, bagian khusus yang juga turunan DevlibComponent), bukan HTML mentah atau komponen v1.
// Angka di BASELINE = sisa yang belum diubah; TIDAK BOLEH NAIK. Turun -> turunkan BASELINE ke angka baru.
// Diperluas 2026-10-13 (user: "UBAH HARUS PAKAI OBJECT ... DAN BUAT AGAR INI TIDAK TERJADI LAGI"): tag struktural mentah, warna literal,
// kelas lokal (bukan dl- / fa), dan warna literal di CSS lokal. Perlu gaya baru? Tulis / ubah OBJEK atau TOKEN di DevLib, bukan di layar.
class DevlibOnlyRatchetTest extends TestCase
{
    private const BASELINE = [
        // 2026-10-02: 65 form kecil sudah x-dl::action-form (zona beku atas izin user). 2026-10-08: layar Maintenance dihapus,
        // sisa terakhir (maintenance-my-work) ikut hilang.
        'raw_form' => 0,
        'raw_button' => 1,        // share.blade.php: halaman publik tanpa devlib.js
        'raw_visible_input' => 0,
        'raw_select' => 0,
        'raw_textarea' => 0,
        'raw_table' => 0,         // lembar ACC / Proof Digital = objek x-mp::sales.proof-sheet
        'style_block' => 1,       // share.blade.php: tema untuk halaman publik
        'inline_style' => 1,
        'v1_component' => 0,      // x-ui.* / x-form.* / x-grid.* / livewire di layar v2
        'raw_tag' => 86,           // div / span / p / ul / li / h1-h6 / a / img / svg ... di layar (bukan objek DevLib)
        'color_literal' => 0,     // #hex / rgb() / hsl() di markup layar (warna = token DevLib)
        'local_class' => 13,       // class="..." selain dl-* dan ikon fa-* di layar
    ];

    // Warna literal di CSS lokal (di luar definisi token --x:). Berkas yang tidak terdaftar = baseline 0.
    // devlib.css tidak dihitung: itu rumah token dan objek, tempat warna memang ditulis.
    private const CSS_BASELINE = [
        'packages/mockup-parts/resources/assets/css/mockup-parts.css' => 34,
        'packages/mockup-parts/resources/assets/css/prodev.css' => 214,
        'resources/css/app.css' => 82,
        'resources/css/ledger.css' => 42,
    ];

    private const PATTERNS = [
        'raw_form' => '/<form\b/',
        'raw_button' => '/<button\b/',
        'raw_visible_input' => '/<input\b(?![^>]*type="hidden")/',
        'raw_select' => '/<select\b/',
        'raw_textarea' => '/<textarea\b/',
        'raw_table' => '/<table\b/',
        'style_block' => '/<style\b/',
        'inline_style' => '/\sstyle="/',
        'v1_component' => '/<x-(ui|form|grid|print|gantt|kanban)[.\-]|<livewire:|@livewire\(/',
        'raw_tag' => '/<(?:div|span|p|ul|ol|li|h[1-6]|thead|tbody|tr|td|th|a|img|section|article|nav|header|footer|aside|svg|canvas|iframe|label|dl|dt|dd|b|strong|em|small|pre|code|details|summary)\b/i',
        'color_literal' => '/(?<![\w&#])#(?:[0-9a-fA-F]{6}|[0-9a-fA-F]{3})\b|\brgba?\(|\bhsla?\(/',
    ];

    /** @return array<string, int> */
    public static function currentCounts(): array
    {
        $counts = array_fill_keys(array_keys(self::BASELINE), 0);
        foreach ((new Finder)->files()->in(resource_path('views/v2'))->name('*.blade.php') as $file) {
            $src = preg_replace('/\{\{--.*?--\}\}/s', '', $file->getContents());
            foreach (self::PATTERNS as $key => $re) {
                $counts[$key] += preg_match_all($re, $src);
            }
            $counts['local_class'] += self::localClasses($src);
        }

        return $counts;
    }

    // Kelas yang ditulis layar sendiri: semua token class="..." selain dl-* (objek DevLib), fa* (ikon), dan ekspresi Blade.
    private static function localClasses(string $src): int
    {
        $n = 0;
        preg_match_all('/\bclass="([^"]*)"/', $src, $m);
        foreach ($m[1] as $attr) {
            foreach (preg_split('/\s+/', trim($attr), -1, PREG_SPLIT_NO_EMPTY) as $class) {
                if (! preg_match('/^(?:dl-|fa)|[{@$]/', $class)) {
                    $n++;
                }
            }
        }

        return $n;
    }

    /** @return array<string, int> relatif-path berkas CSS => jumlah warna literal di luar token */
    public static function cssColorCounts(): array
    {
        $out = [];
        $finder = (new Finder)->files()->in([base_path('packages'), resource_path('css')])->name('*.css')->notName('*.min.css')->notName('devlib.css')->notPath('vendor')->notPath('node_modules');
        foreach ($finder as $file) {
            $css = preg_replace('!/\*.*?\*/!s', '', $file->getContents());
            $n = 0;
            preg_match_all('/([^{}@][^{}]*)\{([^{}]*)\}/', $css, $rules);
            foreach ($rules[2] as $declarations) {
                foreach (explode(';', $declarations) as $declaration) {
                    if (! str_contains($declaration, ':') || str_starts_with(trim($declaration), '--')) {
                        continue;
                    }
                    $n += preg_match_all('/#(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{3,4})\b|\brgba?\([^)]*\)|\bhsla?\([^)]*\)/', explode(':', $declaration, 2)[1]);
                }
            }
            $out[str_replace('\\', '/', ltrim(str_replace(base_path(), '', $file->getRealPath()), '\\/'))] = $n;
        }

        return $out;
    }

    public function test_raw_markup_in_v2_screens_never_grows(): void
    {
        $now = self::currentCounts();
        foreach (self::BASELINE as $key => $max) {
            $this->assertLessThanOrEqual($max, $now[$key], "'{$key}' naik ({$now[$key]} > {$max}). Layar v2 memakai objek DevLib (x-dl::*): form kecil = x-dl::action-form, tombol = x-dl::button, tabel = x-dl::table / x-dl::grid.");
            $this->assertSame($max, $now[$key], "'{$key}' turun ke {$now[$key]} (baseline {$max}) -- bagus. Turunkan BASELINE di DevlibOnlyRatchetTest.");
        }
    }

    // Warna di CSS lokal (mockup-parts, v1) tidak boleh bertambah: warna = token --dl-* di DevLib, bukan override per layar (mis. .mp-wo-legible).
    public function test_local_css_colors_never_grow(): void
    {
        foreach (self::cssColorCounts() as $file => $now) {
            $max = self::CSS_BASELINE[$file] ?? 0;
            $this->assertLessThanOrEqual($max, $now, "{$file}: warna literal naik ({$now} > {$max}). Pakai token --dl-* atau tulis objek / token di devlib.css.");
            $this->assertSame($max, $now, "{$file}: warna literal turun ke {$now} (baseline {$max}) -- bagus. Turunkan CSS_BASELINE di DevlibOnlyRatchetTest.");
        }
    }

    // Semua komponen x-dl::* dan x-mp::* adalah turunan satu induk (DevlibComponent): hak akses `can`, terjemahan label,
    // dan tanda asal-usul objek (Mode Developer) berlaku untuk semuanya.
    public function test_every_devlib_and_mockup_part_component_descends_from_devlib_component(): void
    {
        $orphans = [];
        foreach (['Devlib\\Components' => base_path('packages/devlib/src/Components'), 'MockupParts\\Components' => base_path('packages/mockup-parts/src/Components')] as $ns => $dir) {
            foreach ((new Finder)->files()->in($dir)->name('*.php') as $f) {
                $class = $ns.'\\'.str_replace(['/', '.php'], ['\\', ''], str_replace('\\', '/', $f->getRelativePathname()));
                if (class_exists($class) && ! (new ReflectionClass($class))->isSubclassOf(DevlibComponent::class) && $class !== DevlibComponent::class) {
                    $orphans[] = $class;
                }
            }
        }
        $this->assertSame([], $orphans, 'Komponen ini bukan turunan Devlib\Components\DevlibComponent.');
    }
}
