{{-- Production Scheduling > Monitoring: planned against actual for operations that started in the last 14 days. --}}
@php
    $m = \App\Support\V2\Data\Ppic::monitoring();
    $rows = array_map(fn ($r) => ['wo' => raw($r['wo']), 'process' => raw($r['process']), 'machine' => raw($r['machine'] ?? '—'), 'planned' => \Devlib\Locale::number($r['planned_qty'], 0), 'good' => \Devlib\Locale::number($r['good'], 0),
        'allow' => \Devlib\Locale::number($r['allowance'], 0), 'setup' => raw($r['setup']), 'pf' => raw($r['planned_finish']), 'af' => raw($r['actual_finish']), 'result' => t($r['result'])], $m['rows']);
@endphp
<x-dl::card title="Monitoring" description="Plan against actual. Output and times come from Production Output and the operator timers.">
    <div class="dl-grid__stats">
        <x-dl::stat-box label="On-time finish" :value="$m['kpi']['on_time'] === null ? '—' : $m['kpi']['on_time'].'%'" tone="success" :sub="$m['kpi']['finished'].' finished'" />
        <x-dl::stat-box label="Setup plan / actual" :value="$m['kpi']['setup_plan'].' / '.$m['kpi']['setup_actual']" tone="info" sub="minutes" />
        <x-dl::stat-box label="Operations tracked" :value="(string) $m['kpi']['rows']" tone="accent" sub="last 14 days" />
    </div>
    <x-dl::table :columns="['wo' => 'Work Order', 'process' => 'Process', 'machine' => 'Machine', 'planned' => 'Planned qty', 'good' => 'Good output', 'allow' => 'Waste + spoilage allowance', 'setup' => 'Setup plan / actual (min)', 'pf' => 'Planned finish', 'af' => 'Actual finish', 'result' => 'Result']" :rows="$rows" empty="No operation has started in the last 14 days." :source="$src" />
</x-dl::card>
