<?php

namespace Devlib\Components;

// Halaman kesalahan baku (403, 404, 419, 500, 503, offline): kode, judul, penjelasan, tombol Back & Beranda. full=false untuk pratinjau di dalam halaman.
class ErrorPage extends DevlibComponent
{
    public function __construct(
        public string $code = '404',
        public ?string $title = null,
        public ?string $message = null,
        public string $home = '/',
        public bool $full = true,
    ) {}

    public function tag(): string
    {
        return 'x-dl::error-page code='.$this->code;
    }

    // kode => [judul, penjelasan, ikon, warna]
    public const CODES = [
        '403' => ['Access denied', 'You are not allowed to open this page. Contact the admin if you think this is a mistake.', 'fa-lock', 'warn'],
        '404' => ['Page not found', 'The address you opened does not exist or has been moved.', 'fa-compass', 'accent'],
        '419' => ['Session expired', 'The page was left open too long. Reload it and repeat your action.', 'fa-clock-rotate-left', 'warn'],
        '500' => ['A system error occurred', 'Sorry, there is a problem on the server. The IT team has been notified; try again in a moment.', 'fa-triangle-exclamation', 'danger'],
        '503' => ['Under maintenance', 'The application is being updated. Please come back in a few minutes.', 'fa-screwdriver-wrench', 'accent'],
        'offline' => ['No connection', 'The device is offline. Check the network and reload.', 'fa-wifi', 'danger'],
    ];

    public function info(): array
    {
        return self::CODES[$this->code] ?? ['An error occurred', 'Please try again.', 'fa-circle-exclamation', 'danger'];
    }

    public function render()
    {
        return <<<'BLADE'
            @php [$t, $m, $ic, $tone] = $info(); @endphp
            <div {{ $attributes->class(['dl-error', 'dl-error--full' => $full]) }} {!! $dl() !!}>
                <div class="dl-error__card">
                    <span class="dl-error__icon dl-error__icon--{{ $tone }}"><i class="fa-solid {{ $ic }}"></i></span>
                    <div class="dl-error__code">{{ $code === 'offline' ? '' : $code }}</div>
                    <h1 class="dl-error__title">{{ $title ?? \Devlib\Lang::text($t) }}</h1>
                    <p class="dl-error__msg">{{ $message ?? \Devlib\Lang::text($m) }}</p>
                    <div class="dl-error__actions"><x-dl::back-button :fallback="$home" /><x-dl::button :href="$home" icon="home" label="Home" variant="secondary" /></div>
                </div>
            </div>
            BLADE;
    }
}
