<?php

namespace MockupParts;

use Devlib\Components\DevlibComponent;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

// Komponen khusus & turunan layar (mockup.test, nanti erptest/tmerp): <x-mp::nama>.
// Semua kelasnya turunan objek DevLib; aset disajikan dari paket seperti DevLib (tanpa build).
class MockupPartsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mp');
        Blade::componentNamespace('MockupParts\\Components', 'mp');
        DevlibComponent::family('MockupParts\\Components', 'x-mp::', __DIR__.'/../resources/views');

        Route::get('/_mp/{file}', function (string $file) {
            if ($file === 'mockup-parts.js') {
                return response(self::bundle(), 200, ['Content-Type' => 'application/javascript', 'Cache-Control' => 'no-cache']);
            }
            $root = realpath(__DIR__.'/../resources/assets');
            $path = realpath($root.'/'.$file) ?: realpath($root.'/css/'.$file);
            abort_unless($path && str_starts_with($path, $root.DIRECTORY_SEPARATOR), 404);

            return response()->file($path, ['Content-Type' => str_ends_with($file, '.css') ? 'text/css' : 'application/javascript', 'Cache-Control' => 'no-cache']);
        })->where('file', '[A-Za-z0-9._-]+')->name('mp.asset');
    }

    public static function asset(string $file): string
    {
        $root = __DIR__.'/../resources/assets/';
        $v = $file === 'mockup-parts.js' ? max(array_map('filemtime', self::scripts())) : (is_file($root.$file) ? filemtime($root.$file) : (is_file($root.'css/'.$file) ? filemtime($root.'css/'.$file) : 0));

        return route('mp.asset', ['file' => $file]).'?v='.$v;
    }

    // Satu kelas JS per file, termasuk subfolder per modul (components/sales/*.js).
    public static function scripts(): array
    {
        $dir = __DIR__.'/../resources/assets/js/components';
        $files = [...glob($dir.'/*.js'), ...glob($dir.'/*/*.js')];
        sort($files);

        return $files;
    }

    public static function bundle(): string
    {
        return implode("\n", array_map(fn ($f) => '/* ---- '.basename($f)." ---- */\n".file_get_contents($f), self::scripts()));
    }
}
