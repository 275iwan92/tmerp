<?php

namespace App\Services\Auth;

use App\Models\Sys\SecLoginAttempt;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysLoginHero;
use App\Models\Sys\SysQuote;
use App\Support\V2\Board\Board;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/*
 * REMARK
 * Tujuan       : Aturan halaman masuk: cek login/email + password pada akun aktif, catat SETIAP percobaan (berhasil atau gagal), mulai sesi, dan tentukan halaman tujuan setelah masuk berdasarkan LEVEL akun. Data latar login (gambar hero, kutipan, akun UAT) untuk x-dl::frame.
 * Sumber hulu  : tm2sys.sec_user, sec_login_attempt, sys_login_hero, sys_quote; config uat.accounts (hanya .env.mockup).
 * Input        : login atau email, password, Request (IP, user agent, sesi).
 * Output       : SecUser yang masuk (atau null), baris sec_login_attempt, sesi baru (dl_fresh_login = mulai bersih di Shell v2), URL tujuan.
 * Dipakai oleh : App\Http\Controllers\Auth\LoginController.
 * Status       : Aktif. Dipindahkan apa adanya dari halaman v1 pages::auth.login (diarsipkan 2026-10-21, T10 tahap 6a).
 */
class LoginService
{
    // Label mockup "Username atau email": kedua kolom diterima di isian yang sama. Gagal dan berhasil sama-sama dicatat (BRIEF 3.1.3).
    public function attempt(string $login, string $password, Request $request): ?SecUser
    {
        $login = trim($login);
        $user = SecUser::query()->where('login', $login)->orWhere('email', $login)->first();
        $ok = $user && $user->active && $user->activate && Hash::check($password, (string) $user->pswd);

        SecLoginAttempt::create([
            'login' => $login,
            'success' => $ok,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        if (! $ok) {
            return null;
        }

        Auth::login($user);
        $request->session()->regenerate();
        // Login baru = mulai bersih di Shell v2 (tab lama ditutup, menu di lapis utama, opening screen tampil) -- user 2026-10-03.
        $request->session()->put('dl_fresh_login', true);

        return $user;
    }

    // Landing setelah login berbasis LEVEL, bukan nama grup (user 2026-09-13): Operational Manager ke atas -> Management Report,
    // di bawahnya -> menu operasional. canAccessModule('MGMT') menegakkan aturan yang sama dengan EnsureModuleAccess.
    public function landing(SecUser $user): string
    {
        return match (true) {
            // v2: yang punya papan widget (top management, user 2026-10-02) mendarat di layar pembukanya sendiri -- tanpa tab yang menutupinya.
            config('app.ui') === 'v2' && Board::profile($user) !== null => route('v2.home'),
            config('app.ui') === 'v2' => ($user->isDireksiOnly() || $user->canAccessModule('MGMT')) ? route('v2.home', ['open' => 'MGMT_EXEC']) : route('v2.home'),
            $user->isDireksiOnly() => route('report.executive'),
            $user->canAccessModule('MGMT') => route('report.executive'),
            default => route('menu'),
        };
    }

    /** @return list<array{src: string, portrait: null}> semua foto hero aktif (sys_login_hero); acak ditentukan x-dl::frame */
    public function heroImages(): array
    {
        return SysLoginHero::query()->where('activate', true)->get()
            ->map(fn (SysLoginHero $h) => ['src' => (string) $h->image_url, 'portrait' => null])->values()->all();
    }

    /** @return list<array{t: string, a: string}> kutipan aktif (sys_quote) */
    public function quotes(): array
    {
        return SysQuote::query()->where('activate', true)->get()
            ->map(fn (SysQuote $q) => ['t' => (string) $q->quote_text, 'a' => (string) $q->author])->values()->all();
    }

    // Akun UAT dari config uat.accounts (hanya .env.mockup, keputusan user 2026-09-25); kosong = ikon bantuan tidak tampil.
    /** @return array<string, array{login: string, password: string}> label => akun */
    public function uatAccounts(): array
    {
        $out = [];
        foreach (explode(';', (string) config('uat.accounts')) as $line) {
            $p = array_map('trim', explode('|', $line));
            if (count($p) >= 2 && $p[0] !== '') {
                $out[($p[2] ?? '') !== '' ? $p[2] : $p[0]] = ['login' => $p[0], 'password' => $p[1]];
            }
        }

        return $out;
    }
}
