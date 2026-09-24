<?php

namespace App\Http\Middleware;

use App\Models\Sys\SysConstant;
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
            && ! self::skipsSecuritySetup($user->login)
            && ! app(SecurityRecoveryService::class)->isSetupComplete($user)
            && ! $request->routeIs('security.setup')) {
            return redirect()->route('security.setup');
        }

        return $next($request);
    }

    // UAT (Fase 2) dengan pengamanan dilonggarkan (keputusan user 2026-09-25): konstanta SECURITY_SETUP_SKIP
    // ('*' = semua, atau daftar login dipisah koma) melewati kewajiban 4 pertanyaan keamanan. Hanya diisi di
    // ddtsys (mockup.test); di tm2sys tidak ada -> gerbang tetap penuh. Diubah lewat Settings > Constants.
    public static function skipsSecuritySetup(string $login): bool
    {
        $list = array_filter(array_map('trim', explode(',', strtolower(SysConstant::text('SECURITY_SETUP_SKIP', '')))));

        return in_array('*', $list, true) || in_array(strtolower($login), $list, true);
    }
}
