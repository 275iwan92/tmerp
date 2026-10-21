<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\LoginService;
use App\Services\Security\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

/*
 * REMARK
 * Tujuan       : Halaman ganti password PAKSA (middleware force.password) bila must_change_password = 1: sehabis reset admin atau untuk user baru. default_password tidak diubah oleh alur ini (tetap fallback).
 * Sumber hulu  : App\Services\Security\PasswordResetService::changeByUser, security_defaultpassword_spec.md bagian 6.
 * Input        : GET /password/change; POST (password, password_confirmation); keluar lewat route logout.
 * Output       : View auth.password-change; sukses = redirect ke halaman yang dituju semula (intended) atau menu.
 * Dipakai oleh : routes/web.php (middleware auth, locale; di luar force.password supaya tidak berputar).
 * Status       : Aktif (T10 tahap 6a, 2026-10-21). Dipindahkan apa adanya dari halaman v1 pages::auth.password-change.
 */
class PasswordChangeController extends Controller
{
    public function show(LoginService $login): View
    {
        return view('auth.password-change', ['images' => $login->heroImages(), 'quotes' => $login->quotes()]);
    }

    public function update(Request $request, PasswordResetService $service): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:8'], 'password_confirmation' => ['required', 'string']]);

        if ($data['password'] !== $data['password_confirmation']) {
            return back()->withErrors(['password' => t('The password confirmation does not match.')]);
        }

        try {
            $service->changeByUser(Auth::user(), $data['password']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['password' => $e->getMessage()]);
        }

        return redirect()->intended(route('menu'));
    }
}
