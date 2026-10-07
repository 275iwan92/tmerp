<?php

namespace MockupParts\Components;

use Devlib\Components\DevlibComponent;

// Roster mingguan (mockup_roster_shift): baris = shift (pola jam per hari), kolom = hari; 1 penempatan =
// 1 minggu penuh mengikuti pola shift. Pool pegawai di kanan (cari, grup per jabatan, centang banyak).
// Tempatkan: drag chip ke baris shift ATAU centang di pool lalu klik baris ("+klik"). x = lepas.
// shifts: [['key','label','tone','capacity','pattern' => [hari => ['07:30','16:30'] | null (libur)]]]
// days:   [['key' => 'Sen', 'date' => '07/09']] ; people: [['id','name','code','role','skills']]
// assign: [id => shiftKey]. Perubahan -> event dl:roster {assign}; Simpan -> dl:roster-save.
// :save-url = alamat POST JSON {assign} (Simpan tersimpan di server dulu; gagal = pesan galat, papan tetap). :week-url = alamat dengan {step}
// (-1 / 1) untuk pindah minggu (layar dimuat ulang). :locked = papan hanya-baca (roster sudah dikunci).
class RosterBoard extends DevlibComponent
{
    public function __construct(
        public array $shifts = [],
        public array $days = [],
        public array $people = [],
        public array $assign = [],
        public string $title = '',
        public string $week = '',
        public ?string $source = null,
        public ?string $saveUrl = null,
        public ?string $weekUrl = null,
        public bool $locked = false,
    ) {}

    public function tag(): string
    {
        return 'x-mp::roster-board';
    }

    public function source(): string
    {
        return $this->source ?? 'tm2hrm roster (emp_schedule)';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-roster') }} {!! $dl() !!} data-dl-roster data-shifts="{{ json_encode($shifts) }}" data-days="{{ json_encode($days) }}" data-people="{{ json_encode($people) }}" data-assign="{{ json_encode((object) $assign) }}" @if ($saveUrl) data-save-url="{{ $saveUrl }}" @endif @if ($weekUrl) data-week-url="{{ $weekUrl }}" @endif @if ($locked) data-locked="1" @endif>
                <div class="dl-roster__bar">
                    <div class="dl-roster__week"><x-dl::icon-button icon="prev" title="Previous week" data-r-week="-1" /><b>{{ $week }}</b><x-dl::icon-button icon="next" title="Next week" data-r-week="1" /></div>
                    <span class="dl-print__spacer"></span>
                    <div class="dl-roster__stats" data-r-stats></div>
                    <x-dl::button preset="save" size="sm" data-r-save />
                </div>
                <div class="dl-roster__main">
                    <div class="dl-roster__board">
                        <div class="dl-roster__head"><b>{{ $title }}</b><small>{{ \Devlib\Lang::text('1 placement = 1 full week, hours follow the shift pattern · tick in the pool then click a row = place them all at once') }}</small></div>
                        <div class="dl-roster__grid" data-r-grid></div>
                    </div>
                    <aside class="dl-roster__pool">
                        <div class="dl-roster__poolhead"><b>{{ \Devlib\Lang::text('Employee Pool') }}</b><small data-r-poolcount></small></div>
                        <div class="dl-grid__search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="{{ \Devlib\Lang::text('Search name or NIK...') }}" data-r-search aria-label="{{ \Devlib\Lang::text('Search employee') }}"></div>
                        <div class="dl-roster__sel" data-r-selbar hidden><b data-r-selcount></b> {{ \Devlib\Lang::text('selected — click a shift row to place them') }} <button type="button" class="dl-btn dl-btn--link dl-btn--sm" data-r-selnone>Cancel</button></div>
                        <div class="dl-roster__people" data-r-pool></div>
                    </aside>
                </div>
            </div>
            BLADE;
    }
}
