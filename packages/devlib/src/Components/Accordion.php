<?php

namespace Devlib\Components;

// Satu panel lipat. Sendiri = vertikal biasa; di dalam x-dl::accordion-group ikut arah & aturan grupnya.
class Accordion extends DevlibComponent
{
    public function __construct(
        public string $title = '',
        public bool $open = false,
        public ?string $icon = null,
        public ?string $badge = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::accordion';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-accordion', 'is-open' => $open]) }} {!! $dl() !!} data-dl-acc>
                <button type="button" class="dl-accordion__head" aria-expanded="{{ $open ? 'true' : 'false' }}">
                    @if ($icon)<i class="fa-solid {{ \Devlib\Icon::fa($icon) }} dl-accordion__icon"></i>@endif
                    <span class="dl-accordion__title">{{ $title }}</span>
                    @if ($badge !== null)<span class="dl-accordion__badge">{{ $badge }}</span>@endif
                    <i class="fa-solid fa-chevron-down dl-accordion__chev"></i>
                </button>
                <div class="dl-accordion__body"><div class="dl-accordion__inner"><div class="dl-accordion__pad">{{ $slot }}</div></div></div>
            </div>
            BLADE;
    }
}
