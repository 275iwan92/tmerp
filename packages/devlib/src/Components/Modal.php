<?php

namespace Devlib\Components;

// Jendela modal. Dibuka tombol ber-atribut data-dl-open="<id>" atau Devlib.modal.open(id). Slot "footer" = tombol bawah.
class Modal extends DevlibComponent
{
    public function __construct(
        public string $id = 'modal',
        public ?string $title = null,
        public string $size = 'md',
        public bool $open = false,
        public ?string $icon = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::modal id='.$this->id;
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-mov', 'is-on' => $open]) }} {!! $dl() !!} data-dl-modal-id="{{ $id }}" role="dialog" aria-modal="true" aria-label="{{ $title }}">
                <div class="dl-modal dl-modal--{{ $size }}">
                    @if ($title)
                        <div class="dl-modal__head"><h3>@if ($icon)<i class="fa-solid {{ \Devlib\Icon::fa($icon) }}"></i> @endif{{ $title }}</h3><button type="button" class="dl-modal__x" data-dl-close-modal aria-label="{{ \Devlib\Lang::text('Close') }}">&times;</button></div>
                    @endif
                    <div class="dl-modal__body">{{ $slot }}</div>
                    @isset($footer)<div class="dl-modal__foot">{{ $footer }}</div>@endisset
                </div>
            </div>
            BLADE;
    }
}
