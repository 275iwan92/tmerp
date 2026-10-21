{{--
 REMARK
 Tujuan       : Halaman masuk: x-dl::login-box di dalam frame (hero dan kutipan dari database); ikon bantuan akun UAT hanya bila config uat.accounts terisi (mockup.test).
 Sumber hulu  : App\Http\Controllers\Auth\LoginController::show, App\Services\Auth\LoginService (heroImages, quotes, uatAccounts).
 Input        : images, quotes, uat (label => [login, password]), error (kode empty | wrong).
 Output       : Form POST ke login.attempt (CSRF), tautan lupa password dan daftar.
 Dipakai oleh : Route login (routes/web.php, middleware guest).
 Status       : Aktif (T10 tahap 6a, 2026-10-21). Menggantikan halaman v1 pages::auth.login.
--}}
@extends('auth.layout', ['title' => 'Sign in'])
@section('box')
    <x-dl::login-box :show="'lang, title, username, password, forgot, button, register'.($uat ? ', support' : '')" :uat-accounts="$uat ?: null" :action="route('login.attempt')"
        :forgot-url="route('password.request')" :register-url="route('register')" :error="$error" :username="old('login', '')" />
@endsection
