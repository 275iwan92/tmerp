<?php

namespace Devlib\Components;

// Tombol baku. href terisi = dirender <a>. variant link = tampil sebagai tautan.
class Button extends DevlibComponent
{
    public function __construct(
        public string $label = '',
        public string $variant = 'primary',
        public string $size = 'md',
        public string $type = 'button',
        public ?string $href = null,
        public ?string $icon = null,
        public bool $block = false,
        public ?string $i18n = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::button variant='.$this->variant.' size='.$this->size;
    }

    public function classes(): string
    {
        return 'dl-btn dl-btn--'.$this->variant.' dl-btn--'.$this->size.($this->block ? ' dl-btn--block' : '');
    }

    public function render()
    {
        return <<<'BLADE'
            @if ($href)
                <a href="{{ $href }}" {{ $attributes->class($classes()) }} {!! $dl() !!} @if ($i18n) data-i18n="{{ $i18n }}" @endif>@if ($icon)<span class="dl-btn__icon">{{ $icon }}</span>@endif{{ $label }}{{ $slot }}</a>
            @else
                <button type="{{ $type }}" {{ $attributes->class($classes()) }} {!! $dl() !!} @if ($i18n) data-i18n="{{ $i18n }}" @endif>@if ($icon)<span class="dl-btn__icon">{{ $icon }}</span>@endif{{ $label }}{{ $slot }}</button>
            @endif
            BLADE;
    }
}
