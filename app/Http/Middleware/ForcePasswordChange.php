<?php

namespace App\Http\Middleware;

use App\Services\Security\SecurityRecoveryService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// security_defaultpassword_spec.md §6 — user dengan must_change_password=1
// (habis reset admin / user baru) dipaksa ke halaman ganti password sebelum
// akses fitur apa pun. Dipasang di routes/web.php hanya pada grup halaman
// aplikasi, TIDAK pada route password.change/security.setup sendiri (hindari
// redirect loop).
//
// Diperluas 2026-08-27: fitur Lupa Password 4-pertanyaan (keputusan user) --
// user yang BELUM setup 4 jawaban keamanan juga dipaksa ke /security/setup,
// TERLEPAS dari status must_change_password (gerbang ini independen supaya
// 146 akun lama yang sudah pernah ganti password tetap ke-cover, bukan cuma
// user baru/habis-reset).
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->must_change_password && ! $request->routeIs('password.change', 'security.setup')) {
            return redirect()->route('password.change');
        }

        if (! $user->must_change_password
            && ! app(SecurityRecoveryService::class)->isSetupComplete($user)
            && ! $request->routeIs('security.setup')) {
            return redirect()->route('security.setup');
        }

        return $next($request);
    }
}
