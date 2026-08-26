<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// security_defaultpassword_spec.md §6 — user dengan must_change_password=1
// (habis reset admin / user baru) dipaksa ke halaman ganti password sebelum
// akses fitur apa pun. Dipasang di routes/web.php hanya pada grup halaman
// aplikasi, TIDAK pada route password.change sendiri (hindari redirect loop).
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && ! $request->routeIs('password.change')) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
