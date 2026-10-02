<?php

namespace App\Http\Middleware;

use Closure;
use Devlib\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

// i18n readiness gap ditemukan saat QA checkpoint rollout i18n 7-malam
// (2026-09-10, docs/BACKLOG.md §13.1): App::setLocale() TIDAK PERNAH
// dipanggil di mana pun di aplikasi ini -- artinya Carbon::translatedFormat()
// (dipakai ratusan kali utk tanggal di seluruh layar) SELALU render pakai
// config('app.locale') statis dari .env (APP_LOCALE=en), TERLEPAS dari
// preferensi sec_user.lang_code masing-masing user. Efeknya nyata utk
// SEMUA user (bukan cuma yang pilih EN): user ber-lang_code 'id' (default,
// mayoritas) tetap melihat nama hari/bulan default Bahasa Inggris
// ("Thursday, 10 September") di tengah UI yang sebagian besar Indonesia.
// Translator::t() (App\Support\Localization\Translator) sendiri TIDAK
// terpengaruh gap ini -- itu baca sys_translations langsung per lang_code,
// tidak lewat App::getLocale(). Middleware ini HANYA menjembatani locale
// Laravel/Carbon ke preferensi yang sama, supaya date-format ikut konsisten
// dgn Translator::t() dan sisa UI. Tidak mengubah Translator::t() apa pun.
class SetUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Satu sumber bahasa untuk Laravel (pesan validasi lang/, Carbon) dan DevLib (label, angka, tanggal):
        // bahasa akun user, cookie perangkat untuk tamu, ?print_lang untuk cetakan (Devlib\Locale::lang).
        App::setLocale(Locale::lang());

        return $next($request);
    }
}
