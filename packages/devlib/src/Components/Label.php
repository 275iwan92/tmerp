<?php

namespace Devlib\Components;

// Label tempel gudang. Tiga jenis yang SENGAJA tampak berbeda (kind): location (bin / rak), item (isi satu bin),
// pallet (satu palet: nomor palet, qty besar, tanggal datang, kode palet supplier, kedaluwarsa).
// Tiap label: pita berwarna di atas (jenis + ikon + tanda, mis. bulan datang) = penanda warna yang tetap terbaca di
// printer hitam-putih karena tulisannya ikut tercetak; kode besar; QR (kamera HP) + barcode 1D (scanner laser); baris
// keterangan; tag (Expired, Expires soon, Dead stock).
// size : sm 60 x 40 mm | md 100 x 50 mm (bawaan) | lg 100 x 150 mm -- ukuran nyata, lihat x-dl::label-guide.
// color: accent|green|tangerine|violet|lime|pink|navy|yellow|teal|orange|amber|neutral|danger (warna pita).
// tone="danger" = garis merah tebal di kiri (dead stock / kedaluwarsa).
// $lines = [['label' => 'Capacity', 'value' => '3,000 kg'], ...]  -- label diterjemahkan, value = data apa adanya.
// $tags  = [['label' => 'Expired', 'tone' => 'danger'|'warn'|'info'], ...] -- label diterjemahkan.
class Label extends DevlibComponent
{
    public const SIZES = ['sm' => '60 x 40 mm', 'md' => '100 x 50 mm', 'lg' => '100 x 150 mm'];

    // Ukuran nyata (mm) dan jumlah label per lembar A4 (tepi 5 mm, label rapat).
    public const SPEC = ['sm' => ['w' => 60, 'h' => 40, 'a4' => 21], 'md' => ['w' => 100, 'h' => 50, 'a4' => 10], 'lg' => ['w' => 100, 'h' => 150, 'a4' => 2]];

    public const KINDS = ['location' => ['Location', 'fa-location-dot'], 'item' => ['Item', 'fa-tag'], 'pallet' => ['Pallet', 'fa-pallet']];

    // Penanda warna bulan datang (Jan..Des). Merah tidak dipakai: merah = dead stock / kedaluwarsa.
    public const MONTH_COLORS = [1 => 'accent', 'green', 'tangerine', 'violet', 'lime', 'pink', 'navy', 'yellow', 'teal', 'orange', 'amber', 'neutral'];

    // Kode, nama, isi baris, tanda = data (tidak diterjemahkan); judul kecil = label.
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
        public ?string $kind = null,
        public ?string $color = null,
        public ?string $mark = null,
        public ?string $highlight = null,
        public array $tags = [],
    ) {
        $this->size = isset(self::SIZES[$size]) ? $size : 'md';
        $this->kind = isset(self::KINDS[$kind ?? '']) ? $kind : null;
        $this->title ??= $this->kind ? self::KINDS[$this->kind][0] : null;
    }

    public function tag(): string
    {
        return 'x-dl::label size='.$this->size.($this->kind ? ' kind='.$this->kind : '');
    }

    public static function monthColor(?string $date): string
    {
        $m = $date ? (int) substr($date, 5, 2) : 0;

        return self::MONTH_COLORS[$m] ?? 'neutral';
    }

    public function qrSize(): int
    {
        return ['sm' => 62, 'md' => 112, 'lg' => 150][$this->size];
    }

    public function icon(): ?string
    {
        return $this->kind ? self::KINDS[$this->kind][1] : null;
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-label', 'dl-label--'.$size, 'dl-label--'.$tone => $tone, 'dl-label--'.$kind => $kind]) }} data-color="{{ $color ?: 'neutral' }}" {!! $dl() !!}>
                @if ($title || $mark)
                    <div class="dl-label__band">@if ($icon())<i class="fa-solid {{ $icon() }}"></i>@endif<b>{{ $title }}</b>@if ($mark)<span>{{ $mark }}</span>@endif</div>
                @endif
                <div class="dl-label__main">
                    @if ($qr)<span class="dl-label__qr" data-dl-qrcode data-text="{{ $qr }}" data-size="{{ $qrSize() }}"></span>@endif
                    <div class="dl-label__body">
                        <b class="dl-label__code">{{ $code }}</b>
                        @if ($name)<span class="dl-label__name">{{ $name }}</span>@endif
                        @if ($highlight)<b class="dl-label__hi">{{ $highlight }}</b>@endif
                        @if ($lines)
                            <dl class="dl-label__lines">@foreach ($lines as $l)<div><dt>{{ \Devlib\Lang::text($l['label'] ?? '') }}</dt><dd>{{ $l['value'] ?? '' }}</dd></div>@endforeach</dl>
                        @endif
                        @if ($tags)
                            <span class="dl-label__tags">@foreach ($tags as $t)<em class="dl-label__tag dl-label__tag--{{ $t['tone'] ?? 'info' }}">{{ \Devlib\Lang::text($t['label'] ?? '') }}</em>@endforeach</span>
                        @endif
                        @if ($barcode)<span class="dl-label__bar"><svg data-dl-barcode data-value="{{ $barcode }}" data-format="CODE128" data-height="{{ ['sm' => 18, 'md' => 28, 'lg' => 44][$size] }}" data-width="{{ $size === 'sm' ? 1 : 1.3 }}" data-text="0" role="img" aria-label="Barcode {{ $barcode }}"></svg></span>@endif
                        @if ($footer)<small class="dl-label__foot">{{ $footer }}</small>@endif
                    </div>
                </div>
            </div>
            BLADE;
    }
}
