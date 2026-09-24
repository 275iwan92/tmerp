<?php

namespace MockupParts\Components\Sales;

use Devlib\Components\DevlibComponent;

// Kartu pilihan jenis Inquiry (mockup inquiry_main_hub.html): ikon + jenis + judul + penjelasan + syarat/alur
// + 2 tombol: "Pilih data tersimpan" (menyaring worklist ke jenis ini) dan "+ Buat data baru"
// (NEW = langsung ke form; RECALC/REVISE = pilih produk existing dulu lewat modal).
class InquiryTypeCard extends DevlibComponent
{
    // jenis => [ikon, judul, penjelasan, perlu, alur]
    public const TYPES = [
        'NEW' => ['✨', 'Produk Baru', 'Belum pernah dibuat. Sales menentukan tipe produk, dimensi, bahan, dan finishing dari nol.', 'tipe produk + dimensi + bahan', 'Sales → Design → Costing'],
        'RECALC' => ['🔁', 'Hitung Ulang Harga', 'Produk & layout sudah ada, tidak ada yang berubah. Hanya perlu harga baru.', 'pilih produk existing + qty', 'Sales → Costing (lewati Design)'],
        'REVISE' => ['✏️', 'Ubah Spesifikasi', 'Produk sudah ada tapi ada yang berubah: dimensi, bahan, warna, atau finishing.', 'pilih produk existing + apa yang berubah', 'Sales → Design → Costing'],
    ];

    public function __construct(
        public string $type = 'NEW',
        public ?string $newUrl = null,
        public ?string $picker = null,
    ) {}

    public function tag(): string
    {
        return 'x-mp::sales.inquiry-type-card type='.$this->type;
    }

    public function source(): string
    {
        return 'tm2sdi.iqp_inquiry.inquiry_type (FK -> iqp_type) = '.$this->type;
    }

    public function info(): array
    {
        return self::TYPES[$this->type];
    }

    public function render()
    {
        return <<<'BLADE'
            @php([$emoji, $title, $desc, $need, $flow] = $info())
            <div {{ $attributes->class(['mp-typecard', 'mp-typecard--'.strtolower($type)]) }} {!! $dl() !!}>
                <div class="mp-typecard__emoji">{{ $emoji }}</div>
                <x-dl::badge :label="$type" :tone="['NEW' => 'info', 'RECALC' => 'accent', 'REVISE' => 'warn'][$type]" />
                <h4>{{ $title }}</h4>
                <p>{{ $desc }}</p>
                <div class="mp-typecard__req">Perlu: <b>{{ $need }}</b><br>Alur: {{ $flow }}</div>
                <x-dl::action-bar align="stretch">
                    <x-dl::button variant="secondary" icon="📂" label="Pilih data tersimpan" data-inq-tab="{{ $type }}" />
                    @if ($picker)
                        <x-dl::button icon="＋" label="Buat data baru" data-dl-open="{{ $picker }}" />
                    @else
                        <x-dl::button icon="＋" label="Buat data baru" :href="$newUrl" />
                    @endif
                </x-dl::action-bar>
            </div>
            BLADE;
    }
}
