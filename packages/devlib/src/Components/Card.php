<?php

namespace Devlib\Components;

// Kotak berjudul untuk mengelompokkan isi (bagian form, panel info). Slot "actions" = tombol di kanan judul.
class Card extends DevlibComponent
{
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public string $variant = 'section',
        public ?string $icon = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::card variant='.$this->variant;
    }

    public function render()
    {
        return <<<'BLADE'
            <section {{ $attributes->class(['dl-card', 'dl-card--'.$variant]) }} {!! $dl() !!}>
                @if ($title || isset($actions))
                    <header class="dl-card__head">
                        <div>@if ($icon)<i class="fa-solid {{ \Devlib\Icon::fa($icon) }}"></i> @endif<b>{{ $title }}</b>@if ($description)<small>{{ $description }}</small>@endif</div>
                        @isset($actions)<div class="dl-card__actions">{{ $actions }}</div>@endisset
                    </header>
                @endif
                <div class="dl-card__body">{{ $slot }}</div>
            </section>
            BLADE;
    }
}
