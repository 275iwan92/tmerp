{{--
 REMARK
 Tujuan       : Masuk kandidat portal karir dengan email + password; setelah masuk kembali ke halaman yang dituju (intended_url) atau Lamaran Saya.
 Sumber hulu  : CareersController::loginForm / login, CareersPortalService::attempt (Firestore 'candidates').
 Input        : galat dan old() dari sesi; flash 'verified' setelah klik tautan verifikasi surel.
 Output       : x-mp::auth.box berisi dua x-dl::field.
 Dipakai oleh : Route careers.login; middleware portal.candidate mengarahkan tamu ke sini.
 Status       : Aktif (T10 tahap 6b, 2026-10-21). Menggantikan pages::careers.login (Livewire v1).
--}}
@extends('careers.layout', ['title' => 'Masuk'])
@section('content')
    <x-mp::careers.page size="narrow">
        <x-mp::auth.box :title="raw('Masuk')" :sub="raw('Masuk untuk melamar posisi atau melihat status lamaran Anda.')" :action="route('careers.login.attempt')" :lang="false">
            @if (session('verified'))
                <x-dl::alert tone="success" :message="raw('Email berhasil diverifikasi, silakan masuk.')" />
            @endif
            <x-dl::field.text name="email" type="email" :label="raw('Email')" :value="old('email')" :case-toggle="false" autocomplete="username" :error="$errors->first('email')" />
            <x-dl::field.password name="password" :label="raw('Password')" :error="$errors->first('password')" />
            <x-dl::button type="submit" size="lg" block :label="raw('Masuk')" />
            <x-dl::hint>Belum punya akun? <x-dl::button variant="link" :label="raw('Daftar')" :href="route('careers.register')" /></x-dl::hint>
        </x-mp::auth.box>
    </x-mp::careers.page>
@endsection
