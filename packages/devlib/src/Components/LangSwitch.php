<?php

namespace Devlib\Components;

use Devlib\Locale;

// Pilihan bahasa (bendera). Daftar bahasa = Locale::languages() (tmerp: sys_languages yang aktif), jadi bahasa baru
// muncul sendiri. Memicu event "dl:lang" di dokumen (cookie perangkat + teks [data-dl-t]).
// reload: halaman dimuat ulang setelah bahasa dipilih supaya teks yang dirender server ikut berganti, dan pilihannya
// disimpan ke akun user lewat config devlib.lang_url. Bawaan: aktif bila ada user yang login; halaman tamu (login /
// daftar) berganti di tempat tanpa muat ulang.
class LangSwitch extends DevlibComponent
{
    private const FLAGS = ['id' => '🇮🇩', 'en' => '🇬🇧', 'ja' => '🇯🇵', 'zh' => '🇨🇳', 'ko' => '🇰🇷', 'th' => '🇹🇭', 'vi' => '🇻🇳', 'ms' => '🇲🇾'];

    public function __construct(
        public array $langs = [],
        public ?string $active = null,
        public ?bool $reload = null,
    ) {
        if (! $this->langs) {
            foreach (Locale::languages() as $code => $spec) {
                $this->langs[$code] = [self::FLAGS[$code] ?? strtoupper($code), $spec['name'] ?? $code];
            }
        }
        $this->active ??= Locale::lang();
        $this->reload ??= function_exists('auth') && auth()->check();
    }

    public function tag(): string
    {
        return 'x-dl::lang-switch';
    }

    public function source(): string
    {
        return 'sys_languages + sec_user.lang_code (tmerp)';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-langs') }} {!! $dl() !!} data-dl-langs @if ($reload) data-reload @endif>
                @foreach ($langs as $code => [$flag, $title])
                    <button type="button" class="dl-flag {{ $code === $active ? 'is-active' : '' }}" data-lang="{{ $code }}" title="{{ $title }}">{{ $flag }}</button>
                @endforeach
            </div>
            BLADE;
    }
}
