{{--
 REMARK
 Tujuan       : Kerangka halaman tamu / paksa-ganti: x-dl::frame (gambar latar hero, jam, kutipan, merek) dengan kotak isi di kanan atau tengah. Hero dan kutipan dari database (sys_login_hero, sys_quote).
 Sumber hulu  : x-dl::frame (DevLib), App\Services\Auth\LoginService (heroImages, quotes), App\Support\Branding\Brand (merek), x-dl::env-badge.
 Input        : images, quotes (dari controller), title, box (right | center), isi = section box.
 Output       : Dokumen HTML lewat v2.layout dengan frame dan slot isi.
 Dipakai oleh : auth.login, auth.register, auth.forgot-password, auth.password-change, auth.security-setup.
 Status       : Aktif (T10 tahap 6a, 2026-10-21).
--}}
@extends('v2.layout', ['title' => $title ?? 'Sign in', 'bodyClass' => 'dl-page-auth'])
@section('body')
    @php($envTitle = app()->bound('devlib.env_title') ? trim((string) app('devlib.env_title')) : '')
    <x-dl::frame :image-list="$images" :quote-list="$quotes" :box="$box ?? 'right'" color="light" :resizable="($box ?? 'right') === 'right'"
        :brand="\App\Support\Branding\Brand::name().($envTitle !== '' ? ' '.$envTitle : '')" widgets="brand@top-left, clock@top-right, quote@bottom-left">
        @yield('box')
        <x-dl::env-badge part="version" variant="loginver" />
    </x-dl::frame>
@endsection
