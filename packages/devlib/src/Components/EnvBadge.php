<?php

namespace Devlib\Components;

/*
 * REMARK
 * Tujuan       : Teks lingkungan: akhiran judul logo ('- mockup ver 0.20', part title) dan baris host/IP kecil (part where, di bawah nama pengguna); tidak tampil di situs live.
 * Sumber hulu  : binding container 'devlib.env_title' / 'devlib.env_where' yang diisi aplikasi (kosong / tidak ada = tidak tampil); DevLib tidak tahu isinya.
 * Input        : part title|where, variant title|side|login, label (opsional).
 * Output       : <small class="dl-envbadge">teks</small> atau kosong.
 * Dipakai oleh : LayerMenu (judul logo + bawah nama pengguna) dan halaman login tmerp (judul logo + bawah form).
 * Status       : Aktif. Teks terang di sidebar gelap (token --dl-side-ink), hitam di login (varian login); tanpa pil, latar, atau bingkai.
 */
class EnvBadge extends DevlibComponent
{
    // The label is data built by the host app (already translated); it is not a dictionary key.
    protected const TEXT_PROPS = [];

    protected const TEXT_LISTS = [];

    public function __construct(public string $part = 'where', public string $variant = 'side', public ?string $label = null)
    {
        $key = 'devlib.env_'.$part;
        $this->label ??= app()->bound($key) ? (string) app($key) : '';
    }

    public function tag(): string
    {
        return 'x-dl::env-badge part='.$this->part;
    }

    public function render()
    {
        return <<<'BLADE'
            @if ($label !== '')
                <small {{ $attributes->class(['dl-envbadge', 'dl-envbadge--'.$variant]) }} {!! $dl() !!}>{{ $label }}</small>
            @endif
            BLADE;
    }
}
