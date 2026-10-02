<?php

namespace Devlib\Components;

// Lembar cetak label: isi slot = deretan x-dl::label. Tombol Print mencetak labelnya saja (bilah dan layar lain tidak
// ikut), satu label tidak terpotong antar halaman. $back = alamat tombol Back; $count = jumlah label (tampil di bilah).
class LabelSheet extends DevlibComponent
{
    protected const TEXT_PROPS = ['title'];

    public function __construct(
        public string $title = 'Labels',
        public ?string $back = null,
        public ?int $count = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::label-sheet';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-labelsheet') }} {!! $dl() !!}>
                <div class="dl-labelsheet__bar">
                    <b>{{ $title }}</b>@if ($count !== null)<span>{{ $count }} {{ \Devlib\Lang::text('labels') }}</span>@endif
                    <span class="dl-labelsheet__spacer"></span>
                    {{ $tools ?? '' }}
                    @if ($back)<a class="dl-btn dl-btn--secondary dl-btn--md" href="{{ $back }}"><i class="fa-solid fa-arrow-left"></i> Back</a>@endif
                    <button type="button" class="dl-btn dl-btn--primary dl-btn--md" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
                </div>
                <div class="dl-labelsheet__labels">{{ $slot }}</div>
            </div>
            BLADE;
    }
}
