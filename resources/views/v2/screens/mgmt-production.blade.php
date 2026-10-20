{{--
 REMARK
 Tujuan       : Production & Manufacturing: status Work Order, WO selesai per bulan, reject rate output dan WO aktif dengan jatuh tempo terdekat.
 Sumber hulu  : App\Support\V2\Data\Mgmt\ProductionReport (prd_wo, prd_wo_operation_output, prd_wo_operation_output_defect); pindahan layar v1 pages::report.production.
 Input        : Query th (tahun, bawaan tahun berjalan).
 Output       : Kartu filter x-dl::action-form (GET), x-dl::kpi, x-dl::chart, x-dl::alert, x-dl::table.
 Dipakai oleh : Layar v2 slug mgmt-production lewat rute v2.screen (menu Management Reports > Production & Manufacturing, sys_app_menu MGMT_PRODUCTION)
 Status       : Aktif
--}}
@php
    use App\Support\V2\Data\Mgmt\AccountingReport;
    use App\Support\V2\Data\Mgmt\MgmtReport;
    use App\Support\V2\Data\Mgmt\ProductionReport;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $r = new ProductionReport(request()->query());
    $src = Src::of('tm2prd.prd_wo', 'tm2prd.prd_wo_operation_output', 'tm2prd.prd_wo_operation_output_defect');
    $st = $r->woByStatus();
    $defect = $r->outputDefectRate();
@endphp

<x-dl::card title="Production & Manufacturing Report" description="Work Order status and production output.">
    <x-dl::action-form method="get" action="{{ route('v2.screen', 'mgmt-production') }}">
        <x-dl::form.row :cols="1">
            <x-dl::field.select name="th" label="Year" :options="AccountingReport::recentYears()" :value="$r->year" :placeholder="null" :source="$src" />
        </x-dl::form.row>
        <x-dl::action-bar><x-dl::button variant="secondary" icon="fa-filter" label="Filter" /></x-dl::action-bar>
    </x-dl::action-form>
</x-dl::card>

<x-dl::kpi-row>
    <x-dl::kpi label="Total Work Orders" :value="$r->totalWo()" icon="fa-industry" tone="accent" :note="tf(':n completed this year', ['n' => $st['COMPLETED']])" />
    <x-dl::kpi label="In Progress" :value="$st['IN_PROGRESS']" icon="fa-gears" tone="violet" note="Status IN_PROGRESS" />
    <x-dl::kpi label="On Hold" :value="$st['ON_HOLD']" icon="fa-triangle-exclamation" tone="warn" note="Needs PPIC follow-up" />
    <x-dl::kpi label="Past Due" :value="$r->overdueOpen()" icon="fa-clock" tone="danger" note="Active WO, due date passed" />
</x-dl::kpi-row>

<x-dl::chart type="bar" :title="raw(tf('Completed WO per Month :year', ['year' => $r->year]))" :labels="MgmtReport::months()" :series="[['name' => t('Completed'), 'data' => $r->monthlyCompleted()]]" :source="$src" />

<x-dl::card :title="raw(tf('Output Reject Rate :year', ['year' => $r->year]))">
    @if ($defect === null)
        <x-dl::hint>@t('No production output data this year.')</x-dl::hint>
    @else
        <x-dl::kpi label="Reject Rate" :value="$defect" format="percent" icon="fa-ban" :tone="$defect > 5 ? 'danger' : 'success'" note="Reject qty ÷ (Good + Reject qty), all WO Operation Outputs this year." />
    @endif
</x-dl::card>

<x-dl::card title="Active WO — Nearest Due Date">
    <x-dl::table :columns="['no' => 'WO No.', 'status' => 'Status', 'due' => 'Due Date', 'late' => ['Overdue', 'num' => true]]" :rows="$r->longestOpenWoRows()" empty="No active WO." :source="$src" />
</x-dl::card>
