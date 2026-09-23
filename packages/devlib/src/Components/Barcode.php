<?php

namespace Devlib\Components;

// Barcode 1D (JsBarcode dari cdnjs): format CODE128 (default) | EAN13 | EAN8 | CODE39 | ITF14 | UPC.
// Untuk label barang, nomor lot, surat jalan. Teks angka tampil di bawah garis.
class Barcode extends DevlibComponent
{
    public function __construct(
        public string $value = '',
        public string $format = 'CODE128',
        public int $height = 50,
        public float $width = 1.6,
        public bool $text = true,
    ) {}

    public function tag(): string
    {
        return 'x-dl::barcode format='.$this->format;
    }

    public function render()
    {
        return <<<'BLADE'
            <span {{ $attributes->class('dl-barcode') }} {!! $dl() !!}><svg data-dl-barcode data-value="{{ $value }}" data-format="{{ $format }}" data-height="{{ $height }}" data-width="{{ $width }}" data-text="{{ $text ? 1 : 0 }}" role="img" aria-label="Barcode {{ $value }}"></svg></span>
            BLADE;
    }
}
