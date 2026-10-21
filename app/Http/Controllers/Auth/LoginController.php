<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\LoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/*
 * REMARK
 * Tujuan       : Halaman masuk (x-dl::frame + x-dl::login-box): tampilkan form dengan hero dan kutipan dari database, proses POST login (keluar tetap route logout di routes/web.php).
 * Sumber hulu  : App\Services\Auth\LoginService (aturan login, landing, data latar, akun UAT).
 * Input        : GET /login; POST /login (login, password).
 * Output       : View auth.login; redirect ke halaman tujuan (LoginService::landing) atau kembali dengan kode galat empty / wrong.
 * Dipakai oleh : routes/web.php (middleware guest).
 * Status       : Aktif (T10 tahap 6a, 2026-10-21). Menggantikan halaman v1 pages::auth.login.
 */
class LoginController extends Controller
{
    public function show(LoginService $login): View
    {
        return view('auth.login', [
            'images' => $login->heroImages(),
            'quotes' => $login->quotes(),
            'uat' => $login->uatAccounts(),
            'error' => session('login_error'),
        ]);
    }

    public function attempt(Request $request, LoginService $login): RedirectResponse
    {
        $name = trim((string) $request->input('login'));
        $password = (string) $request->input('password');

        if ($name === '' || $password === '') {
            return back()->withInput($request->only('login'))->with('login_error', 'empty');
        }

        $user = $login->attempt($name, $password, $request);

        return $user ? redirect()->intended($login->landing($user)) : back()->withInput($request->only('login'))->with('login_error', 'wrong');
    }
}
