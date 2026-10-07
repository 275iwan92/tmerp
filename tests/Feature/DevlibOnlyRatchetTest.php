<?php

namespace Tests\Feature;

use Devlib\Components\DevlibComponent;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

// Layar v2 (mockup.test dan tmerp.test, kode yang sama) = turunan objek DevLib (user 2026-10-02): isi layar ditulis dengan
// x-dl::* (dan x-mp::*, bagian khusus yang juga turunan DevlibComponent), bukan HTML mentah atau komponen v1.
// Angka di BASELINE = sisa yang belum diubah; TIDAK BOLEH NAIK. Turun -> turunkan BASELINE ke angka baru.
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
        'raw_table' => 2,         // product-acc: lembar ACC / Proof Digital (zona beku ProDev)
        'style_block' => 1,       // share.blade.php: tema untuk halaman publik
        'inline_style' => 2,
        'v1_component' => 0,      // x-ui.* / x-form.* / x-grid.* / livewire di layar v2
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
    ];

    /** @return array<string, int> */
    public static function currentCounts(): array
    {
        $counts = array_fill_keys(array_keys(self::PATTERNS), 0);
        foreach ((new Finder)->files()->in(resource_path('views/v2'))->name('*.blade.php') as $file) {
            $src = preg_replace('/\{\{--.*?--\}\}/s', '', $file->getContents());
            foreach (self::PATTERNS as $key => $re) {
                $counts[$key] += preg_match_all($re, $src);
            }
        }

        return $counts;
    }

    public function test_raw_markup_in_v2_screens_never_grows(): void
    {
        $now = self::currentCounts();
        foreach (self::BASELINE as $key => $max) {
            $this->assertLessThanOrEqual($max, $now[$key], "'{$key}' naik ({$now[$key]} > {$max}). Layar v2 memakai objek DevLib (x-dl::*): form kecil = x-dl::action-form, tombol = x-dl::button, tabel = x-dl::table / x-dl::grid.");
            $this->assertSame($max, $now[$key], "'{$key}' turun ke {$now[$key]} (baseline {$max}) -- bagus. Turunkan BASELINE di DevlibOnlyRatchetTest.");
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
