<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

// Objek form khusus (packages/mockup-parts, x-mp::*) dimuat langsung dari foldernya, tanpa `composer dump-autoload`
// (user 2026-10-05: harus otomatis). Kelas baru di paket itu langsung terbaca begitu berkasnya ada.
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'MockupParts\\')) {
        $file = dirname(__DIR__, 2).'/packages/mockup-parts/src/'.str_replace('\\', '/', substr($class, 12)).'.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['devlib.auth' => \App\Http\Middleware\DevlibAuth::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
