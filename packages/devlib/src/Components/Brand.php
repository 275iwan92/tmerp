<?php

namespace Devlib\Components;

/* REMARK
 * Tujuan: lencana logo + nama + tagline aplikasi. tone: light (di atas gelap) | dark | auto.
 * Sumber hulu: prop dari pemanggil; identity() = identitas bawaan situs (config devlib.brand.*, lalu Telasih Makmur).
 * Input: name, tagline, tone, logo (url gambar; kosong = lencana gradasi bawaan).
 * Output: div.dl-brand (lencana + nama + tagline).
 * Dipakai oleh: Frame (login), Shell / LayerShell (sidebar), LoginBox, layar identitas tmerp.
 * Status: aktif.
 */
class Brand extends DevlibComponent
{
    /** Identitas bawaan situs: config('devlib.brand.*') bila diisi, selain itu Telasih Makmur (tmerp mengirim nilai sendiri). */
    public static function identity(): array
    {
        return [
            'name' => config('devlib.brand.name', 'TELASIH MAKMUR'),
            'tagline' => config('devlib.brand.tagline', 'packaging solution'),
            'logo' => config('devlib.brand.logo'),
        ];
    }

    public function __construct(
        public string $name = 'TELASIH MAKMUR',
        public string $tagline = 'packaging solution',
        public string $tone = 'light',
        public ?string $logo = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::brand tone='.$this->tone;
    }

    public function source(): string
    {
        return 'identitas perusahaan (tmerp: Settings > Identifikasi)';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-brand', 'dl-brand--'.$tone]) }} {!! $dl() !!}><span class="dl-logo-badge">@if ($logo)<img src="{{ $logo }}" alt="{{ $name }}">@endif</span><div><b>{{ $name }}</b><span>{{ $tagline }}</span></div></div>
            BLADE;
    }
}
