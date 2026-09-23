<?php

namespace Devlib\Components;

// Kartu status (mesin, dokumen, layanan): ikon + judul + badge status + nilai + keterangan + waktu.
// tone: accent | success | warn | danger | info | neutral. filter = bisa diklik sebagai penyaring (seperti Stat Box).
class StatusCard extends DevlibComponent
{
    public function __construct(
        public string $title = '',
        public string $status = '',
        public string $tone = 'accent',
        public string $icon = 'info',
        public ?string $value = null,
        public ?string $desc = null,
        public ?string $meta = null,
        public bool $filter = false,
        public bool $active = false,
    ) {}

    public function tag(): string
    {
        return 'x-dl::status-card tone='.$this->tone;
    }

    public function render()
    {
        return <<<'BLADE'
            <{{ $filter ? 'button type=button' : 'div' }} {{ $attributes->class(['dl-scard', 'dl-scard--'.$tone, 'is-filter' => $filter, 'is-active' => $active]) }} {!! $dl() !!} @if ($filter) data-dl-stat @endif>
                <span class="dl-scard__icon"><i class="fa-solid {{ \Devlib\Icon::fa($icon) }}"></i></span>
                <span class="dl-scard__body">
                    <span class="dl-scard__top"><b class="dl-scard__title">{{ $title }}</b><span class="dl-badge dl-badge--pill dl-badge--{{ $tone }}"><span class="dl-scard__dot"></span>{{ $status }}</span></span>
                    @if ($value !== null)<span class="dl-scard__value">{{ $value }}</span>@endif
                    @if ($desc)<span class="dl-scard__desc">{{ $desc }}</span>@endif
                    @if ($meta)<span class="dl-scard__meta"><i class="fa-regular fa-clock"></i> {{ $meta }}</span>@endif
                </span>
            </{{ $filter ? 'button' : 'div' }}>
            BLADE;
    }
}
