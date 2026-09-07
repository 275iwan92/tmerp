<?php

namespace App\Support\Localization;

use App\Models\Sys\SysLanguage;
use App\Models\Sys\SysTranslation;
use Illuminate\Support\Facades\Auth;

// Kerangka i18n app-wide (keputusan user 2026-09-07) -- titik akses tunggal
// utk teks UI shell PASCA-LOGIN, mirror pola App\Support\Branding\Brand
// (baca dari DB, cache per-request). BEDA dari SysTranslation::forLang() yang
// dipakai login/register (guest, lang dari query string #[Url]) -- di sini
// lang dari preferensi TERSIMPAN per user (sec_user.lang_code), krn
// pemakainya sudah login.
//
// SCOPE SAAT INI (2026-09-07): baru kerangka + string chrome shell PALING
// dasar (lihat pemakaian t() di main-menu.blade.php: label Home, Logout,
// Tema/Bahasa/Klasik, caption section dropdown profil). SEBAGIAN BESAR layar
// aplikasi (ratusan file Blade transaksi/master/laporan) MASIH 100% hardcode
// Indonesia, BELUM ditarik ke sys_translations -- itu proyek i18n app-wide
// yang jauh lebih besar, BUKAN diselesaikan 1 sesi. Menambah cakupan = tambah
// baris sys_translations utk key baru + ganti string literal Blade jadi
// Translator::t('key', 'fallback default'), diulang per layar.
class Translator
{
    protected static array $cache = [];

    public static function lang(): string
    {
        $user = Auth::user();

        return $user?->lang_code ?: 'id';
    }

    public static function t(string $key, ?string $default = null): string
    {
        $lang = static::lang();

        if (! array_key_exists($lang, static::$cache)) {
            static::$cache[$lang] = SysTranslation::forLang($lang);
        }

        return static::$cache[$lang][$key] ?? $default ?? $key;
    }

    public static function setLang(string $langCode): bool
    {
        $user = Auth::user();

        if (! $user || ! SysLanguage::where('code', $langCode)->exists()) {
            return false;
        }

        $user->update(['lang_code' => $langCode]);
        static::$cache = [];

        return true;
    }
}
