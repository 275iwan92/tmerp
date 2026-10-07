<?php

namespace Devlib;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

// Satu pintu library: komponen dipanggil sebagai <x-dl::nama>, aset (CSS/JS)
// disajikan langsung dari paket -- aplikasi pemakai tidak menyalin apa pun,
// jadi ubah di paket = semua aplikasi pemakai ikut.
class DevlibServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once __DIR__.'/helpers.php';  // raw() -- nilai data di prop teks x-dl::*
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'devlib');
        Blade::componentNamespace('Devlib\\Components', 'dl');

        Route::get('/_devlib/{file}', function (string $file) {
            if ($file === 'devlib.js') {
                return response(self::bundle(), 200, ['Content-Type' => 'application/javascript', 'Cache-Control' => 'no-cache']);
            }
            $path = realpath(__DIR__.'/../resources/assets/'.$file);
            $root = realpath(__DIR__.'/../resources/assets');
            abort_unless($path && str_starts_with($path, $root.DIRECTORY_SEPARATOR), 404);

            $type = str_ends_with($file, '.css') ? 'text/css' : 'application/javascript';

            return response()->file($path, ['Content-Type' => $type, 'Cache-Control' => 'no-cache']);
        })->where('file', '[A-Za-z0-9._-]+')->name('devlib.asset');

        // Lookup remote: cari ke server (data besar). Middleware diatur aplikasi pemakai (config devlib.lookup_middleware).
        Route::middleware(config('devlib.lookup_middleware', ['web']))
            ->get('/_devlib-lookup/{name}', fn (Request $r, string $name) => response()->json(
                Lookup::search($name, (string) $r->query('q', ''), $r->except('q'))))
            ->where('name', '[A-Za-z0-9_-]+')->name('devlib.lookup');
    }

    // URL aset + versi dari waktu ubah file, supaya perubahan library langsung terlihat.
    public static function asset(string $file): string
    {
        $v = $file === 'devlib.js'
            ? max(array_map('filemtime', self::scripts()))
            : (@filemtime(__DIR__.'/../resources/assets/'.$file) ?: 0);

        return route('devlib.asset', ['file' => $file]).'?v='.$v;
    }

    // Perilaku JS = satu kelas per file (resources/assets/js): core (kelas induk + helper) -> komponen -> boot.
    // Disajikan sebagai satu devlib.js supaya pemakai cukup memuat satu file dan tidak ada langkah build.
    public static function scripts(): array
    {
        $dir = __DIR__.'/../resources/assets/js';
        $components = glob($dir.'/components/*.js');
        sort($components);

        return [$dir.'/core.js', ...$components, $dir.'/boot.js'];
    }

    public static function bundle(): string
    {
        return implode("\n", array_map(fn ($f) => "/* ---- ".basename($f)." ---- */\n".file_get_contents($f), self::scripts()));
    }
}
