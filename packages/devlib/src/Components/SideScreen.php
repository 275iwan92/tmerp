<?php

namespace Devlib\Components;

// Side Screen (user 2026-10-04): jendela layar PENUH untuk layar akun yang "banyak aspek" (Help & support, Edit profile).
// Turunan Drawer: dibuka dari kanan, tetapi menutupi seluruh layar; tombol Float menjadikannya jendela mengambang ala OS
// Windows (dipindah dengan menyeret kepala, ukuran ditarik dari tepi / sudut, snap ke tepi layar; posisi & ukuran diingat
// per browser). Tidak ada minimize (ralat user). Isi = url (iframe, dimuat saat pertama dibuka) atau slot.
// Kotak cari di kepala menyaring bagian dan kartu di dalam isi (x-dl::side-screen.layout) lewat postMessage.
class SideScreen extends Drawer
{
    public function __construct(
        public string $id = 'side',
        public ?string $title = null,
        public ?string $subtitle = null,
        public ?string $icon = null,
        public ?string $url = null,
        public bool $search = true,
        // Ukuran awal saat mengambang (sebelum user mengubahnya).
        public string $width = '1140px',
        public string $height = '680px',
    ) {
        parent::__construct(id: $id, title: $title, side: 'right', width: $width, icon: $icon);
    }

    public function tag(): string
    {
        return 'x-dl::side-screen id='.$this->id;
    }

    public function source(): string
    {
        return $this->url ? 'layar '.$this->url : 'slot';
    }

    public function render()
    {
        return <<<'BLADE'
            <section {{ $attributes->class(['dl-ss']) }} {!! $dl() !!} data-dl-side-screen="{{ $id }}" @if ($url) data-dl-ss-url="{{ $url }}" @endif
                data-dl-ss-size="{{ $width }} {{ $height }}" role="dialog" aria-label="{{ $title }}">
                <div class="dl-ss__head" data-dl-ss-drag>
                    @if ($icon)<span class="dl-ss__ico"><i class="fa-solid {{ \Devlib\Icon::fa($icon) }}"></i></span>@endif
                    <div class="dl-ss__titles"><div class="dl-ss__title">{{ $title }}</div>@if ($subtitle)<div class="dl-ss__sub">{{ $subtitle }}</div>@endif</div>
                    @if ($search)
                        <label class="dl-ss__search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" data-dl-ss-search placeholder="{{ \Devlib\Lang::text('Search in this panel…') }}" aria-label="{{ \Devlib\Lang::text('Search in this panel…') }}"><kbd>/</kbd></label>
                    @endif
                    <span class="dl-ss__esc">Esc</span>
                    <div class="dl-ss__win">
                        <button type="button" class="dl-ss__btn" data-dl-ss-float title="{{ \Devlib\Lang::text('Float window') }}" aria-label="{{ \Devlib\Lang::text('Float window') }}"><i class="fa-regular fa-window-restore"></i></button>
                        <button type="button" class="dl-ss__btn" data-dl-close title="{{ \Devlib\Lang::text('Close') }}" aria-label="{{ \Devlib\Lang::text('Close') }}"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                </div>
                <div class="dl-ss__body">
                    @if ($url)
                        {{-- name="dl-wide": halaman di dalamnya tahu ia dibuka di jendela ini (window.name bertahan saat pindah bagian / submit). --}}
                        <iframe class="dl-ss__frame" name="dl-wide" title="{{ $title }}"></iframe>
                    @else
                        {{ $slot }}
                    @endif
                </div>
                @foreach (['n', 's', 'e', 'w', 'ne', 'nw', 'se', 'sw'] as $edge)<span class="dl-ss__rs dl-ss__rs--{{ $edge }}" data-dl-ss-rs="{{ $edge }}"></span>@endforeach
            </section>
            BLADE;
    }
}
