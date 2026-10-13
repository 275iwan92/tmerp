<?php

/*
 * REMARK
 * Tujuan       : Papan Gantt resource x waktu; mode linked = dropdown Process lalu Machine (hanya mesin mampu), sumbu dipas ke bar.
 * Sumber hulu  : Data dari layar: rows, tasks, categories (Ppic::gantt dan linkedBoard).
 * Input        : rows, tasks, categories, linked, extras, modeSwitch, emptyText, impactUrl/moveUrl, reasonRequired.
 * Output       : Papan interaktif; seret memanggil impact lalu move (Reason wajib); event dl:gantt.
 * Dipakai oleh : production-wo (Simulation dan Live Schedule), production-scheduling.
 * Status       : Aktif (DevLib).
 */

namespace Devlib\Components;

use Devlib\Lang;

// Gantt resource x waktu (mockup_scheduling_board, ganttboardstandard). Layar cukup deklarasi data.
// rows:  [['key', 'label', 'section' => judul pemisah (mis. "SUBCON")]]
// tasks: [['id', 'row', 'label', 'process' => kunci categories, 'start' => 'HH:MM', 'end' => 'HH:MM',
//          'progress' => 0-100, 'status', 'state' => hot|qchold|breakdown, 'risk' => bool, 'muted' => bool (greyed, context only)]]
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
        public bool $reasonRequired = false,
        public ?string $moveLabel = null,   // text of the accept button in the impact panel (default: Move into Draft)
        public ?string $bulkLabel = null,   // set = a bulk button in the toolbar acting on the Draft bars shown; shown disabled while bulkUrl is empty (PROCESS first)
        public ?string $bulkUrl = null,
        public ?string $emptyText = null, // shown instead of "No resource matches the filter" when the board has no task at all
        public bool $modeSwitch = true, // false = no Simulation | Live switch (the board stays in the mode given by `simulation`)
        public bool $extras = true,     // false = the number cards, colour legend and filter chips are hidden (the dropdowns stay the only filter)
        public ?array $linked = null,   // ['processes' => [key => label], 'machines' => [processKey => [rowKey]], 'defaults' => [processKey => rowKey], 'process' => key, 'machine' => rowKey]
    ) {
        // "All processes" (key '') shows every machine row; "All machines" is the empty choice of each Machine dropdown
        if ($this->linked !== null && ! array_key_exists('', $this->linked['processes'] ?? [])) {
            $this->linked['processes'] = ['' => Lang::text('All processes')] + $this->linked['processes'];
            $this->linked['machines'] = ['' => array_values(array_unique(array_merge(array_column($this->rows, 'key'), ...array_values($this->linked['machines']))))] + $this->linked['machines'];
        }
    }

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
            'px' => $this->pxPerHour, 'snap' => $this->snap, 'idle' => $this->idleHours, 'unscheduled' => $this->unscheduled, 'resourceLabel' => Lang::text($this->resourceLabel),
            'originDate' => $this->originDate, 'lockUntil' => $this->lockUntil, 'nowAt' => $this->nowAt, 'impactUrl' => $this->impactUrl, 'moveUrl' => $this->moveUrl, 'reasonRequired' => $this->reasonRequired, 'linked' => $this->linked, 'emptyText' => $this->emptyText ? Lang::text($this->emptyText) : null,
            'moveLabel' => $this->moveLabel ? Lang::text($this->moveLabel) : null, 'bulkUrl' => $this->bulkUrl];
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-gantt') }} {!! $dl() !!} data-dl-gantt data-config="{{ json_encode($config()) }}" data-mode="{{ $simulation ? 'sim' : 'live' }}" data-extras="{{ $extras ? 1 : 0 }}">
                <div class="dl-gantt__bar">
                    <div class="dl-segment" data-g2-mode @if (! $modeSwitch) hidden @endif><button type="button" class="dl-segment__opt {{ $simulation ? 'is-active' : '' }}" data-value="sim">● {{ \Devlib\Lang::text('Simulation') }}</button><button type="button" class="dl-segment__opt {{ $simulation ? '' : 'is-active' }}" data-value="live">○ Live</button></div>
                    <span class="dl-gantt__spacer"></span>
                    @if ($linked)
                        {{-- Process first, then only the machines able to run that process (one Machine select per process, the one of the chosen process is shown) --}}
                        <x-dl::field.select name="gantt_process" :value="$linked['process']" :options="$linked['processes']" />
                        @foreach ($linked['machines'] as $pk => $keys)
                            <span data-g2-mach="{{ $pk }}" @if ($pk !== $linked['process']) hidden @endif>
                                <x-dl::field.select name="gantt_machine_{{ $loop->index }}" :value="$linked['defaults'][$pk] ?? ''" :options="['' => \Devlib\Lang::text('All machines')] + collect($rows)->whereIn('key', $keys)->pluck('label', 'key')->all()" />
                            </span>
                        @endforeach
                    @else
                    <x-dl::field.select name="gantt_resource" :options="['' => \Devlib\Lang::text('All Resources')] + collect($rows)->pluck('label', 'key')->all()" placeholder="All Resources" />
                    <x-dl::field.select name="gantt_process" :options="['' => \Devlib\Lang::text('All Processes')] + collect($categories)->map(fn ($c) => $c['label'])->all()" placeholder="All Processes" />
                    @endif
                    @if ($bulkLabel)<x-dl::button variant="secondary" size="sm" icon="fa-check-double" :label="$bulkLabel" :disabled="! $bulkUrl" data-g2-bulk />@endif
                    @if ($draftLabel !== null)<span class="dl-gantt__draft" data-g2-draft>{{ $draftLabel }}</span>@endif
                    {{ $actions ?? '' }}
                    @if ($submitLabel)<x-dl::button preset="submit" :label="$submitLabel" data-g2-submit />@endif
                </div>
                <div class="dl-gantt__stats" data-g2-stats @if (! $extras) hidden @endif></div>
                <div class="dl-gantt__legend" data-g2-legend @if (! $extras) hidden @endif></div>
                <div class="dl-grid__chips" data-g2-chips hidden></div>
                <div class="dl-gantt__wrap"><div class="dl-gantt__board" data-g2-board></div></div>
                <p class="dl-gantt__help">@if ($linked){{ \Devlib\Lang::text('Drag a block = change the start time, or drop it on another machine row · drag the right edge = change the duration · click = short details · double-click = choose another machine.') }}@else{{ \Devlib\Lang::text('Drag a block = change the start time · drag the right edge = change the duration · click = short details · double-click = change the resource/process.') }}@endif <span data-g2-modehelp></span></p>
            </div>
            BLADE;
    }
}
