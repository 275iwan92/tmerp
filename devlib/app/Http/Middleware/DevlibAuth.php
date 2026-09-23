<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

// Gerbang login tanpa database: cukup ada devlib_user di sesi.
class DevlibAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->session()->has('devlib_user')) {
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
