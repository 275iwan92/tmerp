{{--
 REMARK
 Tujuan       : Dashboard Maintenance (hanya baca): indikator OEE, MTBF, MTTR, kepatuhan preventive, top downtime, kegagalan per komponen dan biaya per rentang hari dan mesin.
 Sumber hulu  : App\Services\Maintenance\KpiService (memakai MachineOeeService milik Production dan data MntOrder); target dari Settings > Constants (MNT_TARGET_*); ProdMachine, MntAsset.
 Input        : Query days (30, 90, 180, 365; default 90) dan machine.
 Output       : Kartu, tabel dan grafik x-dl::*; tidak ada posting.
 Dipakai oleh : Route v2.screen slug maintenance-dashboard (App\Support\V2\Screens, menu sys_app_menu target v2:maintenance-dashboard) di menu Maintenance
 Status       : Aktif (mockup maintenance_v2_dashboard.jpg)
--}}
@php
    use App\Models\Master\ProdMachine;
    use App\Models\Master\ProdMachineDowntime;
    use App\Models\Mnt\MntAsset;
    use App\Services\Maintenance\KpiService;
    use App\Support\V2\Data\Maintenance;
    use App\Support\V2\Src;
    use Carbon\CarbonImmutable;

    $days = in_array((int) request('days'), [30, 90, 180, 365], true) ? (int) request('days') : 90;
    $machineId = request('machine') ? (int) request('machine') : null;
    $to = CarbonImmutable::now()->startOfDay();
    $from = $to->subDays($days);
    $svc = new KpiService;
    $k = $svc->indicators($from, $to, $machineId);
    $t = KpiService::targets();
    $act = $svc->actions();
    $trend = $svc->complianceTrend();
    $top = $svc->topDowntime($from, $to);
    $byKind = $svc->downtimeByKind($from, $to);
    $comps = $svc->failuresByComponent($from, $to);
    $cost = $svc->cost($from, $to, $machineId);
    $machines = ['' => t('All machines')] + ProdMachine::whereIn('machine_id', MntAsset::whereNotNull('machine_id')->where('activate', true)->pluck('machine_id'))->orderBy('machine_code')->get(['machine_id', 'machine_code', 'machine_name'])
        ->mapWithKeys(fn ($m) => [$m->machine_id => $m->machine_code.' · '.$m->machine_name])->all();
    $src = Src::of('tm2mnt.mnt_order', 'tm2mnt.mnt_request', 'tm2mst.prod_machine_downtime');

    // Lampu: hijau = capai target, kuning = dalam 10 % dari target, merah = jauh. $up = makin tinggi makin baik.
    $lamp = fn ($v, $target, bool $up) => $v === null ? 'neutral' : ($up ? ($v >= $target ? 'success' : ($v >= $target * 0.9 ? 'warn' : 'danger')) : ($v <= $target ? 'success' : ($v <= $target * 1.1 ? 'warn' : 'danger')));
    $money = fn ($v) => Maintenance::money((float) $v);
@endphp

<x-dl::card title="Maintenance Dashboard" description="Availability, reliability, preventive discipline, and what needs action today.">
    <x-dl::action-form method="get" action="{{ route('v2.screen', 'maintenance-dashboard') }}">
        <x-dl::form.row :cols="4">
            <x-dl::field.select name="days" label="Period" :options="[30 => t('Last 30 days'), 90 => t('Last 90 days'), 180 => t('Last 180 days'), 365 => t('Last 12 months')]" :value="$days" :source="$src" />
            <x-dl::field.select name="machine" label="Machine" :options="$machines" :value="$machineId ?: ''" :source="Src::of('tm2mst.prod_machine')" />
        </x-dl::form.row>
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="fa-rotate" label="Show" type="submit" />
            <x-dl::button variant="secondary" icon="fa-chart-column" label="Machine Reliability" :href="route('v2.screen', 'maintenance-reliability')" />
            <x-dl::button variant="secondary" icon="fa-percent" label="PM Compliance" :href="route('v2.screen', 'maintenance-pm-compliance')" />
        </x-dl::action-bar>
    </x-dl::action-form>
</x-dl::card>

<x-dl::card title="Indicators" description="The lamp compares each figure with its target (Settings, Constants).">
    <x-dl::kpi-row>
        <x-dl::kpi label="Availability" :value="$k['availability'] ?? '—'" format="percent" :tone="$lamp($k['availability'], $t['availability'], true)" icon="fa-power-off" :target="$t['availability']" :note="raw(tf('Target ≥ :n %', ['n' => $t['availability']]))" />
        <x-dl::kpi label="MTBF" :value="$k['mtbf'] === null ? '—' : $k['mtbf'].' h'" :tone="$lamp($k['mtbf'], $t['mtbf'], true)" icon="fa-heart-pulse" :note="raw(tf('Target ≥ :n h', ['n' => $t['mtbf']]))" />
        <x-dl::kpi label="MTTR" :value="$k['mttr'] === null ? '—' : $k['mttr'].' h'" :tone="$lamp($k['mttr'], $t['mttr'], false)" icon="fa-stopwatch" :note="raw(tf('Target ≤ :n h', ['n' => $t['mttr']]))" />
        <x-dl::kpi label="PM Compliance" :value="$k['pm_compliance'] ?? '—'" format="percent" :tone="$lamp($k['pm_compliance'], $t['pm_compliance'], true)" icon="fa-calendar-check" :target="$t['pm_compliance']" :note="raw(tf('Target ≥ :n %', ['n' => $t['pm_compliance']]))" />
        <x-dl::kpi label="Planned Work" :value="$k['planned_work'] ?? '—'" format="percent" :tone="$lamp($k['planned_work'], $t['planned_work'], true)" icon="fa-list-check" :target="$t['planned_work']" :note="raw(tf('Target ≥ :n %', ['n' => $t['planned_work']]))" />
        <x-dl::kpi label="Backlog" :value="$k['backlog_weeks'] === null ? '—' : $k['backlog_weeks'].' wk'" :tone="$lamp($k['backlog_weeks'], $t['backlog_max'], false)" icon="fa-inbox" :note="raw(tf('Target ≤ :n wk', ['n' => $t['backlog_max']]))" />
    </x-dl::kpi-row>
</x-dl::card>

<x-dl::card title="Needs action today" description="Counts, no target. Open the list to act.">
    <x-dl::kpi-row>
        <x-dl::kpi label="Machines Down Now" :value="$act['down']['n']" :tone="$act['down']['n'] ? 'danger' : 'success'" icon="fa-triangle-exclamation" :note="raw($act['down']['hint'])" />
        <x-dl::kpi label="Open Requests" :value="$act['requests']['n']" :tone="$act['requests']['n'] ? 'warn' : 'success'" icon="fa-inbox" :note="raw($act['requests']['hint'])" />
        <x-dl::kpi label="Overdue PM" :value="$act['overdue_pm']['n']" :tone="$act['overdue_pm']['n'] ? 'danger' : 'success'" icon="fa-calendar-xmark" :note="raw($act['overdue_pm']['hint'])" />
        <x-dl::kpi label="In Progress" :value="$act['in_progress']['n']" tone="accent" icon="fa-screwdriver-wrench" :note="raw($act['in_progress']['hint'])" />
        <x-dl::kpi label="Waiting Part" :value="$act['waiting']['n']" :tone="$act['waiting']['n'] ? 'warn' : 'success'" icon="fa-hourglass-half" :note="raw($act['waiting']['hint'])" />
        <x-dl::kpi label="Critical Parts Below Min" :value="$act['parts_low']['n']" :tone="$act['parts_low']['n'] ? 'danger' : 'success'" icon="fa-boxes-packing" :note="raw($act['parts_low']['hint'])" />
    </x-dl::kpi-row>
    <x-dl::action-bar>
        <x-dl::button variant="secondary" size="sm" icon="fa-inbox" label="Maintenance Request" :href="route('v2.screen', 'maintenance-request')" />
        <x-dl::button variant="secondary" size="sm" icon="fa-screwdriver-wrench" label="Breakdown Maintenance Order" :href="route('v2.screen', 'maintenance-bmo')" />
        <x-dl::button variant="secondary" size="sm" icon="fa-calendar-check" label="Preventive Maintenance Order" :href="route('v2.screen', 'maintenance-pmo')" />
    </x-dl::action-bar>
</x-dl::card>

@if (array_filter($trend['data'], fn ($v) => $v !== null))
    <x-dl::chart type="bar" title="PM Compliance, last 12 months" subtitle="Preventive and inspection orders done on time, per month" format="percent" height="240px" :labels="$trend['labels']"
        :series="[['name' => t('PM Compliance'), 'data' => array_map(fn ($v) => $v ?? 0, $trend['data'])]]" :source="Src::of('tm2mnt.mnt_order')" />
@else
    <x-dl::alert tone="info" message="No preventive order was due in the last 12 months, so there is no trend yet." />
@endif

@if ($top)
    <x-dl::chart type="hbar" title="Top machines by downtime (hours)" subtitle="Unplanned downtime in the period" height="220px" :legend="false"
        :labels="array_column($top, 'machine')" :series="[['name' => t('Hours'), 'data' => array_column($top, 'hours'), 'tone' => 'danger']]" :source="Src::of('tm2mst.prod_machine_downtime')" />
@else
    <x-dl::alert tone="info" message="No unplanned downtime has been recorded in this period." />
@endif

@if ($comps)
    <x-dl::chart type="hbar" title="Failures by component" subtitle="Breakdown and corrective orders, from the component code" height="220px" :legend="false"
        :labels="array_column($comps, 'component')" :series="[['name' => t('Orders'), 'data' => array_column($comps, 'n')]]" :source="Src::of('tm2mnt.mnt_order')" />
@endif

<x-dl::card title="OEE (read only, from Production)" description="Production calculates OEE; Maintenance contributes the downtime. Not calculated when a factor is missing.">
    @if ($k['oee'] !== null)
        <x-dl::kpi label="OEE" :value="$k['oee']" format="percent" tone="accent" icon="fa-gauge-high"
            :note="raw(tf('Availability :a % × Performance :p % × Quality :q %', ['a' => $k['factors']['availability'] ?? '—', 'p' => $k['factors']['performance'] ?? '—', 'q' => $k['factors']['quality'] ?? '—']))" />
    @else
        <x-dl::alert tone="info" message="OEE is not calculated: at least one factor (availability, performance or quality) has no data yet." />
    @endif
</x-dl::card>

<x-dl::card title="Maintenance cost (IDR)" description="Spare parts, technician hours at the standard rate, and vendor service of the orders in the period.">
    <x-dl::table :columns="['part' => 'Spare Parts', 'labor' => 'Labor', 'vendor' => 'Vendor', 'total' => 'Total']"
        :rows="[['part' => $money($cost['part']), 'labor' => $money($cost['labor']), 'vendor' => $money($cost['vendor']), 'total' => $money($cost['total'])]]" />
</x-dl::card>

<x-dl::card title="Downtime by kind (hours)" description="Planned maintenance does not reduce OEE availability; breakdown and waiting for parts do.">
    <x-dl::table :columns="['kind' => 'Kind', 'hours' => 'Hours']" :rows="collect($byKind)->map(fn ($h, $kind) => ['kind' => t(ProdMachineDowntime::KINDS[$kind]['label'] ?? $kind), 'hours' => raw((string) $h)])->values()->all()" />
</x-dl::card>

<x-dl::card title="Formulas" description="The same as the engines in the code.">
    <x-dl::table :columns="['name' => 'Indicator', 'formula' => 'Formula', 'source' => 'Source of data']" :rows="array_map(fn ($r) => array_map('raw', array_map('t', $r)), [
        ['name' => 'OEE', 'formula' => 'Availability × Performance × Quality. If any factor cannot be calculated, OEE shows as not calculated, never 0 %.', 'source' => 'Production engine (read only here)'],
        ['name' => 'Availability', 'formula' => '(Planned minutes − unplanned downtime) ÷ planned minutes.', 'source' => 'Machine calendar and downtime log'],
        ['name' => 'MTTR', 'formula' => 'Total breakdown downtime hours ÷ number of breakdowns.', 'source' => 'Downtime log, kind Breakdown'],
        ['name' => 'MTBF', 'formula' => '(Period hours of all machines − breakdown downtime hours) ÷ number of breakdowns.', 'source' => 'Downtime log, kind Breakdown'],
        ['name' => 'PM Compliance', 'formula' => 'Preventive orders done on time (due date plus tolerance) ÷ preventive orders due in the period.', 'source' => 'Preventive Maintenance Orders'],
        ['name' => 'Planned Work', 'formula' => 'Orders that are not Breakdown ÷ all orders in the period.', 'source' => 'Maintenance Orders'],
        ['name' => 'Backlog (weeks)', 'formula' => 'Estimated hours of open orders ÷ technician hours available per week.', 'source' => 'Open orders and constants'],
        ['name' => 'Maintenance cost', 'formula' => 'Spare parts + technician hours × rate + vendor service.', 'source' => 'Order parts, labor and vendor lines'],
    ])" />
</x-dl::card>
