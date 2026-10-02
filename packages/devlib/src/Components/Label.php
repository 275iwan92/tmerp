<?php

namespace Devlib\Components;

// Label tempel gudang: lokasi (bin) atau item. Kode besar + QR (dipindai kamera HP) + barcode 1D (dipindai scanner laser)
// + baris keterangan (kapasitas, lot, qty, ...). Dipakai di layar (kartu) dan di lembar cetak (x-dl::label-sheet).
// size: sm (60 x 35 mm) | md (100 x 50 mm, bawaan) | lg (100 x 150 mm).  tone="danger" = pita merah (mis. dead stock).
// $lines = [['label' => 'Capacity', 'value' => '3,000 kg'], ...]  -- label diterjemahkan, value = data apa adanya.
class Label extends DevlibComponent
{
    public const SIZES = ['sm' => '60 x 35 mm', 'md' => '100 x 50 mm', 'lg' => '100 x 150 mm'];

    // Kode, nama, isi baris = data (tidak diterjemahkan); hanya judul kecil yang label.
    protected const TEXT_PROPS = ['title'];

    public function __construct(
        public string $code = '',
        public ?string $title = null,
        public ?string $name = null,
        public array $lines = [],
        public ?string $qr = null,
        public ?string $barcode = null,
        public string $size = 'md',
        public ?string $tone = null,
        public ?string $footer = null,
    ) {
        $this->size = isset(self::SIZES[$size]) ? $size : 'md';
    }

    public function tag(): string
    {
        return 'x-dl::label size='.$this->size;
    }

    public function qrSize(): int
    {
        return ['sm' => 78, 'md' => 118, 'lg' => 200][$this->size];
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-label', 'dl-label--'.$size, 'dl-label--'.$tone => $tone]) }} {!! $dl() !!}>
                @if ($qr)<span class="dl-label__qr" data-dl-qrcode data-text="{{ $qr }}" data-size="{{ $qrSize() }}"></span>@endif
                <div class="dl-label__body">
                    @if ($title)<small class="dl-label__title">{{ $title }}</small>@endif
                    <b class="dl-label__code">{{ $code }}</b>
                    @if ($name)<span class="dl-label__name">{{ $name }}</span>@endif
                    @if ($lines)
                        <dl class="dl-label__lines">@foreach ($lines as $l)<div><dt>{{ \Devlib\Lang::text($l['label'] ?? '') }}</dt><dd>{{ $l['value'] ?? '' }}</dd></div>@endforeach</dl>
                    @endif
                    @if ($barcode)<span class="dl-label__bar"><svg data-dl-barcode data-value="{{ $barcode }}" data-format="CODE128" data-height="{{ $size === 'sm' ? 22 : 34 }}" data-width="{{ $size === 'sm' ? 1 : 1.3 }}" data-text="0" role="img" aria-label="Barcode {{ $barcode }}"></svg></span>@endif
                    @if ($footer)<small class="dl-label__foot">{{ $footer }}</small>@endif
                </div>
            </div>
            BLADE;
    }
}
