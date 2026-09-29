<?php

namespace App\Http\Middleware;

use App\Support\V2\Shell;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Situs v2 (APP_UI=v2): layar menu hanya tampil sebagai tab di dalam shell (/menu). Dibuka sebagai halaman
// penuh -- refresh, tab baru, URL disalin, atau redirect "intended" setelah login dari iframe -- dialihkan ke
// shell dengan layar itu terbuka sebagai tab (user 2026-09-29: layar tanpa menu tidak boleh terjadi).
// Sec-Fetch-Dest=document = navigasi jendela utama; iframe mengirim "iframe", fetch/XHR tidak "document".
// Browser tanpa header ini ditangani cadangan JS di resources/views/v2/layout.blade.php.
class KeepScreensInShell
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Shell::isV2Site() || ! $request->isMethod('GET') || $request->header('Sec-Fetch-Dest') !== 'document') {
            return $next($request);
        }

        $code = Shell::codeFor($request);

        return $code ? redirect(Shell::url(['open' => $code])) : $next($request);
    }
}
