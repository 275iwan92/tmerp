<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Guard sesi portal kandidat (session('portal_candidate_uid')) -- TERPISAH
// total dari middleware 'auth' internal (sec_user/Laravel default guard).
// Lihat App\Services\Portal\CandidateAuthService kenapa bukan Guard/UserProvider formal.
class EnsurePortalCandidateAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('portal_candidate_uid')) {
            return redirect()->route('careers.login')->with('intended_url', $request->fullUrl());
        }

        return $next($request);
    }
}
