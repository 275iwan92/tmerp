<?php

namespace Devlib\Components;

use Devlib\Lang;

// Panduan ukuran label + cara cetak (user 2026-10-02: "jelaskan ukuran label agar mudah di cetak"): tabel tiga ukuran
// (ukuran nyata, dipakai untuk apa, isi per lembar A4) dan dua cara cetak (gulungan label / kertas A4), plus arti warna
// pita bila $colors diisi. Dipasang di layar cetak label dan di lembar cetaknya.
// $size   = ukuran yang sedang dipilih (barisnya ditebalkan), null = tidak ada.
// $colors = [['color' => 'accent', 'label' => 'January'], ...] arti warna pita (label = data, sudah diterjemahkan pemakai).
class LabelGuide extends DevlibComponent
{
    public const USES = [
        'sm' => 'Item label for small packs: boxes, ink cans, rolls.',
        'md' => 'Bin / rack label and item label on a stack.',
        'lg' => 'Pallet label, readable from a few metres away.',
    ];

    public const TIPS = [
        'Label roll (label printer): in the print dialog choose the paper size of the roll, margins None, scale 100%. One label = one page.',
        'A4 sheet (office printer): paper A4, margins None or Minimum, scale 100%, turn on Background graphics so the colour band prints. Cut along the label borders.',
        'No colour printer: the band still prints the label type and the mark as text, so the label stays readable.',
    ];

    public function __construct(
        public ?string $size = null,
        public array $colors = [],
        public bool $open = false,
    ) {}

    public function tag(): string
    {
        return 'x-dl::label-guide';
    }

    public function rows(): array
    {
        $rows = [];
        foreach (Label::SPEC as $k => $s) {
            $rows[] = ['key' => $k, 'size' => Label::SIZES[$k], 'use' => Lang::text(self::USES[$k]), 'a4' => $s['a4']];
        }

        return $rows;
    }

    public function render()
    {
        return <<<'BLADE'
            <details {{ $attributes->class('dl-labelguide') }} {!! $dl() !!} @if ($open) open @endif>
                <summary><i class="fa-solid fa-ruler-combined"></i> {{ \Devlib\Lang::text('Label sizes and how to print') }}</summary>
                <table class="dl-labelguide__table">
                    <thead><tr><th>{{ \Devlib\Lang::text('Size') }}</th><th>{{ \Devlib\Lang::text('Used for') }}</th><th class="num">{{ \Devlib\Lang::text('Labels per A4 sheet') }}</th></tr></thead>
                    <tbody>@foreach ($rows() as $r)<tr class="{{ $r['key'] === $size ? 'is-active' : '' }}"><td><b>{{ $r['size'] }}</b></td><td>{{ $r['use'] }}</td><td class="num">{{ $r['a4'] }}</td></tr>@endforeach</tbody>
                </table>
                <ul class="dl-labelguide__tips">@foreach (\Devlib\Components\LabelGuide::TIPS as $tip)<li>{{ \Devlib\Lang::text($tip) }}</li>@endforeach</ul>
                @if ($colors)
                    <div class="dl-labelguide__colors"><b>{{ \Devlib\Lang::text('Colour band') }}</b>@foreach ($colors as $c)<span class="dl-labelguide__chip" data-color="{{ $c['color'] }}">{{ $c['label'] }}</span>@endforeach</div>
                @endif
            </details>
            BLADE;
    }
}
