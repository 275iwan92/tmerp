<?php

namespace Devlib\Components;

// Kalender acara (mockup_UIUX_calendar): kiri = Create Event + kalender mini + cari; tengah = Day/Week/Month;
// kanan = acara hari ini + detail hari terpilih. Waktu = WIB (Devlib\Locale).
// events: [['id', 'title', 'start' => 'YYYY-MM-DD HH:MM' | 'YYYY-MM-DD', 'end', 'allDay' => bool, 'tone', 'desc']]
// display: day | week | month (bukan $view: nama itu dipakai Blade Component).
// Klik 2x acara = edit; klik 2x slot kosong = acara baru; drag = pindah hari/jam (snap 30 menit).
// Perubahan -> event dl:calendar (detail: {type: create|update|delete|move, event}).
class Calendar extends DevlibComponent
{
    public function __construct(
        public array $events = [],
        public string $display = 'week',
        public ?string $date = null,
        public int $weekStart = 0,
        public int $scrollHour = 7,
        public bool $sidebar = true,
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::calendar';
    }

    public function source(): string
    {
        return $this->source ?? 'acara kalender';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-cal2', 'no-side' => ! $sidebar]) }} {!! $dl() !!} data-dl-calendar data-events="{{ json_encode($events) }}" data-view="{{ $display }}" data-date="{{ $date }}" data-week-start="{{ $weekStart }}" data-scroll="{{ $scrollHour }}">
                <div class="dl-cal2__top">
                    <x-dl::button variant="secondary" size="sm" :label="\Devlib\Lang::text('Today')" data-c-today />
                    <x-dl::icon-button icon="prev" title="Sebelumnya" data-c-prev />
                    <x-dl::icon-button icon="next" title="Berikutnya" data-c-next />
                    <b class="dl-cal2__title" data-c-title></b>
                    <span class="dl-cal2__spacer"></span>
                    <div class="dl-segment" data-c-views><button type="button" class="dl-segment__opt" data-value="day">Hari</button><button type="button" class="dl-segment__opt" data-value="week">Minggu</button><button type="button" class="dl-segment__opt" data-value="month">Bulan</button></div>
                </div>
                <div class="dl-cal2__body">
                    @if ($sidebar)
                        <aside class="dl-cal2__left">
                            <x-dl::button preset="new" label="Buat Acara" block data-c-create />
                            <div class="dl-cal2__mini" data-c-mini></div>
                            <div class="dl-grid__search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="{{ \Devlib\Lang::text('Search events...') }}" data-c-search aria-label="{{ \Devlib\Lang::text('Search events') }}"></div>
                            <div class="dl-cal2__found" data-c-found></div>
                        </aside>
                    @endif
                    <div class="dl-cal2__main" data-c-main></div>
                    @if ($sidebar)
                        <aside class="dl-cal2__right"><div data-c-today-list></div><div data-c-day-list></div></aside>
                    @endif
                </div>
            </div>
            BLADE;
    }
}
