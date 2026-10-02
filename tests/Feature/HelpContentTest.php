<?php

namespace Tests\Feature;

use App\Support\V2\Grid\Grids;
use Tests\TestCase;

// Isi awal Help (resources/data/help/*.json) harus selalu lolos pemeriksa yang sama dengan `php artisan help:import`:
// dua bahasa, tiap teks paling panjang 255 karakter (kolom kamus), kunci unik. Tidak menyentuh database.
class HelpContentTest extends TestCase
{
    public function test_help_content_files_follow_the_format(): void
    {
        $files = glob(resource_path('data/help/*.json'));
        $this->assertNotEmpty($files);

        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(resource_path('data/help/_check.php')).' '.implode(' ', array_map('escapeshellarg', $files)), $out, $code);

        $this->assertSame(0, $code, implode("\n", $out));
    }

    public function test_every_grid_screen_has_a_help_topic(): void
    {
        $keys = [];
        foreach (glob(resource_path('data/help/*.json')) as $file) {
            foreach (json_decode(file_get_contents($file), true) as $topic) {
                $keys[$topic['key']] = true;
            }
        }
        $missing = array_values(array_filter(array_map(fn ($g) => $g->slug(), Grids::all()), fn ($slug) => ! isset($keys[$slug])));

        $this->assertSame([], $missing, 'Grid screens without a help topic in resources/data/help: '.implode(', ', $missing));
    }
}
