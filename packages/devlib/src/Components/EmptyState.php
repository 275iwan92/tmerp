<?php

namespace Devlib\Components;

// Tampilan saat data kosong: ikon, judul, keterangan, dan tombol aksi (slot).
class EmptyState extends DevlibComponent
{
    public function __construct(
        public string $title = 'Belum ada data',
        public ?string $message = null,
        public string $icon = 'file',
    ) {}

    public function tag(): string
    {
        return 'x-dl::empty-state';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-empty') }} {!! $dl() !!}>
                <span class="dl-empty__icon"><i class="fa-solid {{ \Devlib\Icon::fa($icon) }}"></i></span>
                <b class="dl-empty__title">{{ $title }}</b>
                @if ($message)<p class="dl-empty__msg">{{ $message }}</p>@endif
                @if (trim($slot) !== '')<div class="dl-empty__actions">{{ $slot }}</div>@endif
            </div>
            BLADE;
    }
}
