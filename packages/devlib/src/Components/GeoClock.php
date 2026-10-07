<?php

namespace Devlib\Components;

use Devlib\Lang;

// Absen berbasis lokasi (GPS geofencing, dipakai ponsel): membaca posisi perangkat, menanyakan jarak ke lokasi kerja ke server
// (`check-url`, JSON {lat, lng} -> {distance, in_radius}), menampilkan jarak + batang radius + lencana, lalu mengirim Absen Masuk /
// Pulang (`clock-url`, JSON {type: IN|OUT, lat, lng} -> {message, tone, history}). Objek ini tidak tahu database: server yang
// menghitung jarak dan memutuskan status (posisi dari perangkat tidak dipercaya untuk keputusan). Riwayat = [['type' => IN|OUT,
// 'time' => teks jadi, 'status' => teks jadi, 'tone' => success|warn|danger]] (nilai data, dibungkus raw() oleh pemakai).
// Pesan galat izin lokasi / GPS dibaca dari atribut data-t* yang sudah diterjemahkan di server.
class GeoClock extends DevlibComponent
{
    /** @param list<array{type: string, time: string, status: string, tone?: string}> $history */
    public function __construct(
        public string $checkUrl = '',
        public string $clockUrl = '',
        public int $radius = 200,
        public array $history = [],
        public string $title = '',
        public string $subtitle = '',
        public ?string $source = null,
    ) {
        $this->history = array_map(fn (array $r) => array_map(fn ($v) => is_string($v) ? Lang::plain($v) : $v, $r), $this->history);
    }

    public function tag(): string
    {
        return 'x-dl::geo-clock';
    }

    public function source(): string
    {
        return $this->source ?? 'att_mobile_gps_log + lokasi kerja (jarak dihitung server)';
    }

    public function jsClass(): ?string
    {
        return 'GeoClock';
    }

    /** @return array<string, string> teks yang dipakai JS, sudah diterjemahkan */
    public function texts(): array
    {
        return ['waiting' => Lang::text('Waiting for GPS location permission…'), 'unsupported' => Lang::text('This device does not support GPS.'),
            'denied' => Lang::text('Location permission denied or GPS could not be read.'), 'meters' => Lang::text('meters'),
            'inside' => Lang::text('Inside the work location radius'), 'outside' => Lang::text('Outside the work location radius'),
            'in' => Lang::text('Clock In'), 'out' => Lang::text('Clock Out'), 'failed' => Lang::text('Could not send. Try again.'), 'empty' => Lang::text('No attendance yet today.')];
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-geo') }} {!! $dl() !!} data-dl-geo data-check-url="{{ $checkUrl }}" data-clock-url="{{ $clockUrl }}" data-radius="{{ $radius }}"
                data-t="{{ json_encode($texts()) }}" data-history="{{ json_encode($history) }}">
                @if ($title || $subtitle)<div class="dl-geo__head">@if ($title)<b>{{ $title }}</b>@endif @if ($subtitle)<span>{{ $subtitle }}</span>@endif</div>@endif
                <div class="dl-geo__box">
                    <small>{{ \Devlib\Lang::text('Distance to the work location') }}</small>
                    <div class="dl-geo__msg" data-geo-msg>{{ \Devlib\Lang::text('Waiting for GPS location permission…') }}</div>
                    <div class="dl-geo__dist" data-geo-dist hidden><b data-geo-meters></b> <span>{{ \Devlib\Lang::text('meters') }}</span></div>
                    <div class="dl-geo__bar" data-geo-bar hidden><i data-geo-fill></i></div>
                    <span class="dl-geo__badge" data-geo-badge hidden></span>
                    <button type="button" class="dl-btn dl-btn--link dl-btn--sm" data-geo-recheck>{{ \Devlib\Lang::text('Check location again') }}</button>
                </div>
                <div class="dl-geo__note" data-geo-note hidden></div>
                <div class="dl-geo__actions">
                    <x-dl::button icon="fa-right-to-bracket" label="Clock In" data-geo-clock="IN" disabled />
                    <x-dl::button variant="secondary" icon="fa-right-from-bracket" label="Clock Out" data-geo-clock="OUT" disabled />
                </div>
                <small>{{ \Devlib\Lang::text('Today') }}</small>
                <div class="dl-geo__history" data-geo-history></div>
            </div>
            BLADE;
    }
}
