{{--
 REMARK
 Tujuan       : Live Schedule = pemantauan read-only jadwal yang berlaku: semua operasi CONFIRMED / berjalan, per Process dan Machine, plus tabel rincian per operasi.
 Sumber hulu  : App\Support\V2\Data\Ppic::live() (prd_wo_operation, prd_wo_operation_output, prd_wo_operation_output_defect, prd_operation_time_log).
 Input        : Tidak ada (tanpa tombol ubah); pilihan Process / Machine di Gantt hanya menyaring tampilan.
 Output       : KPI status, Gantt linked (All processes / All machines), tabel: WO, customer, produk, proses, mesin, target, good, bad, progres, rencana vs aktual, status, operator, gate.
 Dipakai oleh : Menu Production > Planning > Live Schedule (v2:production-live).
 Status       : Read-only; muat ulang halaman untuk data terbaru (belum ada auto-refresh).
--}}
@php
    use App\Support\V2\Data\Ppic;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $live = Ppic::live();
    $g = $live['gantt'];
    $k = $live['kpi'];
    $src = Src::of('tm2prd.prd_wo_operation', 'tm2prd.prd_wo_operation_output', 'tm2prd.prd_operation_time_log');
    $gateText = ['READY' => 'Ready', 'WAIT' => 'Waiting', 'BLOCK' => 'Blocked'];
    $tone = ['Running' => 'success', 'Paused' => 'warn', 'Late' => 'danger', 'Done' => 'neutral', 'Locked' => 'info', 'Scheduled' => 'info'];
@endphp

<x-dl::card title="Live Schedule" description="The schedule in force: confirmed and running operations only. Read only; changes are made on the Work Order form.">
    <x-dl::action-bar>
        <x-dl::button variant="secondary" icon="back" label="Back" :href="route('v2.screen', 'production-wo')" />
    </x-dl::action-bar>
    <x-dl::kpi-row>
        <x-dl::stat-box label="Operations" :value="(string) $k['total']" tone="accent" />
        <x-dl::stat-box label="Running" :value="(string) $k['running']" tone="success" />
        <x-dl::stat-box label="Paused" :value="(string) $k['paused']" tone="warn" />
        <x-dl::stat-box label="Late" :value="(string) $k['late']" tone="danger" />
        <x-dl::stat-box label="Scheduled" :value="(string) $k['scheduled']" tone="info" />
    </x-dl::kpi-row>
    <x-dl::gantt :mode-switch="false" :extras="false" :simulation="false" :linked="$g['linked']" empty-text="No confirmed operations yet. Apply a Work Order to the live schedule first."
        :rows="$g['rows']" :tasks="$g['tasks']" :categories="$g['categories']" :origin-date="$g['originDate']" :start="$g['start']" :end="$g['end']" :px-per-hour="$g['pxPerHour']"
        :snap="$g['snap']" :now-at="$g['nowAt']" :lock-until="$g['lockUntil']" :submit-label="null" resource-label="Machine" :source="$src" />
</x-dl::card>

<x-dl::card title="Operations" description="Good and bad come from Production Output; planned and actual times from the schedule and the operator timer.">
    <x-dl::table :source="$src" empty="No confirmed operations yet."
        :columns="['wo' => 'Work Order', 'customer' => 'Customer', 'product' => 'Product', 'process' => 'Process', 'machine' => 'Machine', 'target' => 'Target qty', 'good' => 'Good', 'bad' => 'Bad',
            'progress' => 'Progress', 'plan' => 'Planned start → finish', 'actual' => 'Actual start → finish', 'status' => 'Status', 'operator' => 'Operator', 'gates' => 'Gates', 'due' => 'Due']"
        :rows="array_map(fn ($r) => ['wo' => raw($r['wo']), 'customer' => raw($r['customer'] ?: '—'), 'product' => raw($r['product'] ?: '—'), 'process' => raw($r['process']), 'machine' => raw($r['machine']),
            'target' => raw(Locale::number($r['target'], 0).' '.$r['uom']), 'good' => Locale::number($r['good'], 0), 'bad' => Locale::number($r['bad'], 0), 'progress' => $r['progress'].'%',
            'plan' => raw(Locale::dt($r['plan_start']).' → '.Locale::dt($r['plan_finish'])),
            'actual' => raw($r['actual_start'] ? Locale::dt($r['actual_start']).' → '.($r['actual_finish'] ? Locale::dt($r['actual_finish']) : '…') : '—'),
            'status' => raw($r['status']), 'operator' => raw($r['operator'] ?: '—'),
            'gates' => raw(implode(' / ', array_map(fn ($x) => $gateText[$x] ?? $x, $r['gates']))), 'due' => raw($r['due'] ? Locale::d($r['due']) : '—')], $live['rows'])" />
</x-dl::card>
