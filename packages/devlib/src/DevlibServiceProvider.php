<?php

namespace Devlib;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

// Satu pintu library: komponen dipanggil sebagai <x-dl::nama>, aset (CSS/JS)
// disajikan langsung dari paket -- aplikasi pemakai tidak menyalin apa pun,
// jadi ubah di paket = semua aplikasi pemakai ikut.
class DevlibServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'devlib');
        Blade::componentNamespace('Devlib\\Components', 'dl');

        Route::get('/_devlib/{file}', function (string $file) {
            $path = realpath(__DIR__.'/../resources/assets/'.$file);
            $root = realpath(__DIR__.'/../resources/assets');
            abort_unless($path && str_starts_with($path, $root.DIRECTORY_SEPARATOR), 404);

            $type = str_ends_with($file, '.css') ? 'text/css' : 'application/javascript';

            return response()->file($path, ['Content-Type' => $type, 'Cache-Control' => 'no-cache']);
        })->where('file', '[A-Za-z0-9._-]+')->name('devlib.asset');
    }

    // URL aset + versi dari waktu ubah file, supaya perubahan library langsung terlihat.
    public static function asset(string $file): string
    {
        $v = @filemtime(__DIR__.'/../resources/assets/'.$file) ?: 0;

        return route('devlib.asset', ['file' => $file]).'?v='.$v;
    }
}
