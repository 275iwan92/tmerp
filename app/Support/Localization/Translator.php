<?php

namespace App\Support\Localization;

use Devlib\Locale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

// Bahasa tampilan user (v1 & v2 satu sumber, user 2026-10-02). Teks diterjemahkan lewat kamus tunggal sys_dictionary:
// tulis English lalu t('...') / @t('...') (App\Support\Localization\Dictionary). Kamus kunci lama sys_translations
// (kunci + fallback per layar) sudah dipensiunkan -- jangan dihidupkan lagi.
class Translator
{
    public static function lang(): string
    {
        return Locale::lang();
    }

    // Simpan pilihan di sec_user.lang_code dan cookie dl_lang (dibaca Devlib\Locale, juga oleh v2 & JS).
    public static function setLang(string $langCode): bool
    {
        $user = Auth::user();

        if (! $user || ! DB::connection('tm2sys')->table('sys_languages')->where('code', $langCode)->exists()) {
            return false;
        }

        $user->update(['lang_code' => $langCode]);
        Cookie::queue(Cookie::make('dl_lang', $langCode, 525600, '/', null, null, false, false, 'lax'));
        request()->cookies->set('dl_lang', $langCode);

        return true;
    }
}
