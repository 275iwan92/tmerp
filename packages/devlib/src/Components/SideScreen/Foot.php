<?php

namespace Devlib\Components\SideScreen;

use Devlib\Components\ActionBar;

// Baris aksi LENGKET di dasar bagian Side Screen (user 2026-10-04): tombol form (Request Correction, Send ticket, Save)
// selalu terlihat tanpa menggulir; teks kecil di kanan = keterangan (mis. "berlaku setelah verifikasi HR").
class Foot extends ActionBar
{
    public function __construct(
        public ?string $hint = null,
        public string $align = 'start',
    ) {
        parent::__construct(align: $align);
    }

    public function tag(): string
    {
        return 'x-dl::side-screen.foot';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-ssl__foot']) }} {!! $dl() !!}>
                <div class="dl-ssl__foot-acts">{{ $slot }}</div>
                @if ($hint)<span class="dl-ssl__foot-hint">{{ $hint }}</span>@endif
            </div>
            BLADE;
    }
}
