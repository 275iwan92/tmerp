<?php

namespace App\Http\Middleware;

use Closure;
use Devlib\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

// Satu bahasa tampilan untuk v1 & v2 (user 2026-10-02): cookie dl_lang (pilihan terakhir di layar mana pun) lalu
// sec_user.lang_code. Locale Laravel/Carbon ikut, supaya translatedFormat() sejalan dengan kamus sys_dictionary.
class SetUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($code = $request->user()?->lang_code) {
            config(['devlib.lang' => $code]);
        }

        App::setLocale(Locale::lang());

        return $next($request);
    }
}
