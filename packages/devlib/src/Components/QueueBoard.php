<?php

/*
 * REMARK
 * Tujuan       : Antrean per proses per mesin: dropdown Process bertaut dropdown Machine, daftar item hanya-baca dengan status, progres persen, start, end, dan jeda (idle) sebelum job beserta alasannya.
 * Sumber hulu  : Data dari layar (mis. Ppic::queue: operasi semua WO aktif, prod_process_machine).
 * Input        : processes [key => label], machines [processKey => [machineKey => label]], rows [process, machine, status, cells...], process, machine, mine.
 * Output       : Tabel yang disaring di browser (queue-board.js); tanpa panggilan server. Progres null tampil sebagai garis (mis. langkah SUBCON tanpa data hasil).
 * Dipakai oleh : Form WO (bagian "QUE every process every machine").
 * Status       : Aktif (DevLib).
 */

namespace Devlib\Components;

use Devlib\Lang;

// rows: [['process' => key, 'machine' => key, 'status' => 'Running|Confirmed|Draft', 'wo' => ..., 'item' => ..., 'qty' => ..., 'progress' => 0-100 or null (shown as a dash), 'start' => ..., 'end' => ..., 'idle' => 'idle time before this job and why' (optional), 'mine' => bool]]
class QueueBoard extends DevlibComponent
{
    public function __construct(
        public array $processes = [],
        public array $machines = [],
        public array $rows = [],
        public ?string $process = null,
        public string $machine = '',
        public string $empty = 'Nothing is queued on this machine.',
        public bool $suggested = false,   // adds the Suggested column (rows carry 'suggested' text); only while a sequence model is active
    ) {
        // "All processes" (key '') lists every machine; "All machines" is the empty choice of each Machine dropdown
        if (! array_key_exists('', $this->processes)) {
            $this->processes = ['' => Lang::text('All processes')] + $this->processes;
            $all = [];
            foreach ($this->machines as $list) {
                $all += $list;
            }
            $this->machines = ['' => $all] + $this->machines;
        }
    }

    public function tag(): string
    {
        return 'x-dl::queue-board';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-queue') }} {!! $dl() !!} data-dl-queue>
                <div class="dl-queue__bar">
                    <x-dl::field.select name="queue_process" :value="$process" :options="$processes" />
                    @foreach ($machines as $pk => $list)
                        <span data-q-mach="{{ $pk }}" @if ($pk !== $process) hidden @endif>
                            <x-dl::field.select name="queue_machine_{{ $loop->index }}" :value="$pk === $process ? $machine : ''" :options="['' => \Devlib\Lang::text('All machines')] + $list" />
                        </span>
                    @endforeach
                    <span class="dl-queue__count" data-q-count></span>
                </div>
                <div class="dl-table-wrap">
                    <table class="dl-table">
                        <thead><tr>
                            <th>{{ \Devlib\Lang::text('Status') }}</th><th>{{ \Devlib\Lang::text('Work Order') }}</th><th>{{ \Devlib\Lang::text('Item') }}</th><th class="num">{{ \Devlib\Lang::text('Quantity') }}</th>
                            <th>{{ \Devlib\Lang::text('Progress') }}</th><th>{{ \Devlib\Lang::text('Start') }}</th><th>{{ \Devlib\Lang::text('End') }}</th>@if ($suggested)<th>{{ \Devlib\Lang::text('Suggested') }}</th>@endif<th>{{ \Devlib\Lang::text('Idle before') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($rows as $r)
                                <tr data-q-process="{{ $r['process'] }}" data-q-machine="{{ $r['machine'] }}" hidden class="{{ ! empty($r['mine']) ? 'is-mine' : '' }}">
                                    <td><span class="dl-queue__st dl-queue__st--{{ strtolower($r['status']) }}">{{ \Devlib\Lang::text($r['status']) }}</span></td>
                                    <td>{{ $r['wo'] }}</td><td>{{ $r['item'] }}</td><td class="num">{{ $r['qty'] }}</td>
                                    <td>@if ($r['progress'] === null)—@else<span class="dl-queue__pct"><i style="width:{{ (int) $r['progress'] }}%"></i></span> {{ (int) $r['progress'] }}%@endif</td>
                                    <td>{{ $r['start'] }}</td><td>{{ $r['end'] }}</td>@if ($suggested)<td>{{ $r['suggested'] ?? '' }}</td>@endif<td>{{ $r['idle'] ?? '' }}</td>
                                </tr>
                            @endforeach
                            <tr data-q-empty hidden><td colspan="{{ $suggested ? 9 : 8 }}" class="dl-table__empty">{{ \Devlib\Lang::text($empty) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            BLADE;
    }
}
