<?php

declare(strict_types=1);

namespace App\Support\Localization;

// Teks halaman masuk (login). Dulu dibaca dari kamus kunci v1 (sys_translations, layar Setup > Localization);
// sekarang English-nya ada di sini dan terjemahannya di kamus utama (Setup > Translation Dictionary).
// Bahasa halaman masuk dipilih tamu lewat bendera, jadi bahasa dikirim eksplisit (bukan dari akun).
final class AuthText
{
    public const TEXTS = [
        'login_heading' => 'Sign in',
        'login_sub' => 'Use your registered account to sign in.',
        'label_username' => 'Username or email',
        'label_password' => 'Password',
        'forgot' => 'Forgot password?',
        'btn_login' => 'Sign in',
        'err_empty' => 'Enter your username and password.',
        'err_wrong' => 'Incorrect username or password.',
    ];

    /** @return array<string, string> kunci teks => teks dalam bahasa $lang */
    public static function forLang(string $lang): array
    {
        return array_map(fn (string $en): string => Dictionary::t($en, 'ui', $lang), self::TEXTS);
    }
}
