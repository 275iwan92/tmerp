<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Halaman Setup/Security: admin-only (BRIEF §3.4). Belum ada modul SECURITY
// terdaftar di sec_module, jadi gate-nya is_admin langsung (sama seperti
// SecUser::canSetup()).
class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->canSetup()) {
            abort(403, 'Halaman ini khusus administrator.');
        }

        return $next($request);
    }
}
