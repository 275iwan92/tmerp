<?php

namespace Devlib\Components;

// Kartu identitas pegawai (mockup_ID_card: 6 desain). design: blue | green | gold | red; orientation: landscape | portrait.
// Ukuran CR80 (85,6 x 54 mm). QR otomatis berisi ID (bisa diganti qr). Foto kosong = inisial.
class IdCard extends DevlibComponent
{
    public function __construct(
        public string $name = '',
        public string $position = '',
        public ?string $division = null,
        public string $id = '',
        public ?string $photo = null,
        public string $design = 'blue',
        public string $orientation = 'landscape',
        public string $company = 'TELASIH MAKMUR',
        public string $initials = 'TM',
        public string $motto = 'People · Process · A Better Tomorrow',
        public array $values = [],
        public string $footer = 'Work Safely · Grow Together',
        public ?string $qr = null,
        public bool $nfc = true,
    ) {}

    public function tag(): string
    {
        return 'x-dl::id-card design='.$this->design.' '.$this->orientation;
    }

    public function ini(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-idc', 'dl-idc--'.$design, 'dl-idc--'.$orientation]) }} {!! $dl() !!}>
                @if ($orientation === 'portrait')<span class="dl-idc__slot"></span>@endif
                <div class="dl-idc__head"><span class="dl-idc__logo">{{ $initials }}</span><span class="dl-idc__co"><b>{{ $company }}</b><small>{{ $motto }}</small></span>
                    @if ($values && $orientation === 'landscape')<ul class="dl-idc__values">@foreach ($values as $v)<li>{{ $v }}</li>@endforeach</ul>@endif</div>
                <div class="dl-idc__body">
                    <span class="dl-idc__photo">@if ($photo)<img src="{{ $photo }}" alt="">@else{{ $ini() }}@endif</span>
                    <div class="dl-idc__who"><b class="dl-idc__name">{{ $name }}</b><span class="dl-idc__pos">{{ $position }}</span>@if ($division && $orientation === 'landscape')<span class="dl-idc__div">{{ $division }}</span>@endif<span class="dl-idc__id">ID <b>{{ $id }}</b></span></div>
                    <div class="dl-idc__codes"><x-dl::qr :text="$qr ?? $id" :size="$orientation === 'portrait' ? 64 : 58" />@if ($nfc)<i class="fa-solid fa-wifi dl-idc__nfc" title="NFC"></i>@endif</div>
                </div>
                <div class="dl-idc__foot">@if ($orientation === 'portrait' && $values){!! collect($values)->map(fn ($v) => e($v))->implode('<br>') !!}@else{{ $footer }}@endif</div>
            </div>
            BLADE;
    }
}
