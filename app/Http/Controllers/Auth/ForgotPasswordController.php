<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Sys\SecUser;
use App\Services\Auth\LoginService;
use App\Services\Security\SecurityRecoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/*
 * REMARK
 * Tujuan       : Lupa password berbasis 4 pertanyaan (keputusan user 2026-08-27, menggantikan tautan email): 1 cari akun, 2 jawab tanggal lahir, nama ibu, nomor HP dan pertanyaan akun, 3 sukses. Jawaban benar semua = password KEMBALI ke default_password akun (bukan password baru).
 * Sumber hulu  : App\Services\Security\SecurityRecoveryService (isSetupComplete, verify, resetToDefaultPassword), sec_user, RateLimiter.
 * Input        : GET /forgot-password; POST find (login), verify (4 jawaban), restart. Langkah disimpan di sesi (forgot_user = id akun).
 * Output       : View auth.forgot-password per langkah; batas 8 percobaan per 15 menit per login+IP; pesan generik agar akun tidak bisa ditebak.
 * Dipakai oleh : routes/web.php (middleware guest).
 * Status       : Aktif (T10 tahap 6a, 2026-10-21). Dipindahkan apa adanya dari halaman v1 pages::auth.forgot-password.
 */
class ForgotPasswordController extends Controller
{
    private const MAX_ATTEMPTS = 8;

    private const DECAY_SECONDS = 900;

    public function show(Request $request, LoginService $login): View
    {
        $user = $request->session()->has('forgot_user') ? SecUser::find($request->session()->get('forgot_user')) : null;
        $step = $request->session()->has('forgot_done') ? 3 : ($user ? 2 : 1);

        return view('auth.forgot-password', [
            'images' => $login->heroImages(), 'quotes' => $login->quotes(), 'step' => $step,
            'question' => $user?->securityQuestion?->question_text ?? '',
        ]);
    }

    public function find(Request $request, SecurityRecoveryService $service): RedirectResponse
    {
        $name = trim((string) $request->validate(['login' => ['required', 'string']])['login']);
        $key = $this->key($name, $request);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return back()->withErrors(['recovery' => t('Too many attempts. Try again later or contact the Administrator.')]);
        }

        $user = SecUser::where('login', $name)->orWhere('email', $name)->first();

        // Pesan generik sengaja SAMA untuk "tak ada akun" dan "belum setup": jangan bocorkan mana yang benar (cegah enumerasi akun).
        if (! $user || ! $service->isSetupComplete($user)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            return back()->withErrors(['recovery' => t('Account not found or the security questions are not set. Contact the Administrator.')]);
        }

        $request->session()->put('forgot_user', $user->id_user);
        $request->session()->put('forgot_login', $name);

        return redirect()->route('password.request');
    }

    public function verify(Request $request, SecurityRecoveryService $service): RedirectResponse
    {
        $data = $request->validate([
            'birth_date' => ['required', 'date'], 'mother_name' => ['required', 'string'], 'phone' => ['required', 'string'], 'security_answer' => ['required', 'string'],
        ]);
        $user = SecUser::find($request->session()->get('forgot_user'));

        if (! $user) {
            return redirect()->route('password.request');
        }

        $key = $this->key((string) $request->session()->get('forgot_login'), $request);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return back()->withErrors(['recovery' => t('Too many attempts. Try again later or contact the Administrator.')]);
        }

        if (! $service->verify($user, $data['birth_date'], $data['mother_name'], $data['phone'], $data['security_answer'])) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            return back()->withInput($request->except('security_answer'))->withErrors(['recovery' => t('One of the answers does not match. Check again.')]);
        }

        if (! $service->resetToDefaultPassword($user)) {
            return back()->withErrors(['recovery' => t('This account has no stored default password. Contact the Administrator.')]);
        }

        RateLimiter::clear($key);
        $request->session()->forget(['forgot_user', 'forgot_login']);
        $request->session()->flash('forgot_done', true);

        return redirect()->route('password.request');
    }

    public function restart(Request $request): RedirectResponse
    {
        $request->session()->forget(['forgot_user', 'forgot_login', 'forgot_done']);

        return redirect()->route('password.request');
    }

    private function key(string $login, Request $request): string
    {
        return 'password-recovery:'.strtolower(trim($login)).'|'.$request->ip();
    }
}
