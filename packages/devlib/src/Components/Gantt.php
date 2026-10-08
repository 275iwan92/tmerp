<?php

namespace Devlib\Components;

// Gantt resource x waktu (mockup_scheduling_board, ganttboardstandard). Layar cukup deklarasi data.
// rows:  [['key', 'label', 'sub' => crew/keterangan, 'section' => judul pemisah (mis. "MANUAL JOB")]]
// tasks: [['id', 'row', 'label', 'process' => kunci categories, 'start' => 'HH:MM', 'end' => 'HH:MM',
//          'progress' => 0-100, 'status', 'state' => hot|qchold|breakdown, 'risk' => bool]]
// categories: [kunci => ['label', 'tone']]  -> warna blok, legenda, filter proses.
// Drag = geser mulai (snap), tepi kanan = durasi, klik = detail, klik 2x = ubah resource/proses.
// QC Hold terkunci. Blok bertumpuk ditandai bentrok. Perubahan -> event dl:gantt; Submit -> dl:gantt-submit.
// Production scheduling board options (all optional, backward compatible):
//   originDate 'Y-m-d' = the date hour 0 means (multi-day axis; start/end are hours from origin, e.g. 6..54).
//   lockUntil (hour offset or 'HH:MM'), nowAt (hour offset from the server).
//   task: locked, movable, setup (minutes), gates [ok|wait|block x3: material, tooling, artwork], forecastEnd (hour), eligible [row keys].
//   row: down [{from,to,label}], load 0-100, bottleneck.
//   impactUrl / moveUrl: after a drag, JSON {task,row,start,end} is checked by the server first:
//     {ok, reason?, lines?: [{label,value,tone?}], row?, start?, end?}; moveUrl receives the same JSON when the user accepts.
//   draftLabel: amber badge in the toolbar (JS: el.dlGantt.setDraft(text)).
class Gantt extends DevlibComponent
{
    public function __construct(
        public array $rows = [],
        public array $tasks = [],
        public array $categories = [],
        public int $start = 8,
        public int $end = 20,
        public int $pxPerHour = 70,
        public int $snap = 15,
        public float $idleHours = 2,
        public int $unscheduled = 0,
        public bool $simulation = true,
        public ?string $submitLabel = 'Submit to Master Schedule',
        public string $resourceLabel = 'Machine / Crew',
        public ?string $source = null,
        public ?string $originDate = null,
        public int|float|string|null $lockUntil = null,
        public ?float $nowAt = null,
        public ?string $impactUrl = null,
        public ?string $moveUrl = null,
        public ?string $draftLabel = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::gantt';
    }

    public function source(): string
    {
        return $this->source ?? 'jadwal resource';
    }

    public function config(): array
    {
        return ['rows' => $this->rows, 'tasks' => $this->tasks, 'categories' => (object) $this->categories, 'start' => $this->start, 'end' => $this->end,
            'px' => $this->pxPerHour, 'snap' => $this->snap, 'idle' => $this->idleHours, 'unscheduled' => $this->unscheduled, 'resourceLabel' => \Devlib\Lang::text($this->resourceLabel),
            'originDate' => $this->originDate, 'lockUntil' => $this->lockUntil, 'nowAt' => $this->nowAt, 'impactUrl' => $this->impactUrl, 'moveUrl' => $this->moveUrl];
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-gantt') }} {!! $dl() !!} data-dl-gantt data-config="{{ json_encode($config()) }}" data-mode="{{ $simulation ? 'sim' : 'live' }}">
                <div class="dl-gantt__bar">
                    <div class="dl-segment" data-g2-mode><button type="button" class="dl-segment__opt {{ $simulation ? 'is-active' : '' }}" data-value="sim">● {{ \Devlib\Lang::text('Simulation') }}</button><button type="button" class="dl-segment__opt {{ $simulation ? '' : 'is-active' }}" data-value="live">○ Live</button></div>
                    <span class="dl-gantt__spacer"></span>
                    <x-dl::field.select name="gantt_resource" :options="['' => \Devlib\Lang::text('All Resources')] + collect($rows)->pluck('label', 'key')->all()" placeholder="All Resources" />
                    <x-dl::field.select name="gantt_process" :options="['' => \Devlib\Lang::text('All Processes')] + collect($categories)->map(fn ($c) => $c['label'])->all()" placeholder="All Processes" />
                    @if ($draftLabel !== null)<span class="dl-gantt__draft" data-g2-draft>{{ $draftLabel }}</span>@endif
                    {{ $actions ?? '' }}
                    @if ($submitLabel)<x-dl::button preset="submit" :label="$submitLabel" data-g2-submit />@endif
                </div>
                <div class="dl-gantt__stats" data-g2-stats></div>
                <div class="dl-gantt__legend" data-g2-legend></div>
                <div class="dl-grid__chips" data-g2-chips hidden></div>
                <div class="dl-gantt__wrap"><div class="dl-gantt__board" data-g2-board></div></div>
                <p class="dl-gantt__help">{{ \Devlib\Lang::text('Drag a block = change the start time · drag the right edge = change the duration · click = short details · double-click = change the resource/process.') }} <span data-g2-modehelp></span></p>
            </div>
            BLADE;
    }
}
