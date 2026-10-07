<?php

namespace Devlib\Components;

// Jejak pengiriman skematis (SVG, tanpa peta): gudang -> pelanggan, truk bergerak di sepanjang rute dan berhenti di titik
// sesuai TAHAP dokumen. Objek ini tidak tahu database: pemakai menyerahkan `stage` (hasil pemetaan status Delivery Order,
// lihat App\Support\V2\Data\DeliveryTrack). Tanpa GPS: posisi truk = titik tetap per tahap, kecuali `position` (0..1) diberikan.
// Tahap: draft -> confirmed -> shipped -> delivered; cancelled = rute berhenti, merah.
class DeliveryTrack extends DevlibComponent
{
    public const STAGES = ['draft' => 'Draft', 'confirmed' => 'Confirmed', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];

    // Titik truk di rute (0 = gudang, 1 = pelanggan) bila `position` tidak diberikan.
    public const AT = ['draft' => 0.0, 'confirmed' => 0.0, 'shipped' => 0.55, 'delivered' => 1.0, 'cancelled' => 0.0];

    /** @param array<string, string> $times tahap => waktu yang sudah jadi teks (nilai data, dibungkus raw() oleh pemakai) */
    public function __construct(
        public string $stage = 'draft',
        public string $from = 'Warehouse',
        public string $to = 'Customer',
        public ?float $position = null,
        public array $times = [],
        public string $title = '',
        public string $subtitle = '',
    ) {
        if (! isset(self::AT[$this->stage])) {
            $this->stage = 'draft';
        }
    }

    public function tag(): string
    {
        return 'x-dl::delivery-track';
    }

    public function source(): string
    {
        return 'status Delivery Order (do_delivery.status + do_delivery_pod) lewat App\Support\V2\Data\DeliveryTrack';
    }

    public function jsClass(): ?string
    {
        return 'DeliveryTrack';
    }

    public function target(): float
    {
        return max(0.0, min(1.0, $this->position ?? self::AT[$this->stage]));
    }

    public function moving(): bool
    {
        return $this->stage === 'shipped';
    }

    // Langkah di bawah rute: done / current / pending. Cancelled: semua pending (rute berhenti, banner merah).
    /** @return list<array{key: string, label: string, state: string, time: string}> */
    public function steps(): array
    {
        $keys = array_keys(self::STAGES);
        $at = array_search($this->stage, $keys, true);
        $out = [];
        foreach (self::STAGES as $key => $label) {
            $i = array_search($key, $keys, true);
            $state = $at === false ? 'pending' : ($i < $at || $this->stage === 'delivered' ? 'done' : ($i === $at ? 'current' : 'pending'));
            $out[] = ['key' => $key, 'label' => $label, 'state' => $state, 'time' => $this->times[$key] ?? ''];
        }

        return $out;
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-track', 'dl-track--'.$stage]) }} {!! $dl() !!} data-dl-track data-target="{{ $target() }}" data-moving="{{ $moving() ? 1 : 0 }}">
                @if ($title || $subtitle)<div class="dl-track__head">@if ($title)<b>{{ $title }}</b>@endif @if ($subtitle)<span>{{ $subtitle }}</span>@endif</div>@endif
                <svg viewBox="0 0 600 170" role="img" aria-label="{{ $from }} → {{ $to }}: {{ $stage === 'cancelled' ? 'Cancelled' : \Devlib\Components\DeliveryTrack::STAGES[$stage] }}">
                    <path class="dl-track__road" data-dl-track-road d="M60 120 C160 120 170 45 300 62 S450 140 540 52"/>
                    <path class="dl-track__trail" data-dl-track-trail d="M60 120 C160 120 170 45 300 62 S450 140 540 52"/>
                    <path class="dl-track__flow" d="M60 120 C160 120 170 45 300 62 S450 140 540 52"/>
                    <g class="dl-track__node dl-track__node--from"><circle cx="60" cy="120" r="12"/><text x="60" y="150" text-anchor="middle">{{ $from }}</text></g>
                    <g class="dl-track__node dl-track__node--to"><circle cx="540" cy="52" r="12"/><text x="540" y="30" text-anchor="middle">{{ $to }}</text></g>
                    <g class="dl-track__truck" data-dl-track-truck><circle r="14"/><path d="M-7 -4 h9 v8 h-9 z M2 -2 h5 l3 3 v3 h-8 z"/></g>
                </svg>
                <ol class="dl-track__steps">
                    @foreach ($steps() as $s)
                        <li class="is-{{ $s['state'] }}"><i></i><b>{{ $s['label'] }}</b>@if ($s['time'])<small>{{ $s['time'] }}</small>@endif</li>
                    @endforeach
                </ol>
                @if ($stage === 'cancelled')<div class="dl-track__cancel">Cancelled</div>@endif
            </div>
            BLADE;
    }
}
