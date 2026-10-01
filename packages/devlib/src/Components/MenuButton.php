<?php

namespace Devlib\Components;

// Tombol + daftar pilihan (dropdown) -- mis. Print ▾ / Email ▾ bila satu form punya lebih dari satu dokumen cetak
// (form ProDev: Master Card dan ACC Digital). Tampilan daftar = daftar x-dl::doc-share.
// :items = [['label' => .., 'icon' => .., 'sub' => teks kecil, 'href' => alamat ATAU 'act' => nama aksi,
//            'blank' => true (buka tab baru), 'disabled' => true, 'title' => alasan]].
// Item ber-href membuka alamat itu; item ber-act memancarkan event dl:menu {act} (bubbles) untuk dijalankan pemakainya.
class MenuButton extends DevlibComponent
{
    public function __construct(
        public array $items = [],
        public ?string $preset = null,
        public string $label = '',
        public ?string $icon = null,
        public string $variant = 'secondary',
        public string $size = 'md',
    ) {}

    public function tag(): string
    {
        return 'x-dl::menu-button';
    }

    public function render()
    {
        return <<<'BLADE'
            <span {{ $attributes->class('dl-docshare dl-menubtn') }} {!! $dl() !!} data-dl-menubtn>
                <span class="dl-docshare__menu">
                    @if ($preset)
                        <x-dl::button :preset="$preset" :size="$size" data-mb-toggle><i class="fa-solid fa-caret-down dl-docshare__caret"></i></x-dl::button>
                    @else
                        <x-dl::button :label="$label" :icon="$icon" :variant="$variant" :size="$size" data-mb-toggle><i class="fa-solid fa-caret-down dl-docshare__caret"></i></x-dl::button>
                    @endif
                    <span class="dl-docshare__list" role="menu">
                        @foreach ($items as $it)
                            <button type="button" role="menuitem" data-mb-item data-mb-act="{{ $it['act'] ?? '' }}" data-href="{{ $it['href'] ?? '' }}" @if (! empty($it['blank'])) data-blank @endif
                                @if (! empty($it['title'])) title="{{ $it['title'] }}" @endif @disabled(! empty($it['disabled']))><i class="fa-solid {{ \Devlib\Icon::fa($it['icon'] ?? 'fa-file-lines') }}"></i> {{ $it['label'] ?? '' }}@if (! empty($it['sub']))<small>{{ $it['sub'] }}</small>@endif</button>
                        @endforeach
                    </span>
                </span>
            </span>
            BLADE;
    }
}
