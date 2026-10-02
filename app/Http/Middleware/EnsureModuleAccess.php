<?php

namespace App\Http\Middleware;

use App\Models\Sys\SecModule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Cek izin modul (BRIEF §4 / TIER_3A_SECURITY.sql "LOGIKA CEK IZIN"):
// can_access=1 DAN mgmt_level >= module.min_mgmt_level, is_admin bypass.
// Dipasang per route group dengan parameter module_code, mis. `module:INQUIRY`.
class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $moduleCode): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if (! $user->canAccessModule($moduleCode)) {
            $module = SecModule::query()->where('module_code', $moduleCode)->first();
            abort(403, str_replace(':module', (string) ($module->module_name ?? $moduleCode), t('You do not have access to the :module module.')));
        }

        return $next($request);
    }
}
