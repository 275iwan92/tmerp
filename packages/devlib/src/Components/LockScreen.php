<?php

namespace Devlib\Components;

// Layar kunci: menutup aplikasi setelah tidak ada aktivitas `idle` menit (0 = hanya manual), atau lewat tombol
// [data-dl-lock] / Ctrl+Shift+L. Menampilkan jam WIB, foto + nama pengguna, isian password untuk membuka.
// Status terkunci bertahan saat halaman dimuat ulang (sessionStorage). Tombol Keluar = logout.
// Buka: event dl:unlock {password} (cancelable). Aplikasi memeriksa password lalu memanggil el.dlLock.unlock()
// atau el.dlLock.fail(pesan); bila event tidak dicegah (pratinjau) password apa pun yang terisi diterima.
class LockScreen extends DevlibComponent
{
    public function __construct(
        public array $user = ['name' => 'User'],
        public int $idle = 0,
        public string $logoutAction = '',
        public string $id = 'lock',
    ) {}

    public function tag(): string
    {
        return 'x-dl::lock-screen';
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->user['name'] ?? '?')))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-lock') }} {!! $dl() !!} data-dl-lockscreen data-idle="{{ $idle }}" data-id="{{ $id }}" hidden role="dialog" aria-modal="true" aria-label="{{ \Devlib\Lang::text('Screen locked') }}">
                <div class="dl-lock__clock"><b data-lk-time></b><span data-lk-date></span></div>
                <form class="dl-lock__card" data-lk-form novalidate>
                    <span class="dl-lock__av">@if (! empty($user['photo']))<img src="{{ $user['photo'] }}" alt="">@else{{ $initials() }}@endif</span>
                    <b class="dl-lock__name">{{ $user['name'] ?? '' }}</b>
                    <small class="dl-lock__sub"><i class="fa-solid fa-lock"></i> {{ \Devlib\Lang::text('Locked — enter the password to continue') }}</small>
                    <x-dl::field.password name="unlock_password" label="Password" icon="fa-key" size="lg" autocomplete="current-password" />
                    <x-dl::button type="submit" size="lg" block icon="unlock" label="Unlock" />
                    @if ($logoutAction)
                        <button type="submit" formaction="{{ $logoutAction }}" formmethod="POST" formnovalidate class="dl-btn dl-btn--link dl-btn--sm dl-lock__out" data-lk-out><i class="fa-solid fa-right-from-bracket"></i> {{ \Devlib\Lang::text('Log out') }}</button>
                    @endif
                </form>
            </div>
            BLADE;
    }
}
