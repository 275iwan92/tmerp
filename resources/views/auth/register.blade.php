{{--
 REMARK
 Tujuan       : Halaman daftar akun: x-dl::register-box di dalam frame. Verifikasi email dan OTP WhatsApp berjalan di browser (demo, format saja) seperti v1; akun menunggu persetujuan administrator.
 Sumber hulu  : App\Http\Controllers\Auth\RegisterController, App\Services\Auth\RegistrationService.
 Input        : images, quotes; flash registered (sukses); galat validasi dari server.
 Output       : Form POST ke register.store; setelah sukses kotak "Registration submitted" (x-mp::auth.box) menggantikan form.
 Dipakai oleh : Route register (routes/web.php, middleware guest).
 Status       : Aktif (T10 tahap 6a, 2026-10-21). Menggantikan halaman v1 pages::auth.register.
--}}
@extends('auth.layout', ['title' => 'Create account'])
@section('box')
    @if (session('registered'))
        <x-mp::auth.box title="Registration submitted" sub="Your account is awaiting admin approval. Once approved, you can sign in with this email and password." :back-url="route('login')" back-label="Back" />
    @else
        @if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif
        <x-dl::register-box :action="route('register.store')" :login-url="route('login')" />
    @endif
@endsection
