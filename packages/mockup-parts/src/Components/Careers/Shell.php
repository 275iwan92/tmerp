<?php

/*
 * REMARK
 * Tujuan       : Kerangka halaman portal karir publik: bilah atas (merek + menu Lowongan / Lamaran Saya / Keluar atau Masuk / Daftar) dan kaki halaman. Isi halaman = slot.
 * Sumber hulu  : x-dl::brand dan x-dl::button milik DevLib; token warna --dl-*.
 * Input        : brand, tagline, signedIn, urls (home, dashboard, login, register, logout), footer, footerNote.
 * Output       : <div class="mp-careers"> dengan header lengket, <main> berisi slot, dan footer.
 * Dipakai oleh : resources/views/careers/layout.blade.php (semua halaman portal karir).
 * Status       : Aktif (T10 tahap 6b, 2026-10-21). Menggantikan resources/views/layouts/portal.blade.php (Tailwind).
 */

namespace MockupParts\Components\Careers;

use Devlib\Components\DevlibComponent;

class Shell extends DevlibComponent
{
    protected const TEXT_PROPS = [];

    protected const TEXT_LISTS = [];

    public function __construct(
        public string $brand = '',
        public string $tagline = 'Karir',
        public bool $signedIn = false,
        public array $urls = [],
        public string $footer = '',
        public string $footerNote = '',
    ) {}

    public function tag(): string
    {
        return 'x-mp::careers.shell';
    }

    public function source(): string
    {
        return 'portal karir publik (sesi kandidat portal_candidate_uid)';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('mp-careers') }} {!! $dl() !!}>
                <header class="mp-careers__bar">
                    <div class="mp-careers__in">
                        <a href="{{ $urls['home'] ?? '#' }}" class="mp-careers__brand"><x-dl::brand tone="dark" :name="$brand" :tagline="$tagline" /></a>
                        <nav class="mp-careers__nav">
                            <x-dl::button variant="link" :label="raw('Lowongan')" :href="$urls['home'] ?? '#'" />
                            @if ($signedIn)
                                <x-dl::button variant="link" :label="raw('Lamaran Saya')" :href="$urls['dashboard'] ?? '#'" />
                                <x-dl::button variant="secondary" :label="raw('Keluar')" :href="$urls['logout'] ?? '#'" />
                            @else
                                <x-dl::button variant="link" :label="raw('Masuk')" :href="$urls['login'] ?? '#'" />
                                <x-dl::button :label="raw('Daftar')" :href="$urls['register'] ?? '#'" />
                            @endif
                        </nav>
                    </div>
                </header>
                <main class="mp-careers__main">{{ $slot }}</main>
                <footer class="mp-careers__foot">
                    <div class="mp-careers__in"><span>{{ $footer }}</span><span>{{ $footerNote }}</span></div>
                </footer>
            </div>
            BLADE;
    }
}
