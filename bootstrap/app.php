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

return Application::configure(basePath: dirname(__DIR__))
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
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
