{{--
 REMARK
 Tujuan       : Dashboard akuntansi: likuiditas, piutang / hutang, profitabilitas, tren arus kas 12 bulan, Compliance dan Action Center (hanya baca).
 Sumber hulu  : App\Support\V2\Data\FinanceDesk (App\Services\Acc\FinanceMetrics); tabel tm2acc.fin_voucher, fin_invoice, fin_tax_register, fin_period.
 Input        : Query string as_of (default hari ini).
 Output       : x-dl::card dengan filter tanggal, x-dl::kpi-row / x-dl::kpi, x-dl::chart area dan daftar tindakan; tombol ke acc-statements dan acc-journal.
 Dipakai oleh : Menu Finance > Control Center > Dashboard (slug acc-dashboard, route v2.screen).
 Status       : Aktif.
--}}
@php
    use App\Support\V2\Data\FinanceDesk as Desk;
    use App\Support\V2\Src;

    $asOf = request('as_of') ?: now()->toDateString();
    $h = Desk::metrics()->headline($asOf);
    $trend = Desk::cashTrend();
    $src = Src::of('tm2acc.fin_voucher', 'tm2acc.fin_invoice', 'tm2acc.fin_tax_register', 'tm2acc.fin_period');
@endphp

<x-dl::card title="Accounting Dashboard" description="Liquidity, receivables, payables, profitability, and compliance status.">
    <x-dl::action-form method="get" action="{{ route('v2.screen', 'acc-dashboard') }}">
        <x-dl::form.row :cols="4"><x-dl::field.date name="as_of" label="As At" :value="$asOf" :source="$src" /></x-dl::form.row>
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="fa-filter" label="Filter" />
            <x-dl::button variant="secondary" icon="fa-file-invoice-dollar" label="Financial Statements" :href="route('v2.screen', 'acc-statements')" />
            <x-dl::button icon="fa-plus" label="New Journal" :href="route('v2.screen', 'acc-journal').'?new=1'" />
        </x-dl::action-bar>
    </x-dl::action-form>
</x-dl::card>

<x-dl::kpi-row>
    @foreach (Desk::dashboardKpis($h) as $k)
        <x-dl::kpi :label="$k['label']" :value="$k['value']" :format="$k['format']" :previous="$k['previous']" :good-when="$k['goodWhen']" :icon="$k['icon']" :tone="$k['tone']" :note="$k['note']" />
    @endforeach
</x-dl::kpi-row>

@if (array_filter($trend['series'][0]['data']))
    <x-dl::chart type="area" title="Cash Flow Overview" subtitle="Monthly operating cash movement — last 12 months" format="money" :labels="$trend['labels']" :series="$trend['series']" :source="$src" />
@else
    <x-dl::alert tone="info" message="There is no cash flow to show yet." />
@endif
<x-dl::action-bar><x-dl::button variant="secondary" size="sm" icon="fa-arrow-right" label="View report" :href="route('v2.screen', 'acc-rpt-cash-flow')" /></x-dl::action-bar>

<x-dl::card title="Compliance Center" description="Closing and tax deadlines">
    @forelse (Desk::cards(Desk::metrics()->compliance()) as $c)
        <x-dl::status-card :title="raw($c['title'])" :desc="raw($c['desc'])" :status="$c['status']" :tone="$c['tone']" :icon="$c['icon']" />
    @empty
        <x-dl::alert tone="info" message="There is no active deadline." />
    @endforelse
    <x-dl::action-bar><x-dl::button variant="secondary" size="sm" icon="fa-percent" label="Open tax" :href="route('v2.screen', 'acc-tax')" /></x-dl::action-bar>
</x-dl::card>

<x-dl::card title="Finance Action Center" description="Prioritized tasks">
    @foreach (Desk::cards(Desk::metrics()->actions()) as $c)
        <x-dl::status-card :title="raw($c['title'])" :desc="raw($c['desc'])" :status="$c['status']" :tone="$c['tone']" :icon="$c['icon']" />
    @endforeach
</x-dl::card>

<x-dl::card title="Shortcuts" description="The screens the Finance team uses most">
    <x-dl::action-bar>
        <x-dl::button variant="secondary" icon="fa-pen" label="Journal Entries" :href="route('v2.screen', 'acc-journal')" />
        <x-dl::button variant="secondary" icon="fa-cash-register" label="Cashier" :href="route('v2.screen', 'acc-cash-bank')" />
        <x-dl::button variant="secondary" icon="fa-book" label="Master of Accounts" :href="route('v2.screen', 'acc-coa')" />
        <x-dl::button variant="secondary" icon="fa-scale-balanced" label="Trial Balance" :href="route('v2.screen', 'acc-rpt-trial-balance')" />
        <x-dl::button variant="secondary" icon="fa-gears" label="Process Engine" :href="route('v2.screen', 'acc-engine')" />
    </x-dl::action-bar>
</x-dl::card>
