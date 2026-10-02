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
        'NEW' => ['✨', 'New Product', 'Never made before. Sales defines the product type, dimensions, material and finishing from scratch.', 'product type + dimensions + material', 'Sales → Design → Costing'],
        'RECALC' => ['🔁', 'Recalculate Price', 'The product & layout already exist, nothing changes. Only a new price is needed.', 'choose an existing product + qty', 'Sales → Costing (skip Design)'],
        'REVISE' => ['✏️', 'Change Specification', 'The product already exists but something changes: dimensions, material, colour or finishing.', 'choose an existing product + what changes', 'Sales → Design → Costing'],
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
                <h4>{{ \Devlib\Lang::text($title) }}</h4>
                <p>{{ \Devlib\Lang::text($desc) }}</p>
                <div class="mp-typecard__req">{{ \Devlib\Lang::text('Needs:') }} <b>{{ \Devlib\Lang::text($need) }}</b><br>{{ \Devlib\Lang::text('Flow:') }} {{ \Devlib\Lang::text($flow) }}</div>
                <x-dl::action-bar align="stretch">
                    <x-dl::button variant="secondary" icon="📂" label="Open saved data" data-inq-tab="{{ $type }}" />
                    @if ($picker)
                        <x-dl::button icon="＋" label="Create new data" data-dl-open="{{ $picker }}" />
                    @else
                        <x-dl::button icon="＋" label="Create new data" :href="$newUrl" />
                    @endif
                </x-dl::action-bar>
            </div>
            BLADE;
    }
}
