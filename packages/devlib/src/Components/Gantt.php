<?php

namespace Devlib\Components;

// Gantt resource x waktu (mockup_scheduling_board, ganttboardstandard). Layar cukup deklarasi data.
// rows:  [['key', 'label', 'sub' => crew/keterangan, 'section' => judul pemisah (mis. "MANUAL JOB")]]
// tasks: [['id', 'row', 'label', 'process' => kunci categories, 'start' => 'HH:MM', 'end' => 'HH:MM',
//          'progress' => 0-100, 'status', 'state' => hot|qchold|breakdown, 'risk' => bool]]
// categories: [kunci => ['label', 'tone']]  -> warna blok, legenda, filter proses.
// Drag = geser mulai (snap), tepi kanan = durasi, klik = detail, klik 2x = ubah resource/proses.
// QC Hold terkunci. Blok bertumpuk ditandai bentrok. Perubahan -> event dl:gantt; Submit -> dl:gantt-submit.
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
        public ?string $submitLabel = 'Submit ke Master Schedule',
        public string $resourceLabel = 'Mesin / Crew',
        public ?string $source = null,
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
            'px' => $this->pxPerHour, 'snap' => $this->snap, 'idle' => $this->idleHours, 'unscheduled' => $this->unscheduled, 'resourceLabel' => $this->resourceLabel];
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-gantt') }} {!! $dl() !!} data-dl-gantt data-config="{{ json_encode($config()) }}" data-mode="{{ $simulation ? 'sim' : 'live' }}">
                <div class="dl-gantt__bar">
                    <div class="dl-segment" data-g2-mode><button type="button" class="dl-segment__opt {{ $simulation ? 'is-active' : '' }}" data-value="sim">● Simulasi</button><button type="button" class="dl-segment__opt {{ $simulation ? '' : 'is-active' }}" data-value="live">○ Live</button></div>
                    <span class="dl-gantt__spacer"></span>
                    <x-dl::field.select name="gantt_resource" :options="['' => 'Semua Resource'] + collect($rows)->pluck('label', 'key')->all()" placeholder="Semua Resource" />
                    <x-dl::field.select name="gantt_process" :options="['' => 'Semua Proses'] + collect($categories)->map(fn ($c) => $c['label'])->all()" placeholder="Semua Proses" />
                    {{ $actions ?? '' }}
                    @if ($submitLabel)<x-dl::button preset="submit" :label="$submitLabel" data-g2-submit />@endif
                </div>
                <div class="dl-gantt__stats" data-g2-stats></div>
                <div class="dl-gantt__legend" data-g2-legend></div>
                <div class="dl-grid__chips" data-g2-chips hidden></div>
                <div class="dl-gantt__wrap"><div class="dl-gantt__board" data-g2-board></div></div>
                <p class="dl-gantt__help">Drag blok = ubah waktu mulai · tarik tepi kanan = ubah durasi · klik = detail singkat · klik 2x = ubah resource/proses. <span data-g2-modehelp></span></p>
            </div>
            BLADE;
    }
}
