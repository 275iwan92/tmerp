<?php

use App\Http\Middleware\EnsureIsAdmin;
use App\Http\Middleware\EnsureModuleAccess;
use App\Http\Middleware\EnsurePortalCandidateAuthenticated;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\SetUserLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'module' => EnsureModuleAccess::class,
            'admin' => EnsureIsAdmin::class,
            'force.password' => ForcePasswordChange::class,
            'portal.candidate' => EnsurePortalCandidateAuthenticated::class,
            'locale' => SetUserLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            // v2: tombol form memanggil server lewat fetch (Accept: JSON) -> error validasi harus 422 JSON, bukan redirect.
            fn (Request $request) => $request->is('api/*') || ($request->is('v2/*') && $request->expectsJson()),
        );
    })->create();

// Satu kode, dua situs: mockup.test (Build & UAT) memakai .env.mockup -> database dummy ddt*; tmerp.test tetap .env
// (tm2* real). Artisan untuk situs dummy: php artisan ... --env=mockup (Laravel membaca .env.mockup sendiri).
if (in_array(strtolower(strtok($_SERVER['HTTP_HOST'] ?? '', ':')), ['mockup.test', 'www.mockup.test'], true)) {
    $app->loadEnvironmentFrom('.env.mockup');
}

return $app;
