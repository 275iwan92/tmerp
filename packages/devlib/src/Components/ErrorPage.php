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
        '403' => ['Akses ditolak', 'Anda tidak punya hak untuk membuka halaman ini. Hubungi admin bila merasa ini keliru.', 'fa-lock', 'warn'],
        '404' => ['Halaman tidak ditemukan', 'Alamat yang dibuka tidak ada atau sudah dipindah.', 'fa-compass', 'accent'],
        '419' => ['Sesi habis', 'Halaman terlalu lama dibiarkan. Muat ulang lalu ulangi aksi Anda.', 'fa-clock-rotate-left', 'warn'],
        '500' => ['Terjadi kesalahan sistem', 'Maaf, ada gangguan di server. Tim IT sudah tercatat; coba lagi sebentar.', 'fa-triangle-exclamation', 'danger'],
        '503' => ['Sedang pemeliharaan', 'Aplikasi sedang diperbarui. Silakan kembali beberapa menit lagi.', 'fa-screwdriver-wrench', 'accent'],
        'offline' => ['Tidak ada koneksi', 'Perangkat sedang offline. Periksa jaringan lalu muat ulang.', 'fa-wifi', 'danger'],
    ];

    public function info(): array
    {
        return self::CODES[$this->code] ?? ['Terjadi kesalahan', 'Silakan coba lagi.', 'fa-circle-exclamation', 'danger'];
    }

    public function render()
    {
        return <<<'BLADE'
            @php [$t, $m, $ic, $tone] = $info(); @endphp
            <div {{ $attributes->class(['dl-error', 'dl-error--full' => $full]) }} {!! $dl() !!}>
                <div class="dl-error__card">
                    <span class="dl-error__icon dl-error__icon--{{ $tone }}"><i class="fa-solid {{ $ic }}"></i></span>
                    <div class="dl-error__code">{{ $code === 'offline' ? '' : $code }}</div>
                    <h1 class="dl-error__title">{{ $title ?? $t }}</h1>
                    <p class="dl-error__msg">{{ $message ?? $m }}</p>
                    <div class="dl-error__actions"><x-dl::back-button :fallback="$home" /><x-dl::button :href="$home" icon="home" label="Beranda" variant="secondary" /></div>
                </div>
            </div>
            BLADE;
    }
}
