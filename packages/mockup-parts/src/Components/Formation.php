<?php

namespace MockupParts\Components;

use Devlib\Components\DevlibComponent;

// Formasi Harian (mockup_roster_formasi_harian.html): baris = posisi/unit x shift, isi = orang yang bertugas.
// Ringkas seminggu (orang yang masuk semua hari cukup 1 chip; pergantian tengah minggu tampil "Sen–Rab A -> Kam–Sab B"
// + BERGANTI; tambahan sebagian hari "+ Sen saja") atau Per hari (fokus 1 hari). Kotak monitoring di atas = filter.
// Chip: borongan (ungu), di 2 titik hari itu (⇄ oranye), belum bersertifikat unit itu (⚠ merah).
// Editor kanan: klik ✎ -> centang orang x hari, tambah orang / ambil dari yang belum terjadwal.
// positions: [['code','name','shifts' => [shiftKey..],'group']] ; shifts: [key => ['label','time','tone' => blue|amber|slate]]
// days: [['key' => 'Sen','date' => '14 Sep']] ; people: [['id','name','group','borongan' => bool,'certs' => [posCode..]]]
// assign: [['pos','shift','id','days' => [indeks hari..]]]. Event: dl:formation-change / -save / -publish / -week / -copy.
class Formation extends DevlibComponent
{
    public function __construct(
        public array $positions = [],
        public array $shifts = [],
        public array $days = [],
        public array $people = [],
        public array $assign = [],
        public int $today = 0,
        public string $week = '',
        public string $range = '',
        public string $status = 'DRAFT',
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-mp::formation';
    }

    public function source(): string
    {
        return $this->source ?? 'tm2hrm formasi harian (emp_schedule per posisi)';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-fm') }} {!! $dl() !!} data-dl-formation data-status="{{ $status }}" data-today="{{ $today }}"
                data-positions="{{ json_encode($positions) }}" data-shifts="{{ json_encode((object) $shifts) }}" data-days="{{ json_encode($days) }}"
                data-people="{{ json_encode($people) }}" data-assign="{{ json_encode($assign) }}">
                <div class="dl-fm__top">
                    <div class="dl-roster__week"><x-dl::icon-button icon="prev" title="Previous week" data-fm-week="-1" /><div class="dl-fm__wk"><b>{{ $week }}</b><small>{{ $range }}</small></div><x-dl::icon-button icon="next" title="Next week" data-fm-week="1" /></div>
                    <span class="dl-print__spacer"></span>
                    <span class="dl-fm__status" data-fm-status></span>
                    <x-dl::button preset="save" size="sm" variant="secondary" data-fm-save />
                    <x-dl::button size="sm" icon="approve" label="Publish" data-fm-publish />
                </div>
                <div class="dl-grid__stats dl-fm__stats" data-fm-stats></div>
                <div class="dl-fm__main">
                    <section class="dl-fm__sheet">
                        <div class="dl-fm__tools">
                            <div class="dl-segment" data-fm-mode><button type="button" class="dl-segment__opt is-active" data-m="week">{{ \Devlib\Lang::text('Week summary') }}</button><button type="button" class="dl-segment__opt" data-m="day">{{ \Devlib\Lang::text('Per day') }}</button></div>
                            <div class="dl-fm__days" data-fm-days></div>
                            <span class="dl-print__spacer"></span>
                            <div class="dl-grid__search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="{{ \Devlib\Lang::text('Search a name in the formation…') }}" data-fm-search aria-label="{{ \Devlib\Lang::text('Search name') }}"></div>
                            <x-dl::button variant="secondary" size="sm" icon="fa-clone" label="Copy last week" data-fm-copy />
                            <x-dl::button preset="print" size="sm" data-fm-print />
                        </div>
                        <div class="dl-fm__scroll"><table class="dl-fm__table"><thead><tr><th class="dl-fm__no">No</th><th>{{ \Devlib\Lang::text('Station') }}</th><th>{{ \Devlib\Lang::text('Working hours') }}</th><th data-fm-colhead>{{ \Devlib\Lang::text('On duty') }}</th><th></th></tr></thead><tbody data-fm-body></tbody></table></div>
                        <div class="dl-fm__legend" data-fm-legend></div>
                    </section>
                    <aside class="dl-fm__side" data-fm-side></aside>
                </div>
            </div>
            BLADE;
    }
}
