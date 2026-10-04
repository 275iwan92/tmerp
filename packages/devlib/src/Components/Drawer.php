<?php

namespace Devlib\Components;

// Panel geser dari samping (kanan/kiri). Dibuka data-dl-open="<id>" atau Devlib.drawer.open(id).
class Drawer extends DevlibComponent
{
    public function __construct(
        public string $id = 'drawer',
        public ?string $title = null,
        public string $side = 'right',
        public string $width = '390px',
        public ?string $icon = null,
        // true = tombol Maximize di kepala: laci melebar satu layar penuh, Restore kembali ke lebar semula (user 2026-10-04; mode diingat per laci).
        public bool $maximize = false,
    ) {}

    public function tag(): string
    {
        return 'x-dl::drawer id='.$this->id;
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-sdrawer', 'dl-sdrawer--'.$side]) }} {!! $dl() !!} data-dl-drawer-id="{{ $id }}" style="--dl-drawer-w:{{ $width }}">
                <div class="dl-sdrawer__backdrop" data-dl-close-modal></div>
                <aside class="dl-sdrawer__panel" role="dialog" aria-label="{{ $title }}">
                    <div class="dl-dr-head">@if ($icon)<i class="fa-solid {{ \Devlib\Icon::fa($icon) }} dl-dr-ico"></i>@endif<span class="dl-dr-title">{{ $title }}</span>@if ($maximize)<button type="button" class="dl-dr-x dl-dr-max" data-dl-dr-max data-title-max="{{ \Devlib\Lang::text('Maximize') }}" data-title-restore="{{ \Devlib\Lang::text('Restore') }}" title="{{ \Devlib\Lang::text('Maximize') }}"><i class="fa-regular fa-window-maximize"></i></button>@endif<button type="button" class="dl-dr-x" data-dl-close-modal aria-label="{{ \Devlib\Lang::text('Close') }}"><i class="fa-solid fa-xmark"></i></button></div>
                    <div class="dl-sdrawer__body">{{ $slot }}</div>
                    @isset($footer)<div class="dl-modal__foot">{{ $footer }}</div>@endisset
                </aside>
            </div>
            BLADE;
    }
}
