<?php

/*
 * REMARK
 * Tujuan       : Kotak isi satu langkah halaman tamu / paksa-ganti (lupa password, ganti password, setup keamanan) di dalam x-dl::frame: judul, sub, pesan galat, slot field + tombol, tautan kembali. Rupa sama dengan x-dl::login-box dan x-dl::register-box.
 * Sumber hulu  : Kelas CSS dl-loginbox / dl-auth dan x-dl::lang-switch, x-dl::alert, x-dl::button milik DevLib.
 * Input        : title, sub, action (POST), error, backUrl, backLabel, lang; slot = field dan tombol.
 * Output       : <div class="dl-loginbox dl-auth"> dengan pilihan bahasa, form POST (+ CSRF), judul, pesan galat, slot, tautan kembali.
 * Dipakai oleh : resources/views/auth/forgot-password.blade.php, password-change.blade.php, security-setup.blade.php.
 * Status       : Aktif (T10 tahap 6a, 2026-10-21). Langkah yang punya JS DevLib sendiri (login, daftar) memakai x-dl::login-box / x-dl::register-box.
 */

namespace MockupParts\Components\Auth;

use Devlib\Components\DevlibComponent;

class Box extends DevlibComponent
{
    protected const TEXT_PROPS = ['title', 'sub', 'backLabel'];

    protected const TEXT_LISTS = [];

    public function __construct(
        public string $title = '',
        public string $sub = '',
        public string $action = '',
        public ?string $error = null,
        public ?string $backUrl = null,
        public string $backLabel = 'Back',
        public bool $lang = true,
    ) {}

    public function tag(): string
    {
        return 'x-mp::auth.box';
    }

    public function source(): string
    {
        return 'tm2sys.sec_user (login, password, pertanyaan keamanan) lewat App\Services\Security';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-loginbox dl-auth') }} {!! $dl() !!}>
                @if ($lang)<div class="dl-loginbox__top"><x-dl::lang-switch /></div>@endif
                <form class="dl-loginbox__form" method="POST" @if ($action !== '') action="{{ $action }}" @endif novalidate>
                    @csrf
                    <h2 class="dl-loginbox__title">{{ $title }}</h2>
                    @if ($sub !== '')<p class="dl-loginbox__sub">{{ $sub }}</p>@endif
                    @if ($error)<x-dl::alert tone="danger" :message="$error" />@endif
                    {{ $slot }}
                    @if ($backUrl)<x-dl::button variant="link" icon="back" :label="$backLabel" :href="$backUrl" class="dl-auth__back" />@endif
                </form>
            </div>
            BLADE;
    }
}
