{{--
 REMARK
 Tujuan       : Daftar akun kandidat portal karir: No. KTP (16 digit, kode wilayah + tanggal lahir dicek), nama sesuai KTP, email, No. HP, password. Setelah daftar kandidat langsung masuk dan diminta memeriksa surel verifikasi.
 Sumber hulu  : CareersController::registerForm / register, CareersPortalService::register (Firestore 'candidates', surel CandidateEmailVerification).
 Input        : galat validasi dan old() dari sesi; flash 'registered' (alamat surel) setelah berhasil.
 Output       : x-mp::auth.box berisi x-dl::field.*; atau x-dl::empty-state "Cek Email Anda".
 Dipakai oleh : Route careers.register.
 Status       : Aktif (T10 tahap 6b, 2026-10-21). Menggantikan pages::careers.register (Livewire v1).
--}}
@extends('careers.layout', ['title' => 'Daftar Akun'])
@section('content')
    <x-mp::careers.page size="form">
        @if (session('registered'))
            <x-dl::empty-state :title="raw('Cek Email Anda')" icon="email" :message="raw('Kami sudah kirim tautan verifikasi ke '.session('registered').'. Verifikasi dulu sebelum mengirim lamaran.')">
                <x-dl::button :label="raw('Lihat Lowongan')" :href="route('careers.landing')" />
            </x-dl::empty-state>
        @else
            <x-mp::auth.box :title="raw('Buat Akun')" :sub="raw('Satu akun untuk melamar semua posisi & melacak status lamaran Anda.')" :action="route('careers.register.store')" :lang="false">
                <x-dl::field.text name="national_id" :label="raw('No. KTP')" :placeholder="raw('16 digit sesuai KTP')" :value="old('national_id')" :maxlength="16" inputmode="numeric" :case-toggle="false" :error="$errors->first('national_id')" />
                <x-dl::field.text name="name" :label="raw('Nama Lengkap (sesuai KTP)')" :value="old('name')" :case-toggle="false" :error="$errors->first('name')" />
                <x-dl::field.text name="email" type="email" :label="raw('Email')" :value="old('email')" :case-toggle="false" autocomplete="email" :error="$errors->first('email')" />
                <x-dl::field.text name="phone" :label="raw('No. HP / Smartphone')" :placeholder="raw('08xxxxxxxxxx')" :value="old('phone')" :case-toggle="false" autocomplete="tel" :error="$errors->first('phone')" />
                <x-dl::field.password name="password" :label="raw('Password')" autocomplete="new-password" :error="$errors->first('password')" />
                <x-dl::field.password name="password_confirmation" :label="raw('Ulangi Password')" autocomplete="new-password" />
                <x-dl::button type="submit" size="lg" block :label="raw('Daftar')" />
                <x-dl::hint>Sudah punya akun? <x-dl::button variant="link" :label="raw('Masuk')" :href="route('careers.login')" /></x-dl::hint>
            </x-mp::auth.box>
        @endif
    </x-mp::careers.page>
@endsection
