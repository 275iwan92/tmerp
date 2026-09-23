<?php

namespace Devlib\Components;

// Tombol ikon persegi (kolom Aksi grid, toolbar). icon = arti baku (edit, delete, open, ...).
class IconButton extends DevlibComponent
{
    public function __construct(
        public string $icon = 'edit',
        public string $title = '',
        public string $variant = 'default',
        public ?string $href = null,
        public ?string $confirm = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::icon-button icon='.$this->icon;
    }

    public function render()
    {
        return <<<'BLADE'
            @if ($href)
                <a href="{{ $href }}" {{ $attributes->class(['dl-iconbtn', 'dl-iconbtn--'.$variant]) }} {!! $dl() !!} title="{{ $title }}" aria-label="{{ $title }}"><i class="fa-solid {{ \Devlib\Icon::fa($icon) }}"></i></a>
            @else
                <button type="button" {{ $attributes->class(['dl-iconbtn', 'dl-iconbtn--'.$variant]) }} {!! $dl() !!} title="{{ $title }}" aria-label="{{ $title }}" @if ($confirm) data-dl-confirm="{{ $confirm }}" @endif><i class="fa-solid {{ \Devlib\Icon::fa($icon) }}"></i></button>
            @endif
            BLADE;
    }
}
