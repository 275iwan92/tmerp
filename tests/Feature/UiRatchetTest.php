<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

// Ratchet migrasi komponen (docs/componentstandard.md §5): jumlah pola HTML
// mentah di halaman TIDAK BOLEH NAIK. Halaman baru wajib pakai x-ui.* / x-form.*
// / x-grid.*. Kalau test ini gagal karena angkanya TURUN (hasil migrasi),
// turunkan BASELINE ke angka baru -- jangan pernah menaikkannya.
class UiRatchetTest extends TestCase
{
    private const BASELINE = [
        'raw_button' => 778,
        'hex_color' => 4519,
        'hand_modal' => 49,
        'raw_table' => 197,
        'inline_style_block' => 13,
    ];

    private const PATTERNS = [
        'raw_button' => '/<button\b/',
        'hex_color' => '/#[0-9a-fA-F]{6}\b/',
        'hand_modal' => '/fixed inset-0/',
        'raw_table' => '/<table\b/',
        'inline_style_block' => '/<style\b/',
    ];

    /** @return array<string, int> */
    public static function currentCounts(): array
    {
        $counts = array_fill_keys(array_keys(self::PATTERNS), 0);

        foreach ((new Finder)->files()->in(resource_path('views/pages'))->name('*.blade.php') as $file) {
            $src = $file->getContents();
            foreach (self::PATTERNS as $key => $re) {
                $counts[$key] += preg_match_all($re, $src);
            }
        }

        return $counts;
    }

    public function test_raw_markup_never_grows(): void
    {
        $now = self::currentCounts();

        foreach (self::BASELINE as $key => $max) {
            $this->assertLessThanOrEqual($max, $now[$key],
                "Pola mentah '{$key}' naik ({$now[$key]} > {$max}). Pakai komponen x-ui.*/x-form.*/x-grid.* -- lihat docs/componentstandard.md.");
            $this->assertSame($max, $now[$key],
                "Pola '{$key}' turun ke {$now[$key]} (baseline {$max}) -- bagus. Turunkan BASELINE di UiRatchetTest ke {$now[$key]}.");
        }
    }
}
